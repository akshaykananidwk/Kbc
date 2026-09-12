# Verification report

**Application:** Ganpati Bapa Quiz Show
**Version:** 1.1.0
**Date:** 13 September 2026

## Test environment

| | |
|---|---|
| PHP | 8.4.19 |
| Database | 10.11.14-MariaDB-0ubuntu0.24.04.1 |
| Web server | PHP built-in server driven through `server-router.php`, which reproduces the `.htaccess` deny and rewrite rules |
| Suite | `tests/verify.php` — re-runnable, nothing mocked |

Reproduce with:

```bash
php tests/verify.php https://your-domain.com admin@example.com YourPassword
```

## Result

**274 checks executed, 274 passed, 0 failed.**

Per-check output is in [`verification-results.md`](verification-results.md).

---

## Summary by module

| Module | Test | Result | Status |
|---|---|---|---|
| Installation | `/install` six-step wizard, fresh database | Tables created, admin created, `.env` written, installer locked | PASS |
| Installation | Requirements check | Correctly reports PHP, extensions, writable folders | PASS |
| Installation | Re-run protection | `/install` refuses once `installed.lock` exists | PASS |
| Installation | Schema | 23 tables, InnoDB, utf8mb4_unicode_ci, FKs and indexes | PASS |
| Login | Correct credentials | Signs in, redirects by role | PASS |
| Login | Wrong credentials | Rejected with a generic message | PASS |
| Login | Brute force | Locks the account after 5 attempts for 15 minutes | PASS |
| Login | Correct password while locked | Still refused | PASS |
| Logout | Session destroyed | Protected pages redirect to login again | PASS |
| Questions | CRUD | Create, edit, duplicate, delete, activate/deactivate | PASS |
| Questions | Used in a game | Deactivated instead of deleted, history preserved | PASS |
| Questions | CSV import / export | Round-trips with a UTF-8 BOM | PASS |
| Questions | Unicode | Gujarati, Hindi and English stored and returned unchanged | PASS |
| Participants | CRUD | Create, edit, delete; registration number auto-assigned | PASS |
| Prize ladder | CRUD | Add, edit, delete any number of levels; renumbering on delete | PASS |
| Prize ladder | Guaranteed levels | Flag stored and honoured | PASS |
| Gifts | CRUD | Create, edit, delete; stock tracked | PASS |
| Gifts | Assignment | Awarded on the correct level, stock decremented | PASS |
| Gifts | Returned on reset | Stock restored when a game is reset or stepped back | PASS |
| Lifelines | 50:50 | Removes exactly two options, always keeps the correct one | PASS |
| Lifelines | Single use | A second use is refused | PASS |
| Lifelines | Audience poll | Percentages total 100; removed options get 0% | PASS |
| Lifelines | Expert advice | Never suggests an option 50:50 removed | PASS |
| Timer | 30 second question | Counts down in real time, server-authoritative | PASS |
| Timer | Pause / resume | Paused timer does not drift | PASS |
| Timer | Reset | Restores the full duration | PASS |
| Timer | Expiry | Reaches TIME_UP with no client action at all | PASS |
| Answer | Selection | Can be changed before locking | PASS |
| Answer | Lock | Stops the timer; further changes refused | PASS |
| Answer | Override | Unlock requires a reason and is written to the audit log | PASS |
| Answer | Reveal order | Refused before locking when configured to require it | PASS |
| Answer | Correct | State CORRECT, prize credited | PASS |
| Answer | Wrong | State WRONG, game ends, guaranteed amount paid | PASS |
| Answer | Time up | Recorded as `timeout`, game ends | PASS |
| Prize | Calculation | Level amount credited on a correct answer | PASS |
| Prize | Guarantee | Banked only by answering the guaranteed level | PASS |
| Prize | Fallback | Wrong answer at level 6 pays the level-5 guarantee | PASS |
| Prize | Completion | Clearing the ladder pays the top prize | PASS |
| Display | Two-monitor sync | `state_version` advances on every action; both screens agree on level, question and state | PASS |
| Display | Long-poll | Returns promptly, no page refresh needed | PASS |
| **Display** | **Correct answer withheld** | **Present-and-null before the reveal; absent from the whole payload** | **PASS** |
| **Display** | **Explanation withheld** | **Null until the result is revealed** | **PASS** |
| **Display** | **No internal data** | **No `private` section, no credentials, no token, no `correct_option` string anywhere** | **PASS** |
| Display | Reveal | Correct answer and explanation released only after the operator reveals | PASS |
| Display | 50:50 | Removed options are `null` server-side, not merely hidden by CSS | PASS |
| Game | Reset | Clears answers, lifelines and questions; keeps the audit log; returns gift stock | PASS |
| Game | History | Every question, answer, lifeline and gift recorded and reportable | PASS |
| Database | Transactions | Forced failure during reveal rolls back the game row, answer and gift stock together | PASS |
| Database | Recovery | The game stays playable and reveals correctly once the fault is removed | PASS |
| Backup | Database | Pure-PHP dump, zipped, with a manifest | PASS |
| Backup | Restore | Rows restored; a safety backup is taken first | PASS |
| Backup | Unicode | Gujarati text survives backup and restore byte for byte | PASS |
| Backup | Files | Application archived; existing backups excluded | PASS |
| Update | GitHub check | Live API call returns version, commit, author, date and changed files | PASS |
| Update | Full update | Real update from GitHub completed in 2.5 s, 162 files | PASS |
| Update | Protected files | `.env`, uploads, storage and `installed.lock` untouched by a real update | PASS |
| Update | Auto migration | A migration pushed to GitHub was downloaded and executed automatically | PASS |
| Update | Migration history | Recorded with its batch number; not re-run | PASS |
| Update | Cache clear | Cache emptied; uploads and database untouched | PASS |
| Update | Package validation | Rejects a truncated download, a path-traversal archive and a foreign package | PASS |
| Rollback | Failure test | A deliberately failing migration rolled the update back automatically | PASS |
| Rollback | File restore | Modified files restored and files the failed update added were removed | PASS |
| Rollback | Partial DDL | The failed migration's `down()` cleaned up the table it had created | PASS |
| Rollback | Recording | Status `rolled_back` with the error message stored | PASS |
| Security | SQL injection | Injection payloads inert in search, filters and login; no time-based delay | PASS |
| Security | XSS | Stored payloads rendered as escaped text | PASS |
| Security | CSRF | Requests without a token refused, including on the installer | PASS |
| Security | Authentication | All admin and operator routes gated | PASS |
| Security | Authorisation | Operator role blocked from every admin page and admin API | PASS |
| Security | File upload | `.php`, `.php.jpg`, PHP-in-`.jpg` and GIF polyglots rejected; SVG sanitised | PASS |
| Security | Upload execution | `.php` inside uploads returns 403 | PASS |
| Security | Directory access | `/app`, `/config`, `/database`, `/storage`, `.env` all blocked | PASS |
| Security | Headers | `X-Content-Type-Options`, `X-Frame-Options`, CSP and `X-Robots-Tag` present | PASS |
| Security | Secrets | GitHub token encrypted, masked in the UI, absent from API responses and logs | PASS |
| Audit log | Coverage | Login, question changes, game creation, lock, reveal, override and reset recorded with IP | PASS |
| Reports | Generation | Game, question and prize reports render and export as CSV | PASS |
| Responsive | Markup | Viewport on all screens; 5 breakpoints; 16 px inputs on mobile; display scales in `vmin` | PASS |
| Sound settings | Inline upload | Every sound, music and image setting has a real upload button; an MP3 uploaded over AJAX is stored, served and removable | PASS |
| Sound settings | Upload hardening | A PHP file renamed to `.mp3` is refused on content, not extension | PASS |
| Sound engine | No files needed | Ten cues are synthesised with the Web Audio API; no audio file ships and none is required | PASS |
| Sound engine | Test button | Every audio setting has a **Test sound** button that plays the uploaded file, or the built-in tone when there is none | PASS |
| Question media | Display | Question image, audio and video reach the display payload and render | PASS |
| Certificates | Issue | A finished game produces serial `GQC-YYYY-NNNN` carrying the real final prize | PASS |
| Certificates | Reprint | The serial never changes; the print count increases | PASS |
| Certificates | Print layout | A4 landscape print stylesheet | PASS |
| Certificates | Rehearsal | A rehearsal game is refused a certificate | PASS |
| Hall of fame | Ranking | Winners listed with formatted prize amounts; rehearsals excluded | PASS |
| Sponsors | Idle screen | Sponsors and the leaderboard rotate on the display between games | PASS |
| QR codes | Generation | In-house encoder; version grows with the payload; decoded successfully by `zbarimg` | PASS |
| QR codes | Endpoints | `/qr?for=register` and `for=display` serve SVG; an unknown target is refused | PASS |
| Audience voting | Open | A six-character code is issued and reaches the display | PASS |
| Audience voting | Phone page | Opens on the short code and never contains the answer | PASS |
| Audience voting | One vote per phone | 15 phones counted once; changing a vote does not add one | PASS |
| Audience voting | Lifeline | The poll lifeline uses the real votes when they exist, and falls back to a simulated poll when nobody votes | PASS |
| Registration | Public form | A person registers themselves and receives a registration number | PASS |
| Registration | Duplicate | The same mobile returns the existing registration instead of a second row | PASS |
| Registration | Honeypot | A bot filling the hidden field is absorbed silently | PASS |
| Fastest Finger | Round setup | Contenders entered, each issued a four-character code | PASS |
| Fastest Finger | Secrecy | The correct order and per-contender right/wrong are absent while the round runs | PASS |
| Fastest Finger | Ranking | The fastest *correct* answer wins; a fast wrong answer never ranks | PASS |
| Fastest Finger | Access codes | An invalid code is refused | PASS |
| Interface language | Gujarati / Hindi | Operator and admin screens render translated; unknown keys degrade to English | PASS |
| Rehearsal mode | Isolation | No gift stock movement, no question statistics, hidden from history, reports and the hall of fame | PASS |
| Routing | Constrained parameters | Route constraints containing `{n,m}` quantifiers compile and match correctly | PASS |

---

## What was verified how

**Automated** (`tests/verify.php`, 274 assertions): everything in the table above
except where noted below. The suite drives the real HTTP application with real
cookies and CSRF tokens, and asserts against the live database.

**Live external integration:** the GitHub check, a complete update, automatic
migration and automatic rollback were each executed against the real
`github.com` API using this repository — not simulated. The rollback test used a
throwaway branch carrying a deliberately broken migration.

**Verified by inspection rather than execution:**

- **Apache behaviour.** Testing used PHP's built-in server with
  `server-router.php`, which reproduces the `.htaccess` deny rules (`/app`,
  `/config`, `/database`, `/storage`, dotfiles, uploads execution) and the
  rewrite-to-front-controller behaviour. The `.htaccess` files themselves were
  not executed by a real Apache instance. **Re-check on your host after
  uploading:** open `/app/Core/Database.php` and `/.env` and confirm both
  return 403.
- **Two physical monitors.** Synchronisation was proved at the data layer —
  `state_version` advances on every action, and the operator and display
  payloads agree on level, question and state — rather than by driving two
  browser windows.
- **Mobile devices.** Responsiveness was checked from the markup and CSS
  (viewport tags, breakpoints, touch target sizes, `vmin` scaling), not on
  physical phones or a real TV.
- **Sound playback.** The cue tones are generated in the browser with the Web
  Audio API, so the application makes sound with no files at all. The generator
  and the event wiring were verified in source, and uploaded music was verified
  end to end (stored, served in the display payload, removable) — but the tones
  themselves can only be *heard* in a browser, which a PHP test cannot do.
  Press the **Test sound** buttons in **Settings → Sound** once on your machine.
- **Phones in the hall.** Audience voting and Fastest Finger were driven over
  real HTTP with separate cookie jars and distinct voter tokens, which is what a
  room full of phones amounts to; they were not tested on physical handsets over
  Wi-Fi. Check your venue's Wi-Fi and the URL in the QR code before the event.
- **Printing.** The certificate page and its A4 landscape print stylesheet were
  verified in markup and CSS, not by printing on paper.

## Known behaviour worth knowing before your event

1. **Questions do not repeat across games by default.** With
   `repeat_questions` off, a question used in any past game is never served
   again, so a 10-level ladder needs 10 fresh questions per show. The dashboard
   and the operator setup screen show how many *unused* questions remain.
   Turn the setting on in **Settings → Game** if you would rather reuse them.
2. **MySQL cannot roll back DDL.** If a future migration fails halfway, the
   runner calls that migration's own `down()` to clean up. Migrations should
   therefore always implement `down()` properly.
3. **Live audience voting needs everyone on the same network.** The QR code
   contains the address the browser is using, so the phones must be able to
   reach that address. On a venue Wi-Fi set `app_url` in **Settings → General**
   to the machine's LAN address before printing or showing the code.
4. **Rehearsal games are kept, not discarded.** They are simply excluded from
   history, statistics, the hall of fame and certificates. Filter for them in
   **Admin → Reports → Games** if you want to review a practice run.
5. **The updater needs `curl` and `zip`.** Without them the rest of the
   application works normally; only the update manager is unavailable, and the
   Updates page says so.
