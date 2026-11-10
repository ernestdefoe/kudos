import app from 'flarum/forum/app';
import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import Avatar from 'flarum/common/components/Avatar';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import KudosCard from './KudosCard';
import CardArt from './CardArt';
import { STYLES, until, type Card, type Wallet } from './data';
import { avatarUser, call, personName, t, tx, type Person } from '../util';

/**
 * Give a kudos: pick who (or come from their post or profile), write a short
 * note, pick a card, see it as it will look. The server decides everything
 * that matters (kudos left, one a week each, never yourself); this only
 * shows what it sent.
 */

declare const m: Mithril.Static;

const NOTE_MAX = 280;

interface Attrs extends IFormModalAttrs {
  receiver?: Person | null;
  post?: { id: number; number: number; title?: string } | null;
  ongive?: () => void;
}

export default class GiveKudosModal extends FormModal<Attrs> {
  wallet: Wallet | null = null;
  receiver: Person | null = null;
  post: Attrs['post'] = null;
  note = '';
  design = STYLES[0];
  query = '';
  results: Person[] = [];
  searching = false;
  private timer: number | undefined;
  private ticket = 0;

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);
    this.receiver = this.attrs.receiver || null;
    this.post = this.attrs.post || null;
    // A different card each time the form opens, so the wall stays varied.
    this.design = STYLES[Math.floor(Math.random() * STYLES.length)];
    call<{ me: Wallet | null }>('GET', '/me').then((res) => {
      this.wallet = res.me;
      m.redraw();
    });
  }

  className() {
    return 'KudosGiveModal Modal--medium';
  }

  title() {
    return t('give.title');
  }

  search(value: string) {
    this.query = value;
    clearTimeout(this.timer);
    if (!value.trim()) {
      this.results = [];
      this.searching = false;
      return;
    }
    this.searching = true;
    this.timer = window.setTimeout(() => {
      const ticket = ++this.ticket;
      call<{ users: Person[] }>('GET', '/recipients', { q: value.trim() }, true).then(
        (res) => {
          if (ticket !== this.ticket) return;
          this.results = res.users;
          this.searching = false;
          m.redraw();
        },
        () => {
          this.searching = false;
          m.redraw();
        }
      );
    }, 200);
  }

  thanked(p: Person | null): boolean {
    return !!p?.id && !!this.wallet?.givenTo.includes(p.id);
  }

  pick(p: Person) {
    if (this.thanked(p)) return;
    // The post came with the member it was by.
    if (this.receiver?.id !== p.id) this.post = null;
    this.receiver = p;
    this.query = '';
    this.results = [];
  }

  pickFirst() {
    const first = this.results.find((p) => !this.thanked(p));
    if (first) this.pick(first);
  }

  preview(): Card {
    const me = app.session.user!;
    return {
      id: 0,
      week: '',
      style: this.design,
      note: this.note.trim() || tx('give.preview_note'),
      noteHidden: false,
      pending: false,
      mine: true,
      createdAt: new Date().toISOString(),
      giver: { id: Number(me.id()), username: me.username(), displayName: me.displayName(), avatarUrl: me.avatarUrl() },
      receiver: this.receiver || { id: null, displayName: tx('give.preview_receiver'), username: '' },
      post: null,
    };
  }

  content() {
    const w = this.wallet;
    if (!w) return <div className="Modal-body">{<LoadingIndicator display="block" />}</div>;
    if (!w.canGive) return <div className="Modal-body KudosGive-blocked">{t('errors.kudos_confirm_email')}</div>;
    if (!w.left) {
      return (
        <div className="Modal-body KudosGive-blocked">
          <Icon name="far fa-hourglass" /> {t('give.none_left', { time: until(w.resetsAt) })}
        </div>
      );
    }

    const left = NOTE_MAX - this.note.length;

    return (
      <div className="Modal-body">
        <div className="KudosGive-preview">
          <KudosCard card={this.preview()} preview />
        </div>

        <div className="Form-group">
          <label className="KudosGive-label">{t('give.to')}</label>
          {this.receiver ? (
            <div className="KudosGive-chosen">
              <Avatar user={avatarUser(this.receiver)} />
              <span className="KudosGive-chosenName">{personName(this.receiver)}</span>
              {this.thanked(this.receiver) ? <span className="KudosGive-warn">{t('give.already')}</span> : null}
              <Button
                className="Button Button--link KudosGive-change"
                onclick={() => {
                  this.receiver = null;
                  this.post = null;
                }}
              >
                {t('give.change')}
              </Button>
            </div>
          ) : (
            <div className="KudosGive-search">
              <input
                className="FormControl"
                type="search"
                autocomplete="off"
                placeholder={tx('give.search_placeholder')}
                aria-label={tx('give.search_placeholder')}
                value={this.query}
                oninput={(e: InputEvent) => this.search((e.target as HTMLInputElement).value)}
                onkeydown={(e: KeyboardEvent) => {
                  // The form can't submit while Send is disabled, so Enter is handled here: it picks the first match.
                  // A block, never `e.key === 'Enter' && …`: a handler returning false swallows every other key.
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    this.pickFirst();
                  }
                }}
              />
              {this.searching ? <LoadingIndicator size="small" display="inline" className="KudosGive-spinner" /> : null}
              {this.results.length ? (
                <ul className="KudosGive-results" role="listbox">
                  {this.results.map((p) => (
                    <li>
                      <button type="button" className="KudosGive-result" disabled={this.thanked(p)} onclick={() => this.pick(p)}>
                        <Avatar user={avatarUser(p)} />
                        <span className="KudosGive-resultName">{personName(p)}</span>
                        {p.displayName !== p.username ? <span className="KudosGive-resultUser">@{p.username}</span> : null}
                        {this.thanked(p) ? <span className="KudosGive-warn">{t('give.already')}</span> : null}
                      </button>
                    </li>
                  ))}
                </ul>
              ) : this.query.trim() && !this.searching ? (
                <p className="KudosGive-none">{t('give.no_match')}</p>
              ) : null}
            </div>
          )}
          {this.post ? (
            <div className="KudosGive-post">
              <Icon name="fas fa-link" />
              <span className="KudosGive-postTitle">{t('give.for_post', { title: this.post.title || '#' + this.post.number })}</span>
              <Button
                className="Button Button--icon Button--link"
                icon="fas fa-times"
                aria-label={tx('give.remove_post')}
                onclick={() => (this.post = null)}
              />
            </div>
          ) : null}
        </div>

        <div className="Form-group">
          <label className="KudosGive-label" for="kudos-note">
            {t('give.note')}
          </label>
          <textarea
            id="kudos-note"
            className="FormControl KudosGive-note"
            rows={3}
            maxlength={NOTE_MAX}
            placeholder={tx('give.note_placeholder')}
            value={this.note}
            oninput={(e: InputEvent) => (this.note = (e.target as HTMLTextAreaElement).value)}
          />
          <div className={'KudosGive-counter' + (left < 20 ? ' is-low' : '')}>{left}</div>
        </div>

        <div className="Form-group">
          <span className="KudosGive-label" id="kudos-design">
            {t('give.design')}
          </span>
          <div className="KudosGive-designs" role="radiogroup" aria-labelledby="kudos-design">
            {STYLES.map((s) => (
              <button
                type="button"
                role="radio"
                aria-checked={this.design === s ? 'true' : 'false'}
                aria-label={tx('designs.' + s)}
                title={tx('designs.' + s)}
                className={'KudosSwatch KudosCard--' + s + (this.design === s ? ' is-active' : '')}
                onclick={() => (this.design = s)}
              >
                <CardArt design={s} />
              </button>
            ))}
          </div>
        </div>

        <div className="Form-group KudosGive-footer">
          <span className="KudosGive-left">{t('give.left', { count: w.left, total: w.allowance })}</span>
          <Button
            type="submit"
            className="Button Button--primary KudosGive-send"
            icon="fas fa-paper-plane"
            loading={this.loading}
            disabled={!this.receiver || this.thanked(this.receiver) || this.note.trim().length < 3}
          >
            {t('give.submit')}
          </Button>
        </div>
      </div>
    );
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();
    if (!this.receiver) {
      this.pickFirst();
      return;
    }
    if (this.thanked(this.receiver) || this.note.trim().length < 3) return;

    this.loading = true;
    call<{ card: Card }>('POST', '', { receiver: this.receiver.id, note: this.note, post: this.post?.id || null, style: this.design }).then(
      (res) => {
        this.loading = false;
        app.alerts.show({ type: 'success' }, tx(res.card.pending ? 'give.sent_pending' : 'give.sent', { name: personName(this.receiver) }));
        this.hide();
        this.attrs.ongive?.();
      },
      () => {
        this.loading = false;
        m.redraw();
      }
    );
  }
}
