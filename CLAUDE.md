# Kudos Wall: working rules

- **Commit straight to `main` and push. Never open a pull request on this repo.** Commit subjects are conventional (`feat:`, `fix:`); the release drafter bumps from the subject.
- `js/dist` is committed and is what forums load. After any change under `js/src`, run `npm run build` in `js/` (from a fresh `npm ci`) and commit the bundle; CI fails if it is stale.
- Publishing a release is Ernest's click. Nothing is announced until he says so. Every change goes to the Command Center session for review before release.

## Design rules that exist for a reason

- **A give is judged in `Giving` under the giver's `kudos_givers` row lock** (SELECT … FOR UPDATE): the week's count and "already thanked them" are read inside the transaction. The row is created with INSERT IGNORE *before* the transaction: inside it, two racing gives each take a shared lock and deadlock. The unique (giver, receiver, week) key backs it up.
- **The allowance is counted from the week's rows**, never stored: deleting a kudos gives it back by itself. A member's allowance is the base or the most any of their groups gets (`group_allowances` JSON), clamped 1–50.
- **Weeks** (`Week`) start on the admin's day at midnight in `Settings::timezone()`: Kudos's own setting, else the forum's (Festoon / Fanfare / Logo Manager seasons, read by setting key; core has no timezone), else `app.timezone`, else UTC. Never hardcode a zone and are stored by their first day (`kudos.week`, a DATE with **no Eloquent date cast**: SQLite would store a time and the week would stop matching).
- 🚨 **Privacy**: every endpoint goes through `Api\Handler` → `Access::assertView` (viewForum AND kudos.view). People come through `Presenter::prime` (one `whereVisibleTo` query per page); someone the viewer can't see is `{hidden: true}`. Linked posts are loaded with `whereVisibleTo`. Pending kudos: giver and moderators only (`Kudos::scopeVisibleTo`). Hidden notes are null for everyone but moderators, in the Presenter, `KudosResource` and the email.
- **Notification subjects**: `kudosReceived` → the Kudos (has the `kudos` resource AND a forum store model; without either the alert list breaks), `kudosSpotlight` → the User, with the week in the data (one per week).
- **The spotlight** (`Spotlights::run`, hourly via `kudos:spotlight`) only judges the week that just ended. The week is a unique key; only the run whose insert lands sets it; rewards are claimed by a conditional update of `rewarded_at` and unclaimed on failure. The group is worn for one week (`expireGroups` takes it back once a newer week is judged; never from someone who was in it before). Built-in groups are never used. FoF Badges is touched only after `isEnabled('fof-badges')` + `class_exists`, only badges without a trigger.
- The index banner is a forum attribute (`ForumSpotlight`): the spotlight is cached (`Spotlights::latest`), plus one indexed `whereVisibleTo` query only while a banner is due.
- 🚨 Migrations never name indexes or foreign keys (Laravel's generated names carry the prefix); `MigrationTest` checks. extend.php uses string literals for settings keys, never `Settings::` constants.
- Frontend: forum.js only wires things up (routes, links, post/user menu items, alerts, the banner); the Wall, the profile tab, the cards and the give form are ONE lazy chunk with namespaced chunk ids. `PostControls`/`UserControls` are plain objects: extend them directly, not by module name.
- The give form's search handles Enter itself (the form can't submit while Send is disabled), in a block: never `onkeydown={(e) => e.key === 'X' && …}`.
- Card colours are each design's own (`less/cards.less`), light and dark; page text uses Flarum's CSS properties; link rules start with `html:not(#x-hi)` to beat themes.

## Testing

- `composer test:setup && composer test` (integration; `DB_DRIVER=mysql` + a `DB_PREFIX` to test prefixes), `composer analyse:phpstan`.
- `cd js && npm ci && npm run build && npm run format-check`.
- Local forum: ~/Herd/kudosdev (https://kudosdev.test, prefix `kd_`). Seed, screenshots, e2e, race test and deploy: ~/github/scopes/kudos (`seed.php`, `shots.mjs`, `e2e.mjs`, `race.sh`, `deploy.sh dev|showcase`). Showcase seed: `~/github/showcase/seeds/99-kudos.php`.
