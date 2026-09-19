# Verification report

**Application:** Ganpati Bapa Quiz Show
**Version:** 1.8.0
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
node tests/layout.js https://your-domain.com admin@example.com YourPassword
```

The second suite drives a real Chromium browser, puts a question on air and
measures the rendered audience screen at six screen shapes. Playwright is a
development tool only — the application itself still has no Node dependency.

## Result

**444 checks executed, 444 passed, 0 failed**, plus **39 browser checks, all passed**.

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
| Display layout | Hidden panels | A panel the script hides leaves the layout entirely (display, operator and admin stylesheets) | PASS |
| Display layout | Screen fit | Every size derives from one measured unit; the screen scales itself between 0.55× and 1.45× | PASS |
| Display layout | 1080p TV, 4:3 projector, laptop, ultra-wide, 1280×600, 4K | Question, options, ladder and timer all render inside the screen, nothing clipped | PASS |
| Display layout | Timer | Fully visible on every screen shape tested, with a live game on air | PASS |
| Display layout | Prize ladder | Shrinks on its own so a long ladder never shrinks the question | PASS |
| Uploads | Server limit | The app reads PHP's real `upload_max_filesize`/`post_max_size` and never advertises more | PASS |
| Uploads | Real song | A multi-megabyte MP3 uploads, is stored, served and removable | PASS |
| Uploads | Oversized file | Refused with HTTP 413 and an explanation naming the settings to change — not a misleading "session expired" | PASS |
| Uploads | Admin guidance | Settings states the limit in force before anything is uploaded | PASS |
| New game | Open game left behind | Refusal names the blocking game and its participant; one confirmation ends it and creates the new game | PASS |
| New game | Audit | The automatic takeover is recorded as `game.replaced`; the old game is closed, never deleted | PASS |
| Question bank | Senior bank | A second bank of 200, 40 per category, sharing no question with the open bank and pitched harder (191 of 200 medium or hard) | PASS |
| Question bank | Replacement | Clearing removes every question and option and reports what went; participants, prizes and settings are untouched | PASS |
| Question bank | Guardrails | The replace screen warns what will go, refuses the wrong confirmation word, takes a database backup first and writes to the audit log | PASS |
| Question bank | Age fallback | A bank aimed entirely at seniors still lets a junior play, and the fallback is recorded in the game log | PASS |
| Show day | Age groups | A 14-year-old plays as a junior and a 35-year-old as a senior; the group can also be pinned by hand | PASS |
| Show day | Question targeting | A junior is never asked a question marked senior-only | PASS |
| Show day | No repeats today | Three shows in a row repeat nothing; the counter reports what is left for each group | PASS |
| Show day | One switch | The button on the setup screen turns the rule off and on, and the rest of the bank returns when it is off | PASS |
| Show day | પ્રશ્ન બદલી | Swaps in a different question at the same prize; the old one is remembered and cannot come back in that game; the display announces it | PASS |
| Show day | Three lifelines | Exactly 50:50, ફોન અ ફ્રેન્ડ and પ્રશ્ન બદલી are enabled, as the show promises | PASS |
| Show day | Entry gift | One click records the gift every entry is promised, and clicking again undoes it | PASS |
| Question bank | Content | 200 Gujarati questions, 40 in each of five categories; every row complete, unique and with an explanation | PASS |
| Question bank | Loading | Seeded into the database and folded into the five categories a balanced show uses | PASS |
| Question bank | Slugs | A Gujarati category name keeps the same slug, so re-saving never creates a duplicate category | PASS |
| Balanced order | Spread | Every game asks exactly two questions from each of the five categories | PASS |
| Balanced order | Variety | The order of the categories differs from show to show, but never changes inside one game | PASS |
| Lifelines | Audience Poll | Announces itself with a countdown and invents no percentages — the hall answers | PASS |
| Lifelines | Phone a Friend | Available, announces itself, counts down the call | PASS |
| Categories | Rotation control | The admin panel takes a category in or out of the balanced rotation, and the change takes effect | PASS |
| Lifelines | Secrecy | Neither announcement puts the answer on the display | PASS |
| Reveal | Wrong answer | The chosen option turns red and the correct one green on the board, measured in a real browser | PASS |
| Reveal | Naming the answer | The panel that follows names both answers in words, with the explanation | PASS |
| Reveal | Steadiness | Selecting, locking and revealing leave the screen at exactly the same scale | PASS |
| Reveal | Highlight | A highlighted option stays inside its column instead of being clipped by the prize ladder | PASS |
| Rotation | Full bank | Every question in a 12-question bank is served before any repeats | PASS |
| Rotation | Next round | Once the bank is exhausted, a new round starts rather than stalling | PASS |
| Rotation | Off | With rotation off the same questions return while others wait — the behaviour it replaces | PASS |
| Rotation | Bookkeeping | Each serve is recorded with its time; the operator screen reports bank size, unused count and round | PASS |
| Assets | Cache busting | Every stylesheet and script URL carries the file's timestamp, so an update reaches a browser that was told to cache it for a week | PASS |
| Assets | Change detection | Touching a file changes the URL browsers request | PASS |
| Version | Truthfulness | The version reported and shown on the display is the VERSION file on disk, so a stale setting from an earlier update cannot mislead | PASS |
| Display | Self-refresh | A screen left running reloads itself once the server reports a new build, and never in the middle of a running clock | PASS |
| Display | Mid-show re-fit | Advancing to a new question re-measures the screen instead of keeping the size it booted with | PASS |
| Updater | Locked-down host | A server-config file PHP may not write (`.htaccess`, `.user.ini`) is reported as a warning and the update still completes; the host's own file is left untouched | PASS |
| Updater | Genuine write failure | An application file that cannot be written still fails the update and names the folder to check | PASS |
| PHP limits | Self-service | Settings writes a `.user.ini` where the host allows it, and shows the exact text to upload where it does not | PASS |
| PHP limits | Packaging | `.user.ini` is never shipped in the package — only `docs/user.ini.example` — so no update can be blocked by it | PASS |
| New game | Exhausted question bank | Reported clearly, and a per-game "reuse questions" option gets the show on air without changing the global setting | PASS |

---

## What was verified how

**Automated** (`tests/verify.php`, 444 assertions; `tests/layout.js`, 39 browser assertions): everything in the table above
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
- **Mobile devices.** Phone and tablet responsiveness was checked from the
  markup and CSS (viewport tags, breakpoints, touch target sizes), not on
  physical handsets. The audience screen itself *was* measured in a real
  browser at six screen shapes, including a 4K TV and a 4:3 projector, but on
  a virtual display rather than the physical television.
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

1. **A show day runs out eventually.** With "no repeats today" on and 213 active
   questions, a ten-question show can run about twenty-one times before the day's
   bank is empty. The operator screen counts down for each age group, and when it
   does run out the message says exactly which switch to turn off.
2. **Current affairs go stale.** The 40 questions in કરંટ અફેર્સ are written from
   settled events. Read through that category before each season and refresh it —
   Admin → Questions, or edit `docs/gujarati-question-bank.csv` and import it.
3. **Balanced order needs the categories to be in rotation.** Admin → Categories
   decides which take part; the five that ship are in, the old sample ones are
   out. With five categories and ten levels that is two questions each; with a
   different count the ladder is simply dealt out in the same cycle.
4. **Rotation decides what comes next when reuse is on.** The least recently
   used questions are served first (Settings → Game → *Rotate through the
   question bank*), so a bank of 200 is fully used before anything returns.
   Questions pinned to a prize level are an exception by design: a pinned
   question is always served at its level, and rotation only chooses between
   several pinned to the same one.
5. **Questions do not repeat across games by default.** With
   `repeat_questions` off, a question used in any past game is never served
   again, so a 10-level ladder needs 10 fresh questions per show. The dashboard
   and the operator setup screen show how many *unused* questions remain.
   When they run low the setup screen now offers **Reuse questions if needed**
   for that one game, so a show is never blocked; turn the setting on in
   **Settings → Game** to make reuse the default.
6. **MySQL cannot roll back DDL.** If a future migration fails halfway, the
   runner calls that migration's own `down()` to clean up. Migrations should
   therefore always implement `down()` properly.
7. **Music needs the server to allow it.** PHP's stock limit is 2 MB, which is
   smaller than most songs. **Admin → Settings → Sound** shows the limit in
   force and, when it is low, offers to write a `.user.ini` asking for 64M
   (PHP-FPM and CGI hosting read that file; `.htaccess` covers mod_php). Where
   the host will not let PHP write it, the same panel shows the text to upload
   by hand. That file is deliberately not part of the update package: a host
   that blocks it would otherwise block every update.
8. **Live audience voting needs everyone on the same network.** The QR code
   contains the address the browser is using, so the phones must be able to
   reach that address. On a venue Wi-Fi set `app_url` in **Settings → General**
   to the machine's LAN address before printing or showing the code.
9. **Rehearsal games are kept, not discarded.** They are simply excluded from
   history, statistics, the hall of fame and certificates. Filter for them in
   **Admin → Reports → Games** if you want to review a practice run.
10. **After an update, reload the display once.** It now does this for itself —
   the screen notices the new build within a few seconds and reloads when no
   clock is running — but a manual reload is instant. Asset addresses change
   with every update, so no browser can serve a stale stylesheet any more.
11. **The updater needs `curl` and `zip`.** Without them the rest of the
   application works normally; only the update manager is unavailable, and the
   Updates page says so.
