###
#    Copyright 2015-2017 ppy Pty. Ltd.
#
#    This file is part of osu!web. osu!web is distributed with the hope of
#    attracting more community contributions to the core ecosystem of osu!.
#
#    osu!web is free software: you can redistribute it and/or modify
#    it under the terms of the Affero GNU General Public License version 3
#    as published by the Free Software Foundation.
#
#    osu!web is distributed WITHOUT ANY WARRANTY; without even the implied
#    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
#    See the GNU Affero General Public License for more details.
#
#    You should have received a copy of the GNU Affero General Public License
#    along with osu!web.  If not, see <http://www.gnu.org/licenses/>.
###

{div, h3, ul, li, p, span} = ReactDOMFactories
el = React.createElement

class ProfilePage.Kudosu extends React.Component
  render: =>
    div className: 'page-extra',
      el ProfilePage.ExtraHeader, name: @props.name, withEdit: @props.withEdit

      div className: 'kudosu-box',
        div className: 'value-display value-display--kudosu',
          div className: 'value-display__label',
            osu.trans('users.show.extra.kudosu.total')
          div className: 'value-display__value', @props.user.kudosu.total.toLocaleString()
          div
            className: 'value-display__description'
            dangerouslySetInnerHTML:
              __html: osu.trans('users.show.extra.kudosu.total_info')
        div className: 'value-display value-display--kudosu',
          div className: 'value-display__label',
            osu.trans('users.show.extra.kudosu.available')
          div className: 'value-display__value', @props.user.kudosu.available.toLocaleString()
          div
            className: 'value-display__description'
            osu.trans('users.show.extra.kudosu.available_info')

      div className: 'kudosu-entries',
        if @props.recentlyReceivedKudosu?.length
          ul className: 'profile-extra-entries',
            for kudosu in @props.recentlyReceivedKudosu
              continue if !kudosu.id?

              giver =
                if kudosu.giver?
                  osu.link kudosu.giver.url,
                    kudosu.giver.username
                    classNames: ['kudosu-entries__link']
                else
                  _.escape osu.trans('users.deleted')

              post = osu.link kudosu.post?.url,
                kudosu.post?.title
                classNames: ['kudosu-entries__link']

              li key: "kudosu-#{kudosu.id}", className: 'profile-extra-entries__item',
                div className: 'profile-extra-entries__detail',
                  div className: 'profile-extra-entries__text', dangerouslySetInnerHTML:
                    __html: osu.trans "users.show.extra.kudosu.entry.#{kudosu.model}.#{kudosu.action}",
                      amount: "<strong class='kudosu-entries__amount'>#{osu.trans 'users.show.extra.kudosu.entry.amount', amount: Math.abs(kudosu.amount)}</strong>"
                      giver: giver
                      post: post
                div className: 'profile-extra-entries__time', dangerouslySetInnerHTML:
                  __html: osu.timeago(kudosu.created_at)

            li className: 'profile-extra-entries__item',
              el ShowMoreLink,
                event: 'profile:showMore'
                hasMore: @props.pagination.recentlyReceivedKudosu.hasMore
                loading: @props.pagination.recentlyReceivedKudosu.loading
                data:
                  name: 'recentlyReceivedKudosu'
                  url: laroute.route 'users.kudosu', user: @props.user.id

        else
          div className: 'profile-extra-entries', osu.trans('users.show.extra.kudosu.entry.empty')
