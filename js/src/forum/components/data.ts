import type { Person } from '../util';

export interface Card {
  id: number;
  week: string;
  style: string;
  note: string | null;
  noteHidden: boolean;
  pending: boolean;
  mine: boolean;
  createdAt: string;
  giver: Person | null;
  receiver: Person | null;
  post: { id: number; number: number; discussionId: number; slug: string; title: string } | null;
}

export interface Wallet {
  allowance: number;
  used: number;
  left: number;
  givenTo: number[];
  resetsAt: string;
  canGive: boolean;
}

export interface Wall {
  cards: Card[];
  next: number | null;
  week: { key: string; start: string; end: string; current: boolean; currentKey: string; total: number };
  spotlight: { week: string; count: number; user: Person | null } | null;
  archive: { week: string; count: number; spotlight: Person | null }[];
  me: Wallet | null;
  queue: number | null;
  moderation: boolean;
}

export interface Profile {
  user: Person;
  cards: Card[];
  next: number | null;
  received: number;
  given: number;
  thisWeek: number;
  spotlights: { week: string; count: number }[];
  topGivers: { user: Person; count: number }[];
}

/** The card designs, in picker order (kept in step with Settings::STYLES). */
export const STYLES = ['sunrise', 'meadow', 'tide', 'confetti', 'starlight', 'letterpress'];

/** "3d 4h" / "5h 12m" / "8m" until an ISO time. */
export function until(iso: string): string {
  const s = Math.max(0, Math.round((Date.parse(iso) - Date.now()) / 1000));
  const d = Math.floor(s / 86400);
  const h = Math.floor((s % 86400) / 3600);
  const mi = Math.floor((s % 3600) / 60);
  if (d > 0) return `${d}d ${h}h`;
  if (h > 0) return `${h}h ${mi}m`;
  return `${Math.max(1, mi)}m`;
}
