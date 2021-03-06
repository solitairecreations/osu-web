<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Jobs\Notifications;

use App\Events\NewPrivateNotificationEvent;
use App\Exceptions\InvalidNotificationException;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserNotificationOption;
use App\Traits\NotificationQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

abstract class BroadcastNotificationBase implements ShouldQueue
{
    use NotificationQueue, Queueable, SerializesModels;

    const CONTENT_TRUNCATE = 36;

    const NOTIFICATION_OPTION_NAME = null;

    protected $name;
    protected $source;

    public static function getBaseKey(Notification $notification): string
    {
        $category = Notification::nameToCategory($notification->name);

        return "{$notification->notifiable_type}.{$category}";
    }

    public static function getMailGroupingKey(Notification $notification): string
    {
        $base = static::getBaseKey($notification);

        return "{$base}-{$notification->notifiable_type}-{$notification->notifiable_id}";
    }

    abstract public static function getMailLink(Notification $notification): string;

    public static function getNotificationClass(string $name)
    {
        $class = get_class_namespace(static::class).'\\'.studly_case($name);

        if (!class_exists($class)) {
            throw new InvalidNotificationException('Invalid event name: '.$name);
        }

        return $class;
    }

    public static function getNotificationClassFromNotification(Notification $notification)
    {
        return static::getNotificationClass($notification->name);
    }

    private static function applyDeliverySettings(array $userIds)
    {
        static $defaults = ['mail' => true, 'push' => true];

        if (static::NOTIFICATION_OPTION_NAME !== null) {
            // FIXME: filtering all the ids could get quite large?
            $notificationOptions = UserNotificationOption
                ::whereIn('user_id', $userIds)
                ->where(['name' => static::NOTIFICATION_OPTION_NAME])
                ->whereNotNull('details')
                ->get()
                ->keyBy('user_id');
        } else {
            $notificationOptions = [];
        }

        $deliverySettings = [];
        foreach ($userIds as $userId) {
            $details = $notificationOptions[$userId]->details ?? $defaults;
            $delivery = 0;
            foreach (UserNotification::DELIVERY_OFFSETS as $type => $_offset) {
                if ($details[$type] ?? $defaults[$type]) {
                    $delivery |= UserNotification::deliveryMask($type);
                }
            }

            $deliverySettings[$userId] = $delivery;
        }

        return $deliverySettings;
    }

    private static function excludeBotUserIds(array $userIds)
    {
        // smaller return size from database compared to "group_id <> bot"
        $botUserIds = User
            ::whereIn('user_id', $userIds)
            ->where('group_id', app('groups')->byIdentifier('bot')->getKey())
            ->pluck('user_id')
            ->all();

        return array_values(array_diff($userIds, $botUserIds));
    }

    public function __construct(?User $source = null)
    {
        $this->name = snake_case(get_class_basename(get_class($this)));
        $this->source = $source;
    }

    abstract public function getDetails(): array;

    abstract public function getListeningUserIds(): array;

    public function getName()
    {
        return $this->name;
    }

    abstract public function getNotifiable();

    /**
     * In most cases this is a deduplicated list that excludes the user id that
     * triggered the notifications. This should be overriden in cases where the source user id shouldn't be removed.
     * e.g. UserAchievementUnlock.
     */
    public function getReceiverIds(): array
    {
        return array_values(array_unique(array_diff($this->getListeningUserIds(), [optional($this->source)->getKey()])));
    }

    public function getTimestamp()
    {
        return now();
    }

    public function handle()
    {
        $deliverySettings = static::applyDeliverySettings(static::excludeBotUserIds($this->getReceiverIds()));

        if (empty($deliverySettings)) {
            return;
        }

        $notification = $this->makeNotification();
        $notification->saveOrExplode();

        // client should now be able to handle push notifications that come in after notification has been loaded,
        // so, it should be fine to create the user notifications first.

        $pushReceiverIds = [];
        $notification->getConnection()->transaction(function () use ($deliverySettings, $notification, &$pushReceiverIds) {
            foreach ($deliverySettings as $userId => $delivery) {
                $userNotification = $notification->userNotifications()->create(['delivery' => $delivery, 'user_id' => $userId]);
                $userNotification->isPush() && $pushReceiverIds[] = $userId;
            }
        });

        if (!empty($pushReceiverIds)) {
            event(new NewPrivateNotificationEvent($notification, $pushReceiverIds));
        }
    }

    public function makeNotification(): Notification
    {
        $params['created_at'] = $this->getTimestamp();
        $params['details'] = $this->getDetails();
        $params['name'] = $this->name;

        if ($this->source !== null) {
            $params['details']['username'] = $this->source->username;
        }

        $notification = new Notification($params);
        $notification->notifiable()->associate($this->getNotifiable());
        if ($this->source !== null) {
            $notification->source()->associate($this->source);
        }

        return $notification;
    }
}
