import app from 'flarum/admin/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/helpers/humanTime';
import type Mithril from 'mithril';

declare const m: Mithril.Static;

const t = (key: string, params: Record<string, any> = {}) => app.translator.trans(`ernestdefoe-kudos.admin.${key}`, params, true);

/** Groups that get more kudos a week; empty = the base allowance. A member gets the most any of their groups allows. */
export function groupAllowances(stream: any): Mithril.Children {
  let map: Record<string, number> = {};
  try {
    map = JSON.parse(stream() || '{}') || {};
  } catch {
    map = {};
  }
  // Guests can't give.
  const groups = app.store.all<any>('groups').filter((g) => g.id() !== '2');
  const set = (id: string, value: string) => {
    const next = { ...map };
    if (value === '') delete next[id];
    else next[id] = Math.max(1, Math.min(50, parseInt(value, 10) || 1));
    stream(JSON.stringify(next));
  };

  return (
    <div className="Form-group">
      <label>{t('settings.group_allowances')}</label>
      <div className="helpText">{t('settings.group_allowances_help')}</div>
      <div className="KudosAdmin-groups">
        {groups.map((group) => [
          <label for={`kudos-allow-${group.id()}`}>{group.namePlural()}</label>,
          <input
            id={`kudos-allow-${group.id()}`}
            className="FormControl"
            type="number"
            min="1"
            max="50"
            placeholder={String(t('settings.group_allowance_placeholder'))}
            value={map[group.id()] ?? ''}
            oninput={(e: InputEvent) => set(group.id(), (e.target as HTMLInputElement).value)}
          />,
        ])}
      </div>
    </div>
  );
}

interface SpotlightAttrs extends ComponentAttrs {
  badge: any;
  group: any;
}

/**
 * The spotlight's rewards: a FoF Badges badge (only when FoF Badges is on,
 * only badges without a trigger) and a group worn for a week (never a
 * built-in group). Plus when the scheduler last ran.
 */
export class SpotlightSettings extends Component<SpotlightAttrs> {
  info: { badges: { id: number; name: string }[] | null; lastRun: string | null } | null = null;

  oninit(vnode: Mithril.Vnode<SpotlightAttrs, this>) {
    super.oninit(vnode);
    app
      .request<any>({ method: 'GET', url: app.forum.attribute('apiUrl') + '/kudos/admin' })
      .then((res) => {
        this.info = res;
        m.redraw();
      })
      .catch(() => {});
  }

  view() {
    const { badge, group } = this.attrs;
    // Admins (1), guests (2), members (3) and moderators (4) are never offered.
    const groups = app.store.all<any>('groups').filter((g) => Number(g.id()) > 4);
    const info = this.info;

    return (
      <div className="KudosAdmin-spotlight">
        <h3 className="KudosAdmin-heading">{t('spotlight.heading')}</h3>
        <p className="helpText">{t('spotlight.help')}</p>

        <div className="Form-group">
          <label for="kudos-spot-group">{t('spotlight.group')}</label>
          <select
            id="kudos-spot-group"
            className="FormControl"
            value={group() || ''}
            onchange={(e: Event) => group((e.target as HTMLSelectElement).value)}
          >
            <option value="">{t('spotlight.none')}</option>
            {groups.map((g) => (
              <option value={g.id()}>{g.nameSingular()}</option>
            ))}
          </select>
          <div className="helpText">{groups.length ? t('spotlight.group_help') : t('spotlight.group_none')}</div>
        </div>

        <div className="Form-group">
          <label for="kudos-spot-badge">{t('spotlight.badge')}</label>
          {!info ? (
            <LoadingIndicator size="small" display="inline" />
          ) : info.badges === null ? (
            <div className="helpText">{t('spotlight.badge_needs_fof')}</div>
          ) : (
            [
              <select
                id="kudos-spot-badge"
                className="FormControl"
                value={badge() || ''}
                onchange={(e: Event) => badge((e.target as HTMLSelectElement).value)}
              >
                <option value="">{t('spotlight.none')}</option>
                {info.badges.map((b) => (
                  <option value={String(b.id)}>{b.name}</option>
                ))}
              </select>,
              <div className="helpText">{t('spotlight.badge_help')}</div>,
            ]
          )}
        </div>

        <div className="KudosAdmin-scheduler">
          {info?.lastRun ? t('spotlight.last_run', { time: humanTime(info.lastRun) }) : info ? t('spotlight.never_run') : null}
        </div>
      </div>
    );
  }
}
