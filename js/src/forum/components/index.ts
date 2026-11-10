/*
 * The one lazy chunk: the wall, the profile tab, the cards and the give form.
 * forum.js only wires things up; everything here loads the first time it's
 * needed. One entry on purpose: two chunks sharing the card would make
 * webpack emit a numbered shared chunk that jsDirectory doesn't publish.
 */
export { default as WallPage, loadCore } from './WallPage';
export { default as KudosUserPage } from './KudosUserPage';
export { default as GiveKudosModal } from './GiveKudosModal';
