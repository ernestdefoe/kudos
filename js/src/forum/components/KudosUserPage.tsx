import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import KudosCard from './KudosCard';
import type { Card, Profile } from './data';
import { avatarUser, call, openGive, personName, personUrl, t, tx, weekLabel } from '../util';

/** A member's Kudos tab: what they were thanked for, by whom most, and their weeks in the spotlight. */

declare const m: Mithril.Static;

export default class KudosUserPage extends UserPage {
  profile: Profile | null = null;
  cards: Card[] = [];
  next: number | null = null;
  loadingMore = false;
  private ticket = 0;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  show(user: any) {
    super.show(user);
    this.load();
  }

  load() {
    const ticket = ++this.ticket;
    this.profile = null;
    call<Profile>('GET', '/users/' + this.user!.id()).then((res) => {
      if (ticket !== this.ticket) return;
      this.profile = res;
      this.cards = res.cards;
      this.next = res.next;
      m.redraw();
    });
  }

  more() {
    if (!this.next || this.loadingMore) return;
    this.loadingMore = true;
    call<{ cards: Card[]; next: number | null }>('GET', '/users/' + this.user!.id(), { before: this.next }).then(
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

  content() {
    const p = this.profile;
    if (!p) return <LoadingIndicator display="block" />;
    const user = this.user!;
    const me = app.session.user;
    const canGive = app.forum.attribute('kudosCanGive') && me && me !== user;

    return (
      <div className="KudosProfile">
        <div className="KudosProfile-head">
          <ul className="KudosProfile-stats">
            <li className="KudosStat KudosStat--main">
              <span className="KudosStat-value">{p.received.toLocaleString()}</span>
              <span className="KudosStat-label">{t('profile.received', { count: p.received })}</span>
            </li>
            <li className="KudosStat">
              <span className="KudosStat-value">{p.thisWeek.toLocaleString()}</span>
              <span className="KudosStat-label">{t('profile.this_week')}</span>
            </li>
            <li className="KudosStat">
              <span className="KudosStat-value">{p.given.toLocaleString()}</span>
              <span className="KudosStat-label">{t('profile.given', { count: p.given })}</span>
            </li>
            <li className="KudosStat">
              <span className="KudosStat-value">
                <Icon name="fas fa-star" className="KudosStat-star" /> {p.spotlights.length}
              </span>
              <span className="KudosStat-label">{t('profile.spotlights', { count: p.spotlights.length })}</span>
            </li>
          </ul>
          {canGive ? (
            <Button
              className="Button Button--primary KudosProfile-give"
              icon="fas fa-hand-holding-heart"
              onclick={() =>
                openGive({
                  receiver: {
                    id: Number(user.id()),
                    username: user.username(),
                    displayName: user.displayName(),
                    slug: user.slug(),
                    avatarUrl: user.avatarUrl(),
                  },
                })
              }
            >
              {t('profile.give', { name: user.displayName() })}
            </Button>
          ) : null}
        </div>

        {p.topGivers.length ? (
          <section className="KudosProfile-section">
            <h3 className="KudosProfile-heading">{t('profile.top_givers')}</h3>
            <ul className="KudosGivers">
              {p.topGivers.map((g) => {
                const url = personUrl(g.user);
                const inner = [
                  <Avatar user={avatarUser(g.user)} className="KudosGivers-avatar" />,
                  <span className="KudosGivers-name">{personName(g.user)}</span>,
                  <span className="KudosGivers-count">×{g.count}</span>,
                ];
                return (
                  <li>
                    {url ? (
                      <Link className="KudosGivers-item" href={url}>
                        {inner}
                      </Link>
                    ) : (
                      <span className="KudosGivers-item">{inner}</span>
                    )}
                  </li>
                );
              })}
            </ul>
          </section>
        ) : null}

        {p.spotlights.length ? (
          <section className="KudosProfile-section">
            <h3 className="KudosProfile-heading">{t('profile.spotlight_weeks')}</h3>
            <ul className="KudosProfile-weeks">
              {p.spotlights.map((s) => (
                <li>
                  <Link className="KudosProfile-week" href={app.route('kudos') + '?week=' + s.week}>
                    <Icon name="fas fa-star" /> {t('week.week_of', { date: weekLabel(s.week) })}{' '}
                    <span className="KudosProfile-weekCount">×{s.count}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        <section className="KudosProfile-section">
          <h3 className="KudosProfile-heading">{t('profile.notes')}</h3>
          {this.cards.length ? (
            <div className="KudosWall KudosWall--profile">
              {this.cards.map((card) => (
                <KudosCard
                  key={card.id}
                  card={card}
                  received
                  onchange={(c: Card | null) => {
                    this.cards = c ? this.cards.map((x) => (x.id === card.id ? c : x)) : this.cards.filter((x) => x.id !== card.id);
                    m.redraw();
                  }}
                />
              ))}
            </div>
          ) : (
            <div className="KudosEmpty KudosEmpty--small">
              <p className="KudosEmpty-title">{t('profile.empty', { name: user.displayName() })}</p>
            </div>
          )}
          {this.next ? (
            <div className="KudosWall-more">
              <Button className="Button" loading={this.loadingMore} onclick={() => this.more()}>
                {t('wall.more')}
              </Button>
            </div>
          ) : null}
        </section>
      </div>
    );
  }
}
