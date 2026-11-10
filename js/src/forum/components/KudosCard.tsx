import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Avatar from 'flarum/common/components/Avatar';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import humanTime from 'flarum/common/helpers/humanTime';
import type Mithril from 'mithril';
import CardArt from './CardArt';
import type { Card } from './data';
import { avatarUser, call, personName, personUrl, t, tx, weekLabel } from '../util';

/**
 * One kudos card: who was thanked, the note, who from, and the post that
 * helped. A moderator gets approve / hide note / delete; a giver can take
 * back their own while it waits for approval.
 */

declare const m: Mithril.Static;

interface Attrs extends ComponentAttrs {
  card: Card;
  /** Small, no controls: the give form's live preview. */
  preview?: boolean;
  /** On the receiver's own profile: the giver heads the card instead of repeating the receiver's name. */
  received?: boolean;
  onchange?: (card: Card | null) => void;
}

function name(p: Card['giver'], className: string) {
  const url = personUrl(p);
  return url ? (
    <Link className={className} href={url}>
      {personName(p)}
    </Link>
  ) : (
    <span className={className}>{personName(p)}</span>
  );
}

export default class KudosCard extends Component<Attrs> {
  busy = false;

  act(action: 'approve' | 'hide_note' | 'restore_note' | 'delete') {
    const card = this.attrs.card;
    if (action === 'delete' && !confirm(tx(card.mine && card.pending ? 'card.withdraw_confirm' : 'card.delete_confirm'))) return;
    this.busy = true;
    const done = () => {
      this.busy = false;
      m.redraw();
    };
    if (action === 'delete') {
      call('DELETE', '/' + card.id).then(() => {
        done();
        this.attrs.onchange?.(null);
      }, done);
    } else {
      call<{ card: Card }>('PATCH', '/' + card.id, { action }).then((res) => {
        done();
        this.attrs.onchange?.(res.card);
      }, done);
    }
  }

  controls() {
    const card = this.attrs.card;
    const mod = app.forum.attribute('kudosCanModerate');
    const items: Mithril.Children[] = [];
    if (mod && card.pending)
      items.push(
        <Button icon="fas fa-check" onclick={() => this.act('approve')}>
          {t('card.approve')}
        </Button>
      );
    if (mod && !card.noteHidden)
      items.push(
        <Button icon="far fa-eye-slash" onclick={() => this.act('hide_note')}>
          {t('card.hide_note')}
        </Button>
      );
    if (mod && card.noteHidden)
      items.push(
        <Button icon="far fa-eye" onclick={() => this.act('restore_note')}>
          {t('card.restore_note')}
        </Button>
      );
    if (mod || (card.mine && card.pending))
      items.push(
        <Button icon="far fa-trash-alt" onclick={() => this.act('delete')}>
          {t(mod ? 'card.delete' : 'card.withdraw')}
        </Button>
      );
    if (!items.length) return null;

    return (
      <Dropdown
        className="KudosCard-controls"
        buttonClassName="Button Button--icon Button--flat KudosCard-controlsButton"
        menuClassName="Dropdown-menu--right"
        icon="fas fa-ellipsis-h"
        accessibleToggleLabel={tx('card.controls')}
        disabled={this.busy}
      >
        {items}
      </Dropdown>
    );
  }

  view() {
    const { card, preview, received } = this.attrs;
    const post = card.post;
    const head = received ? card.giver : card.receiver;

    return (
      <article
        id={preview ? undefined : 'kudos-' + card.id}
        className={['KudosCard', 'KudosCard--' + card.style, preview ? 'KudosCard--preview' : '', card.pending ? 'KudosCard--pending' : ''].join(' ')}
      >
        <div className="KudosCard-band">
          <CardArt design={card.style} />
        </div>
        <div className="KudosCard-body">
          <div className="KudosCard-to">
            <Avatar user={avatarUser(head)} className="KudosCard-avatar" />
            <div className="KudosCard-toText">
              <span className="KudosCard-label">{t(received ? 'card.thanks_from' : 'card.kudos_to')}</span>
              {name(head, 'KudosCard-name')}
            </div>
          </div>

          {card.note !== null ? (
            <p className={'KudosCard-note' + (card.noteHidden ? ' KudosCard-note--hiddenForOthers' : '')}>
              <span className="KudosCard-quote" aria-hidden="true">
                “
              </span>
              {card.note}
            </p>
          ) : (
            <p className="KudosCard-note KudosCard-note--removed">
              <Icon name="far fa-eye-slash" /> {t('card.note_removed')}
            </p>
          )}

          {post ? (
            <Link className="KudosCard-post" href={app.route('discussion.near', { id: post.slug || String(post.discussionId), near: post.number })}>
              <Icon name="fas fa-link" />
              <span className="KudosCard-postTitle">{t('card.for_post', { title: post.title })}</span>
            </Link>
          ) : null}

          <div className="KudosCard-from">
            {received ? (
              <span className="KudosCard-fromText">{t('card.week_of', { date: weekLabel(card.week) })}</span>
            ) : (
              [
                <Avatar user={avatarUser(card.giver)} className="KudosCard-avatar KudosCard-avatar--small" />,
                <span className="KudosCard-fromText">{t('card.from', { name: name(card.giver, 'KudosCard-fromName') })}</span>,
              ]
            )}
            {preview ? null : <span className="KudosCard-time">{humanTime(card.createdAt)}</span>}
          </div>

          {card.pending ? (
            <div className="KudosCard-flag">
              <Icon name="far fa-clock" /> {t('card.pending')}
            </div>
          ) : null}
          {card.noteHidden && card.note !== null ? (
            <div className="KudosCard-flag">
              <Icon name="far fa-eye-slash" /> {t('card.note_hidden_mod')}
            </div>
          ) : null}
        </div>
        {preview ? null : this.controls()}
      </article>
    );
  }
}
