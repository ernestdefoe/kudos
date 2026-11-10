import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import { groupAllowances, SpotlightSettings } from './parts';

const t = (key: string, params: Record<string, any> = {}) => app.translator.trans(`ernestdefoe-kudos.admin.${key}`, params, true);
const setting = (key: string) => `ernestdefoe-kudos.${key}`;
const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

function zones(): Record<string, string> {
  let list: string[] = [];
  try {
    list = (Intl as any).supportedValuesOf('timeZone');
  } catch {}
  if (!list.includes('UTC')) list = ['UTC', ...list];
  return Object.fromEntries(list.map((z) => [z, z.replace(/_/g, ' ')]));
}

export default [
  new Extend.Admin()
    .setting(() => ({ setting: setting('nav'), type: 'boolean', label: t('settings.nav') }), 100)
    .setting(
      () => ({ setting: setting('allowance'), type: 'number', min: 1, max: 50, label: t('settings.allowance'), help: t('settings.allowance_help') }),
      95
    )
    // function, not arrow: the page calls it with itself as `this`, so the value saves with its Save button.
    .customSetting(function (this: any) {
      return groupAllowances(this.setting(setting('group_allowances')));
    }, 94)
    .setting(
      () => ({
        setting: setting('start_day'),
        type: 'select',
        options: Object.fromEntries(DAYS.map((d, i) => [String(i), String(t('settings.days.' + d))])),
        default: '1',
        label: t('settings.start_day'),
      }),
      90
    )
    .setting(
      () => ({
        setting: setting('timezone'),
        type: 'select',
        options: zones(),
        default: 'UTC',
        label: t('settings.timezone'),
        help: t('settings.timezone_help'),
      }),
      89
    )
    .setting(() => ({ setting: setting('moderation'), type: 'boolean', label: t('settings.moderation'), help: t('settings.moderation_help') }), 85)
    .setting(() => ({ setting: setting('banner'), type: 'boolean', label: t('settings.banner'), help: t('settings.banner_help') }), 80)
    .customSetting(function (this: any) {
      return <SpotlightSettings badge={this.setting(setting('spotlight_badge'))} group={this.setting(setting('spotlight_group'))} />;
    }, 75)
    .permission(() => ({ permission: 'kudos.view', icon: 'fas fa-hand-holding-heart', label: t('permissions.view'), allowGuest: true }), 'view', 55)
    .permission(() => ({ permission: 'kudos.give', icon: 'fas fa-hand-holding-heart', label: t('permissions.give') }), 'reply', 55)
    .permission(() => ({ permission: 'kudos.moderate', icon: 'fas fa-hand-holding-heart', label: t('permissions.moderate') }), 'moderate', 55),
];
