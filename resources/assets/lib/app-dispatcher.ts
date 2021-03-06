// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import DispatcherAction from 'actions/dispatcher-action';
import DispatchListener from 'dispatch-listener';
import Dispatcher from 'dispatcher';

export const dispatcher = new Dispatcher();

function isDispatchListener(target: any): target is DispatchListener {
  return target.handleDispatchAction;
}

export function dispatch(data: DispatcherAction) {
  dispatcher.dispatch(data);
}

// https://www.typescriptlang.org/docs/handbook/decorators.html#class-decorators
export function dispatchListener<T extends new(...args: any[]) => {}>(ctor: T) {
  return class extends ctor {
    constructor(...args: any[]) {
      super(...args);

      if (isDispatchListener(this)) {
        dispatcher.register(this);
      }
    }
  };
}
