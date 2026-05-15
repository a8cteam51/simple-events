# Simple Events – Event Dates Dirty-Flag Fix Plan

> **Scope:** Stop `isEditedPostDirty()` getting stuck at `true` after saving an event. That's the only issue being fixed. The 3× `/sync` calls, the random-hash regen, the chrome-agent's "duplicate child post" theory, and the autosave-after-publish noise are **out of scope** — they may improve as a side effect, but no change is being made specifically for them.

---

## Confirmed bug (reproduced in Playwright)

Replicated end-to-end in [tests/e2e/specs/editor/event-dates-reload.spec.js](tests/e2e/specs/editor/event-dates-reload.spec.js). On `trunk` today:

| Step | `isEditedPostDirty()` |
|---|---|
| Open new event, add date via GUI, click Publish, wait for snackbar | **false** |
| Trigger a second save (any way) | **true** |
| Reload the page | **true** still |

Reload then triggers the browser's "Leave site? Changes you may have made..." prompt. Saving again doesn't clear it. The post is permanently dirty in the editor's mind even though `post_content` and the child `se-event-date` rows on disk are correct.

---

## Root cause

There are **two** places that flip the dirty flag to true after the post is supposedly clean:

### 1. Post-save `setAttributes` inside the save subscription

In [src/blocks/event-info/index.js#L327-L367](src/blocks/event-info/index.js#L327-L367), the block subscribes to `core/editor`'s `isSavingPost`. On the rising edge, it fires a `/sync` REST call. The `.then` calls `setAttributes({ eventDates: savedDates.dates })` — both inside [`saveEventDatesOnPostSave` at L207-L213](src/blocks/event-info/index.js#L207-L213) **and** again inside the subscribe wrapper [`.then` at L340-L345](src/blocks/event-info/index.js#L340-L345).

Timing problem: the post PUT and the `/sync` POST run in parallel. The post PUT often resolves first. By the time `/sync` resolves and the `.then` runs, the post has already been persisted — and `setAttributes` fires *after* the post was saved. Gutenberg sees the attribute change → marks dirty.

`saveEventDates` also calls [`dateManagerInstance.refreshWithNewDates`](src/blocks/event-info/event-manager.js#L90-L109), which regenerates `hash` on each date because the server doesn't echo it. Every refresh produces a different random suffix → every post-save `setAttributes` writes a syntactically different array → guaranteed dirty.

### 2. Load-time `useEffect` that syncs dateManager → attributes

[src/blocks/event-info/index.js#L369-L385](src/blocks/event-info/index.js#L369-L385):

```js
useEffect(() => {
    if (dateManagerState?.getCurrentDates()?.dates) {
        const currentEventDates = attributes.eventDates || [];
        const newEventDates = dateManagerState.getCurrentDates().dates;
        const datesChanged = JSON.stringify(currentEventDates) !== JSON.stringify(newEventDates);
        if (datesChanged) {
            setAttributes({ eventDates: newEventDates });
        }
    }
}, [dateManagerState, refreshCounter, setAttributes, attributes.eventDates]);
```

On page load:
- `attributes.eventDates` comes from parsed `post_content` (frozen at last save).
- `dateManagerState` is freshly initialised from `GET /simple-events/event-dates/{id}` ([index.js:239-271](src/blocks/event-info/index.js#L239-L271)).
- The two **always** differ — at minimum because of the random-suffix hash regen, often because `post_content` was serialised before the IDs were known (first publish: serialised with `id: null/undefined`).
- `setAttributes` fires → post becomes dirty before the user has done anything.

This is why the dirty flag survives reload.

---

## The fix (intercept Save click → sync → save)

Two rejected alternatives:

1. **JS-only "three deletions" minimum-diff** — would leave `post_content` with `id: null` after first publish until the user re-edits. Breaks `?event_date_id=X` URL highlighting on multi-date events. The front-end render uses `$attributes['eventDates']` ([class-se-blocks.php:252-253](src/classes/class-se-blocks.php#L252-L253)), and `class-date-display-formatter.php` uses `$date['id']` to identify the active date (L250, L289, L316) and to build `<li id="se-event-date-list-item-{id}">` (L409, L594). A null id silently degrades active-date highlighting. Unacceptable.
2. **Sync-on-change (background sync)** — would persist dates to the DB the moment the user adds/edits one, before the user commits via Save. That breaks the implicit draft semantic: a user who abandons changes shouldn't have those changes silently persisted. Unacceptable.

The viable approach is to keep the save-time sync model but invert the order: instead of `savePost → /sync (in parallel) → late setAttributes (causes dirty leak)`, do `intercepted Save click → /sync → setAttributes → savePost`. The post PUT serialises `post_content` with the correct IDs in a single round, no race, no late attribute mutation.

### Behaviour after the fix

| Moment | What happens |
|---|---|
| User adds/edits a date | `dateManager.upsertDate` runs. **No network call.** Yellow "Unsaved Changes" banner appears as today. Nothing persists yet. |
| User clicks Save / Publish / Update | Click listener checks `dateManager.isDirty`. If dirty: `preventDefault()`, fire `/sync`, wait for response, `setAttributes({ eventDates: response.dates })`, then dispatch `savePost()` programmatically. If not dirty: click propagates, native Gutenberg save runs unchanged. |
| User abandons changes (closes tab) | Nothing was synced. DB is clean. |
| Reload after a successful save | `post_content` already has correct IDs from the single PUT → no diff, no dirty leak, no beforeunload prompt. |

### How the click interception is wired

A `useEffect` on the block's `edit()` function attaches a capture-phase click listener on `.editor-post-publish-button` (the Publish/Update button — shared across both the top-bar shortcut and the pre-publish panel confirm). The listener short-circuits Gutenberg's native handler when `dateManager.isDirty` is true and runs the sync-then-save sequence itself. When dateManager is clean (no pending edits), the listener does nothing and the native handler runs.

The listener also guards against:
- Already-in-flight syncs (re-uses the existing promise instead of firing a second one)
- Autosaves (`isAutosavingPost` true → ignore)
- Sync failures (shows the existing error snackbar at [index.js:143-151](src/blocks/event-info/index.js#L143-L151), does NOT dispatch `savePost()`, leaves dateManager dirty so the user can retry)

### Diff scope

| File | Change |
|---|---|
| `src/blocks/event-info/index.js` | Add the click-interception `useEffect` that attaches to `.editor-post-publish-button`. |
| `src/blocks/event-info/index.js` | Delete the existing save-subscribe `useEffect` at L327-L367 (the parallel `/sync` + double-`savePost` dance). Replaced by the click listener. |
| `src/blocks/event-info/index.js` | Delete the post-save `setAttributes` inside `saveEventDatesOnPostSave` at L198-L213. Not needed once the sync runs before save. |
| `src/blocks/event-info/index.js` | Delete the load-time sync `useEffect` at L369-L385. `post_content` already has correct IDs after the new save flow, so there's nothing to sync on mount. |

No `event-manager.js` changes. No PHP. No `block.json`. No REST routes. No background sync. SSR/front-end render output identical to today's working-state for a successfully-saved event.

### Edge cases

1. **User clicks Save while a previous `/sync` is still in flight** — the listener detects the existing in-flight promise and awaits it instead of firing a second sync.
2. **Pre-publish panel two-step flow ("Publish" → confirm "Publish")** — both buttons share the `.editor-post-publish-button` class, so a single listener covers both clicks.
3. **Autosave** — Gutenberg's autosaves dispatch `__experimentalRequestPostUpdateStart` with `isAutosavingPost` true. The click listener never fires for autosaves (no actual button click), so no interception. Autosave proceeds with whatever attributes are current; since dateManager isn't dirty after a successful save, the autosaved content matches the last user-committed state.
4. **`/sync` failure** — sync rejects → snackbar error → `savePost()` is NOT called → dateManager stays dirty → click listener will retry on the next Save click. User-visible: "Failed to save event dates. Please try again." Same wording the existing code already uses.
5. **Selector drift across Gutenberg versions** — `.editor-post-publish-button` is the stable Gutenberg class for the publish/update button. If a future Gutenberg renames it, the listener silently does nothing and the bug returns. We can fall back to a slot-fill-based approach (option 2 in the earlier analysis) if this happens, but it has not changed since Gutenberg 5.x.

### What this fix does NOT touch (deliberate, per scope)

- The 3× `/sync` POST loop on first publish today — gone for free as a side effect of deleting the old subscribe block.
- The post-publish autosave at +55s.
- Random hash regeneration in `refreshWithNewDates`. Cosmetic; doesn't affect dirty state once `post_content` is stable.

---

## Tests

The Playwright suite in [tests/e2e/](tests/e2e/) already locks the behaviour:

- [event-dates-reload.spec.js](tests/e2e/specs/editor/event-dates-reload.spec.js) — primary spec. Asserts:
  - No `beforeunload` dialog after publish + second save + reload.
  - `isEditedPostDirty()` is `false` after reload.
  - Fails today (beforeunload fires, dirty stuck true). Should go green after the three deletions.
- [event-dates-save.spec.js](tests/e2e/specs/editor/event-dates-save.spec.js) — asserts 1× `/sync`, 1× post PUT, 1 child date. The sync-count assertion is **out of the scope** but kept as-is to surface side-effects of the fix. If it goes green by accident, good; if it stays red, that's fine too — drop the assertion or mark it `.fixme` rather than expanding the fix.
- [event-dates-block.spec.js](tests/e2e/specs/editor/event-dates-block.spec.js) — sanity (block insertion, autodraft post id). Must stay green.
- [event-dates-regressions.spec.js](tests/e2e/specs/editor/event-dates-regressions.spec.js) — `Revert Changes does not fire a /sync` and `edit-and-save on a reloaded published post stays clean` must stay green. Three `.fixme` specs (multi-date publish, lock-while-syncing, second-Update clears dirty) are out of scope — leave them alone.

### Run

```bash
npm run test:e2e                            # full suite
npm run test:e2e -- event-dates-reload      # just the primary repro
SLOWMO=400 npm run test:e2e:headed -- event-dates-reload  # watch in a browser
```

CI workflow: [.github/workflows/e2e-tests.yml](.github/workflows/e2e-tests.yml). Boots wp-env, builds, installs Chromium, runs the suite.

---

## Open questions before touching code

1. **`grep` confirmation** — am I right that nothing in `src/` or any active dependency reads `block.attributes.eventDates` from `post_content` outside the editor block itself? I'll grep before editing; flag if you know of a consumer.
2. **PR strategy** — three deletions in one PR. Test scaffolding is already in a separate diff (current branch). Merge the test scaffolding first so trunk has the failing repro on record, then this fix PR flips it green?

---

## Out of scope (do NOT change as part of this fix)

- Auto-sync on date change (Approach A from the earlier plan)
- `lockPostSaving` integration
- PHP `hash` echo from `/sync`
- Orphan child-post cleanup migration
- Any change to `block.json`, REST routes, or PHP rendering
- The 3× sync POSTs and 2× post PUTs on first publish
- The post-publish autosave
