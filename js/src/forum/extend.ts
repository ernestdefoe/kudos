import Extend from 'flarum/common/extenders';
import Model from 'flarum/common/Model';
import UserPageResolver from 'flarum/forum/resolvers/UserPageResolver';

/** A kudos as a notification subject: the alert reads its note (gone once a moderator hides it). */
class KudosModel extends Model {}

export default [
  // The wall and the profile tab are one chunk: it loads when somebody opens either, not with every page.
  new Extend.Routes()
    .add('kudos', '/kudos', () => import('./components').then(async (ui) => (await ui.loadCore(), { default: ui.WallPage })))
    .add('user.kudos', '/u/:username/kudos', () => import('./components').then((ui) => ({ default: ui.KudosUserPage })), UserPageResolver),

  // 🚨 Every notification subject type needs a store model, or the alert list throws.
  new Extend.Store().add('kudos', KudosModel),
];
