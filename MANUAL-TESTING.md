# Manual Testing — Event Dates Dirty-State Fix & Migration Version Stamp

End-user QA steps for the two fixes in this branch. Follow in a browser; check each ✅ expectation.

## Prerequisites

1. **Build the JS** (the dirty-flag fix lives in compiled `build/`):
   ```bash
   npm run build
   ```
   The PHP fix loads automatically — no build needed for that.
2. **Environment**: wp-env site is `http://localhost:8888` (login `admin` / `password`). If testing on devilbox instead, use that URL — behaviour is identical.

---

## Fix #1 — Dirty-state leak (event-info block)

### Test A — First publish + reload (the core repro)

1. **Events → Add New**. The Event Information block loads in edit mode.
2. Click **Add Date**, set a start/end time, click **Done**.
3. Type a post title.
4. Click **Publish**, confirm **Publish** in the panel.
5. Wait for the **"Event dates synced"** snackbar.
6. ✅ **Expect:** the top-bar button shows greyed-out **"Saved"** — *not* an active "Save"/"Update".
7. Reload the page (F5 / Cmd-R).
8. ✅ **Expect:** **no** "Leave site? Changes you made may not be saved" browser prompt.
9. ✅ **Expect:** after reload, the date is still shown and the post is not dirty.

### Test B — Edit + save again (the "always dirty" repro)

1. Open the event from Test A.
2. Change the date's time (or Add Date again), click **Done**.
3. Click **Update**, wait for the snackbar.
4. ✅ **Expect:** button greys out, stays clean.
5. Reload → ✅ no "Leave site?" prompt.
6. *(Optional, DevTools → Network)*: per save click you should see exactly **one** POST to `…/event-dates/{id}/sync` and **one** to `…/wp/v2/se-event/{id}` — not 3× and 2× like before.

### Test C — Abandon changes (draft semantic must still hold)

1. **Events → Add New**, **Add Date**, **Done** — but **do NOT save**.
2. Close the tab / navigate away.
3. ✅ **Expect:** nothing persisted — no stray child date created. Verify via the meta command below against any new auto-draft, or confirm no orphan event-date posts appear.

---

## Fix #2 — Migration version stamping

### Test D — New event is not flagged for migration

1. Before: note whether the plugin currently shows an "events need migrating" admin notice.
2. **Events → Add New** → add a title → **Publish** (you can skip adding a date entirely).
3. ✅ **Expect:** this new event does **not** appear in / trigger the migration notice.

### Test E — Save with NO date interaction (the original intermittent bug)

1. **Events → Add New** → set **only a title**, do **not** add a date (so `/sync` never fires) → **Publish**.
2. ✅ **Expect:** the event still gets stamped and is **not** flagged for migration — this is the exact case that used to slip through.

### Verify the meta directly

For any event ID, check the stamped version:

```bash
npx wp-env run cli wp post meta get <EVENT_ID> se_event_version
```

✅ **Expect:** prints `2.0.0` (the current `SE_MIGRATION_VERSION`) for any newly created event — including ones where you never added a date.

### Test F — Legacy events still migrate (regression guard)

Hard to stage by hand (needs a genuine pre-2.0 event with no version meta). Covered by the automated test `EventMigrationTest` ("A genuinely legacy event…"). If you have a real legacy event: it should still show in the migration queue and migrate normally when run — the new stamp only affects events created *after* this fix.

---

If anything doesn't match the ✅ expectations, note the step number and what you saw.
