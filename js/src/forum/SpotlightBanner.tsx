import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Avatar from 'flarum/common/components/Avatar';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import { avatarUser, personName, personUrl, t, tx } from './util';

declare const m: Mithril.Static;

/**
 * Above the discussion list while a week runs: last week's most-thanked
 * member. Tiny, so it ships in forum.js. Dismissed per week, in this browser.
 */

interface Attrs extends ComponentAttrs {
  spotlight: { week: string; count: number; user: any };
}

const KEY = 'ernestdefoe-kudos.banner-dismissed';

function dismissed(week: string): boolean {
  try {
    return localStorage.getItem(KEY) === week;
  } catch {
    return false;
  }
}

export default class SpotlightBanner extends Component<Attrs> {
  view() {
    const { week, count, user } = this.attrs.spotlight;
    if (dismissed(week)) return null;
    const url = personUrl(user);
    const me = app.session.user && Number(app.session.user.id()) === user.id;

    return (
      <div className="KudosBanner" role="note">
        <div className="KudosBanner-glow" aria-hidden="true" />
        <span className="KudosBanner-avatar">
          <Avatar user={avatarUser(user)} />
          <span className="KudosBanner-star" aria-hidden="true">
            <Icon name="fas fa-star" />
          </span>
        </span>
        <div className="KudosBanner-text">
          <div className="KudosBanner-eyebrow">{t('banner.eyebrow')}</div>
          <div className="KudosBanner-line">
            {me
              ? t('banner.you', { count })
              : t('banner.line', { name: url ? <Link href={url}>{personName(user)}</Link> : <strong>{personName(user)}</strong>, count })}
          </div>
        </div>
        <Link className="Button Button--primary KudosBanner-cta" href={app.route('kudos') + '?week=' + week}>
          {t('banner.cta')}
        </Link>
        <Button
          className="Button Button--icon Button--link KudosBanner-close"
          icon="fas fa-times"
          aria-label={tx('banner.dismiss')}
          onclick={() => {
            try {
              localStorage.setItem(KEY, week);
            } catch {}
            m.redraw();
          }}
        />
      </div>
    );
  }
}
