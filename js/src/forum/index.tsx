import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import type ItemList from 'flarum/common/utils/ItemList';
import LinkButton from 'flarum/common/components/LinkButton';
import Button from 'flarum/common/components/Button';
import Notification from 'flarum/forum/components/Notification';
import username from 'flarum/common/helpers/username';
import PostControls from 'flarum/forum/utils/PostControls';
import UserControls from 'flarum/forum/utils/UserControls';
import SpotlightBanner from './SpotlightBanner';
import { openGive, t, tx } from './util';

export { default as extend } from './extend';

/*
 * forum.js is loaded on every page, so it only wires things up: the route,
 * the sidebar and profile links, "Give kudos" in post and member menus, the
 * alerts and the spotlight banner. The wall, the profile tab and the give
 * form are one chunk fetched when first needed.
 */

class ReceivedNotification extends Notification {
  icon() {
    return 'fas fa-hand-holding-heart';
  }
  href() {
    const me = app.session.user;
    return me ? app.route('user.kudos', { username: me.slug() }) : app.route('kudos');
  }
  content() {
    // A giver whose account is gone shows as "a former member".
    const from = (this.attrs.notification as any).fromUser();
    return t('notification.received', { username: from ? username(from) : tx('person.former') });
  }
  excerpt() {
    const note = (this.attrs.notification as any).subject()?.attribute?.('note');
    return note ? '“' + note + '”' : '';
  }
}

class SpotlightNotification extends Notification {
  icon() {
    return 'fas fa-star';
  }
  href() {
    const week = (this.attrs.notification as any).content()?.week;
    return app.route('kudos') + (week ? '?week=' + week : '');
  }
  content() {
    return t('notification.spotlight');
  }
  excerpt() {
    const count = (this.attrs.notification as any).content()?.count || 0;
    return tx('notification.spotlight_excerpt', { count });
  }
}

app.initializers.add('ernestdefoe/kudos', () => {
  app.notificationComponents.kudosReceived = ReceivedNotification;
  app.notificationComponents.kudosSpotlight = SpotlightNotification;

  // String form: these components live in lazy chunks, and extending an imported copy patches one core never renders.
  extend('flarum/forum/components/IndexSidebar', 'navItems', function (items: ItemList<any>) {
    if (!app.forum.attribute('kudosCanView') || !app.forum.attribute('kudosNav')) return;
    items.add('kudos', LinkButton.component({ href: app.route('kudos'), icon: 'fas fa-hand-holding-heart' }, t('nav')), 73);
  });

  extend('flarum/forum/components/UserPage', 'navItems', function (this: any, items: ItemList<any>) {
    if (!app.forum.attribute('kudosCanView') || !this.user) return;
    items.add(
      'kudos',
      LinkButton.component({ href: app.route('user.kudos', { username: this.user.slug() }), icon: 'fas fa-hand-holding-heart' }, t('profile.tab')),
      75
    );
  });

  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items: ItemList<any>) {
    if (!app.forum.attribute('kudosCanView')) return;
    items.add('kudosReceived', { name: 'kudosReceived', icon: 'fas fa-hand-holding-heart', label: t('notification.preference_received') });
    items.add('kudosSpotlight', { name: 'kudosSpotlight', icon: 'fas fa-star', label: t('notification.preference_spotlight') });
  });

  // "Give kudos" for a post by someone else: the post comes along as the one that helped.
  // Plain objects, not classes, and in core's own bundle: extend them directly.
  extend(PostControls, 'userControls', function (items: ItemList<any>, post: any) {
    const me = app.session.user;
    const author = post.user?.();
    if (!app.forum.attribute('kudosCanGive') || !me || !author || author === me || post.contentType?.() !== 'comment' || post.isHidden?.()) return;
    items.add(
      'kudos',
      Button.component(
        {
          icon: 'fas fa-hand-holding-heart',
          onclick: () =>
            openGive({
              receiver: {
                id: Number(author.id()),
                username: author.username(),
                displayName: author.displayName(),
                slug: author.slug(),
                avatarUrl: author.avatarUrl(),
              },
              post: { id: Number(post.id()), number: post.number(), title: post.discussion?.()?.title?.() },
            }),
        },
        t('give.post_control')
      )
    );
  });

  // And from a member's own menu on their profile or user card.
  extend(UserControls, 'userControls', function (items: ItemList<any>, user: any) {
    const me = app.session.user;
    if (!app.forum.attribute('kudosCanGive') || !me || !user || user === me) return;
    items.add(
      'kudos',
      Button.component(
        {
          icon: 'fas fa-hand-holding-heart',
          onclick: () =>
            openGive({
              receiver: {
                id: Number(user.id()),
                username: user.username(),
                displayName: user.displayName(),
                slug: user.slug(),
                avatarUrl: user.avatarUrl(),
              },
            }),
        },
        t('give.user_control')
      )
    );
  });

  // Last week's most-thanked member, above the discussion list, while this week runs.
  extend('flarum/forum/components/IndexPage', 'contentItems', function (items: ItemList<any>) {
    const spot = app.forum.attribute<any>('kudosSpotlight');
    if (spot) items.add('kudosSpotlight', <SpotlightBanner spotlight={spot} />, 105);
  });
});
