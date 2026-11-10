import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';
import stringToColor from 'flarum/common/utils/stringToColor';

/** Shared by forum.js and the lazy chunk: translator shortcuts, the API call and the people shape. */

export const t = (key: string, params: Record<string, any> = {}) => app.translator.trans(`ernestdefoe-kudos.forum.${key}`, params);
export const tx = (key: string, params: Record<string, any> = {}) => extractText(t(key, params));

/** A member as the kudos endpoints send them. `hidden` = someone the viewer can't see. */
export interface Person {
  id: number | null;
  hidden?: boolean;
  username?: string;
  displayName?: string;
  slug?: string;
  avatarUrl?: string | null;
}

/** Just enough of a User model for core's Avatar component. */
export function avatarUser(p: Person | null): any {
  const name = p && !p.hidden ? p.displayName || p.username || '' : '';
  return {
    displayName: () => name || '?',
    username: () => p?.username || '',
    avatarUrl: () => (p && !p.hidden ? p.avatarUrl || null : null),
    color: () => (p?.username ? '#' + stringToColor(p.username) : 'var(--muted-color)'),
    id: () => (p?.id ? String(p.id) : '0'),
  };
}

export function personName(p: Person | null): string {
  if (!p) return tx('person.former');
  if (p.hidden) return tx('person.hidden');
  return p.displayName || p.username || '';
}

export function personUrl(p: Person | null): string | null {
  return p && !p.hidden && p.slug ? app.route('user', { username: p.slug }) : null;
}

export function errorText(error: any): string {
  const code = error?.response?.errors?.[0]?.code;
  const key = `ernestdefoe-kudos.forum.errors.${code}`;
  if (code && extractText(app.translator.trans(key)) !== key) return extractText(app.translator.trans(key));
  if (error?.status === 0) return extractText(app.translator.trans('core.lib.error.network_message'));
  if (error?.status === 403) return extractText(app.translator.trans('core.lib.error.permission_denied_message'));
  return tx('errors.generic');
}

export function call<T>(method: 'GET' | 'POST' | 'PATCH' | 'DELETE', path: string, data?: Record<string, any>, quiet = false): Promise<T> {
  const url = app.forum.attribute<string>('apiUrl') + '/kudos' + path;
  const query = method === 'GET' && data ? '?' + new URLSearchParams(Object.entries(data).map(([k, v]) => [k, String(v)])).toString() : '';
  return app.request<T>({
    method,
    url: url + query,
    body: method === 'GET' ? undefined : data,
    errorHandler: (error: any) => {
      if (!quiet) app.alerts.show({ type: 'error' }, errorText(error));
      throw error;
    },
  });
}

/** "Nov 9" (with the year when it isn't this one) for a week's first day. */
export function weekLabel(key: string): string {
  const [y, mo, d] = key.split('-').map(Number);
  const date = new Date(Date.UTC(y, mo - 1, d));
  const opts: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', timeZone: 'UTC' };
  if (y !== new Date().getFullYear()) opts.year = 'numeric';
  return date.toLocaleDateString(app.translator.getLocale?.() || undefined, opts);
}

/** Opens the give form (a lazy chunk) for someone, maybe for one of their posts. */
export function openGive(
  attrs: { receiver?: Person | null; post?: { id: number; number: number; title?: string } | null; ongive?: () => void } = {}
) {
  import('./components').then((ui) => app.modal.show(ui.GiveKudosModal, attrs));
}
