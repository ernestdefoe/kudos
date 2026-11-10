import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import KudosCard from './KudosCard';
import { until, type Card, type Wall } from './data';
import { avatarUser, call, openGive, personName, personUrl, t, tx, weekLabel } from '../util';

/**
 * /kudos: this week's thanks as cards, last week's spotlight, the viewer's
 * kudos left, and the archive of past weeks (?week=2026-11-02). Moderators
 * get the approval queue (?queue=1). Built in core's page structure with the
 * forum's own sidebar, so any theme dresses it.
 */

declare const m: Mithril.Static;
declare const flarum: any;

/** Core components this page builds on, loaded through the registry: some live in lazy chunks. */
let PageStructure: any;
let IndexSidebar: any;

export async function loadCore(): Promise<void> {
  const [structure, sidebar] = await Promise.all([
    flarum.reg.asyncModuleImport('flarum/forum/components/PageStructure'),
    flarum.reg.asyncModuleImport('flarum/forum/components/IndexSidebar'),
  ]);
  PageStructure = structure.default || structure;
  IndexSidebar = sidebar.default || sidebar;
}

export default class WallPage extends Page {
  wall: Wall | null = null;
  cards: Card[] = [];
  next: number | null = null;
  loadingMore = false;
  queue = false;
  weekKey = '';
  private ticket = 0;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.weekKey = (m.route.param('week') as string) || '';
    this.queue = m.route.param('queue') === '1' && !!app.forum.attribute('kudosCanModerate');
    app.history.push('kudos', tx('page.title'));
    this.bodyClass = 'App--kudos';
    this.load();
  }

  oncreate(vnode: Mithril.VnodeDOM) {
    super.oncreate(vnode);
    app.setTitle(tx('page.title'));
    app.setTitleCount(0);
  }

  params(extra: Record<string, any> = {}) {
    const p: Record<string, any> = { ...extra };
    if (this.weekKey) p.week = this.weekKey;
    if (this.queue) p.queue = 1;
    return p;
  }

  load() {
    const ticket = ++this.ticket;
    this.cards = [];
    this.next = null;
    // The queue page has no week header of its own; it still wants the wallet and counts.
    const first = call<Wall>('GET', '', this.weekKey ? { week: this.weekKey } : undefined);
    const queue = this.queue ? call<Pick<Wall, 'cards' | 'next'>>('GET', '', { queue: 1 }) : null;
    Promise.all([first, queue]).then(([wall, q]) => {
      if (ticket !== this.ticket) return;
      this.wall = wall;
      this.cards = q ? q.cards : wall.cards;
      this.next = q ? q.next : wall.next;
      m.redraw();
    });
  }

  more() {
    if (!this.next || this.loadingMore) return;
    this.loadingMore = true;
    call<{ cards: Card[]; next: number | null }>('GET', '', this.params({ before: this.next })).then(
      (res) => {
        this.cards = [...this.cards, ...res.cards];
        this.next = res.next;
        this.loadingMore = false;
        m.redraw();
      },
      () => {
        this.loadingMore = false;
        m.redraw();
      }
    );
  }

  go(week: string, queue = false) {
    this.weekKey = week;
    this.queue = queue;
    const qs = queue ? '?queue=1' : week ? '?week=' + week : '';
    window.history.replaceState(window.history.state, '', app.route('kudos') + qs);
    this.wall = null;
    this.load();
    window.scrollTo({ top: 0 });
  }

  give() {
    // A kudos always lands in this week.
    openGive({ ongive: () => this.go('') });
  }

  changed(id: number, card: Card | null) {
    this.cards = card ? this.cards.map((c) => (c.id === id ? card : c)) : this.cards.filter((c) => c.id !== id);
    if (this.queue && card && !card.pending) this.cards = this.cards.filter((c) => c.id !== id);
    if (this.wall && this.wall.queue !== null && (!card || !card.pending) && this.queue) this.wall.queue = Math.max(0, this.wall.queue - 1);
    m.redraw();
  }

  view() {
    return (
      <PageStructure className="KudosPage" sidebar={() => <IndexSidebar />}>
        {this.hero()}
        {this.wall ? this.body() : <LoadingIndicator display="block" />}
      </PageStructure>
    );
  }

  hero() {
    const me = this.wall?.me;
    const canGive = app.forum.attribute('kudosCanGive');

    return (
      <header className="KudosPage-hero">
        <div className="KudosPage-heading">
          <h2 className="KudosPage-title">
            <span className="KudosPage-mark" aria-hidden="true">
              <Icon name="fas fa-hand-holding-heart" />
            </span>
            {t('page.title')}
          </h2>
          <p className="KudosPage-lead">{t('page.lead')}</p>
        </div>
        {canGive && me ? (
          <div className="KudosPage-wallet">
            <div className="KudosWallet" title={tx('wallet.resets', { time: until(me.resetsAt) })}>
              <span className="KudosWallet-pips" aria-hidden="true">
                {Array.from({ length: me.allowance }, (_, i) => (
                  <span className={'KudosWallet-pip' + (i < me.left ? ' is-left' : '')} />
                ))}
              </span>
              <span className="KudosWallet-text">
                <strong>{t('wallet.left', { count: me.left })}</strong>
                <span className="KudosWallet-reset">{t('wallet.resets', { time: until(me.resetsAt) })}</span>
              </span>
            </div>
            <Button
              className="Button Button--primary KudosPage-give"
              icon="fas fa-hand-holding-heart"
              disabled={!me.left || !me.canGive}
              onclick={() => this.give()}
            >
              {t('page.give')}
            </Button>
          </div>
        ) : null}
      </header>
    );
  }

  body() {
    const wall = this.wall!;
    return (
      <div className="KudosPage-body">
        {this.weekBar(wall)}
        {!this.queue && wall.spotlight ? this.spotlight(wall) : null}
        {this.grid(wall)}
        {this.archive(wall)}
      </div>
    );
  }

  weekBar(wall: Wall) {
    const week = wall.week;
    const archive = wall.archive.map((a) => a.week);
    const idx = archive.indexOf(week.key);
    // Older: the next archived week before this one; newer: the one after, or back to this week.
    const older = week.current ? archive[0] : archive[idx + 1];
    const newer = week.current ? null : idx > 0 ? archive[idx - 1] : '';
    const end = new Date(Date.parse(week.end) - 1000);

    return (
      <div className="KudosWeekBar">
        <div className="KudosWeekBar-nav">
          <Button
            className="Button Button--icon KudosWeekBar-step"
            icon="fas fa-chevron-left"
            aria-label={tx('week.older')}
            disabled={this.queue || !older}
            onclick={() => older && this.go(older)}
          />
          <div className="KudosWeekBar-label">
            <span className="KudosWeekBar-title">
              {this.queue ? t('queue.title') : week.current ? t('week.this_week') : t('week.week_of', { date: weekLabel(week.key) })}
            </span>
            {this.queue ? null : (
              <span className="KudosWeekBar-sub">
                {weekLabel(week.key)} – {end.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                {' · '}
                {t('week.count', { count: week.total })}
                {week.current ? ' · ' + tx('week.ends', { time: until(week.end) }) : ''}
              </span>
            )}
          </div>
          <Button
            className="Button Button--icon KudosWeekBar-step"
            icon="fas fa-chevron-right"
            aria-label={tx('week.newer')}
            disabled={this.queue || newer === null || newer === undefined}
            onclick={() => newer !== null && newer !== undefined && this.go(newer)}
          />
        </div>
        <div className="KudosWeekBar-actions">
          {!week.current || this.queue ? (
            <Button className="Button KudosWeekBar-today" onclick={() => this.go('')}>
              {t('week.back_to_this_week')}
            </Button>
          ) : null}
          {wall.queue !== null && (wall.moderation || wall.queue > 0) ? (
            <Button
              className={'Button KudosWeekBar-queue' + (this.queue ? ' active' : '')}
              icon="far fa-clock"
              onclick={() => this.go('', !this.queue)}
            >
              {t('queue.button')} {wall.queue ? <span className="Button-badge">{wall.queue}</span> : null}
            </Button>
          ) : null}
        </div>
      </div>
    );
  }

  spotlight(wall: Wall) {
    const s = wall.spotlight!;
    const url = personUrl(s.user);
    const nameNode = url ? <Link href={url}>{personName(s.user)}</Link> : <span>{personName(s.user)}</span>;

    return (
      <section className="KudosSpotlight" aria-label={tx('spotlight.label')}>
        <div className="KudosSpotlight-rays" aria-hidden="true" />
        <div className="KudosSpotlight-avatar">
          <Avatar user={avatarUser(s.user)} />
          <span className="KudosSpotlight-medal" aria-hidden="true">
            <Icon name="fas fa-star" />
          </span>
        </div>
        <div className="KudosSpotlight-text">
          <div className="KudosSpotlight-eyebrow">{wall.week.current ? t('spotlight.last_week') : t('spotlight.that_week')}</div>
          <div className="KudosSpotlight-name">{nameNode}</div>
          <div className="KudosSpotlight-count">{t('spotlight.count', { count: s.count, date: weekLabel(s.week) })}</div>
        </div>
        {url ? (
          <Link className="Button KudosSpotlight-cta" href={app.route('user.kudos', { username: s.user!.slug })}>
            {t('spotlight.see_kudos')}
          </Link>
        ) : null}
      </section>
    );
  }

  grid(wall: Wall) {
    if (!this.cards.length) {
      return (
        <div className="KudosEmpty">
          <div className="KudosEmpty-art" aria-hidden="true">
            <Icon name="fas fa-hand-holding-heart" />
          </div>
          <p className="KudosEmpty-title">{this.queue ? t('queue.empty') : wall.week.current ? t('empty.this_week') : t('empty.past_week')}</p>
          {wall.week.current && !this.queue && wall.me?.left ? (
            <Button className="Button Button--primary" icon="fas fa-hand-holding-heart" onclick={() => this.give()}>
              {t('empty.cta')}
            </Button>
          ) : null}
        </div>
      );
    }

    return (
      <div className="KudosWall-wrap">
        <div className="KudosWall">
          {this.cards.map((card) => (
            <KudosCard key={card.id} card={card} onchange={(c: Card | null) => this.changed(card.id, c)} />
          ))}
        </div>
        {this.next ? (
          <div className="KudosWall-more">
            <Button className="Button" loading={this.loadingMore} onclick={() => this.more()}>
              {t('wall.more')}
            </Button>
          </div>
        ) : null}
      </div>
    );
  }

  archive(wall: Wall) {
    if (!wall.archive.length) return null;
    return (
      <section className="KudosArchive">
        <h3 className="KudosArchive-title">{t('archive.title')}</h3>
        <ul className="KudosArchive-list">
          {wall.archive.map((a) => (
            <li key={a.week}>
              <a
                className={'KudosArchive-week' + (a.week === wall.week.key && !this.queue ? ' active' : '')}
                href={app.route('kudos') + '?week=' + a.week}
                onclick={(e: MouseEvent) => {
                  if (e.ctrlKey || e.metaKey || e.shiftKey || e.button) return;
                  e.preventDefault();
                  this.go(a.week);
                }}
              >
                <span className="KudosArchive-date">{t('week.week_of', { date: weekLabel(a.week) })}</span>
                <span className="KudosArchive-count">{t('week.count', { count: a.count })}</span>
                {a.spotlight ? (
                  <span className="KudosArchive-spot">
                    <Icon name="fas fa-star" />
                    <Avatar user={avatarUser(a.spotlight)} className="KudosArchive-avatar" />
                    <span className="KudosArchive-spotName">{personName(a.spotlight)}</span>
                  </span>
                ) : null}
              </a>
            </li>
          ))}
        </ul>
      </section>
    );
  }
}
