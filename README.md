# ગણપતિ બાપા ક્વિઝ શો — Ganpati Bapa Quiz Show

A complete, production-ready web application for running a live Ganpati Bapa themed
quiz show from two monitors: an **operator control screen** and an **audience /
TV display screen**, kept in step by a server-authoritative game engine.

Built with **PHP 8.2+, Apache and MySQL/MariaDB**. No Composer, no Node.js, no build
step — upload the folder to any shared host, open `/install`, and you are running.

---

## What it does

| Screen | URL | Who sees it |
|---|---|---|
| **Operator control** | `/operator` | The host, on monitor 1 — question, options, **correct answer**, timer, lifelines, lock/reveal, prize ladder |
| **Audience display** | `/display` | The crowd, on monitor 2 / projector — festive presentation only, **never the correct answer before the reveal** |
| **Admin panel** | `/admin` | Questions, participants, prize ladder, gifts, lifelines, settings, reports, backups, updates |
| **Installer** | `/install` | One-time setup wizard, then permanently locked |
| **Audience voting** | `/vote/CODE` | The crowd, on their own phones — reached by scanning the QR code on the TV |
| **Self registration** | `/register` | Anyone in the hall who wants to play — QR code on the TV |
| **Fastest Finger** | `/fff/ID` | Shortlisted contenders, on their phones |

The two screens stay synchronised through a lightweight long-poll against
`/api/display/state`. No page refresh is ever needed during a show.

---

## Highlights

- **Server-authoritative game state.** Every decision — timer, lock, reveal, prize,
  guaranteed level — is made and stored on the server. Browser JavaScript only renders.
- **The display screen is security-critical and treated as such.** The correct answer,
  the explanation and all internal data are *absent from the JSON payload* until the
  operator reveals the result. Verified by automated test.
- **Nothing hard-coded.** Prize ladder length and amounts, guaranteed (safe) levels,
  gifts, lifelines, timers, theme colours, currency, sounds and copy are all editable
  in the admin panel.
- **Configurable lifelines** — 50:50, Audience Poll (realistic or manually fixed
  percentages), Expert Advice (name, photo, confidence), and an optional Skip Question.
- **Transaction safety.** Result + prize + gift stock + game status move together or
  not at all.
- **One-click GitHub updates** with automatic backup, protected paths, database
  migration, cache clear and automatic rollback on failure.
- **Fits any screen, by itself.** Every size on the audience screen is a multiple of one
  unit that the screen measures and sets for itself, so a 4K TV, a 4:3 projector and a
  short laptop panel all show the whole question, all four options, the full prize
  ladder and the complete timer — nothing cut off, nothing to configure.
- **Sound that just works.** Every cue (question, timer, lock, right, wrong, win) is
  synthesised in the browser with the Web Audio API, so the app makes sound out of the
  box with no audio files and no licensing worries. Upload your own intro song,
  background music or individual cues in **Admin → Settings → Sound** whenever you want
  to — each one has a real upload button, a player, a **Test sound** button and a
  remove button.
- **Live audience poll from real phones.** The Audience Poll lifeline puts a QR code on
  the TV; the crowd votes from their own phones and the bars show the real result. One
  vote per phone, and it quietly falls back to a simulated poll if nobody votes.
- **Fastest Finger First** round to pick the next contestant, ranked by time among the
  correct answers only. The answer never leaves the server while the round is running.
- **Winner certificates** (A4 landscape, print-ready) with a stable serial number, a
  **hall of fame**, **sponsor slides** and a **cheque presentation** animation.
- **Rehearsal mode.** Practise a full show without touching gift stock, question
  statistics, the leaderboard or the reports.
- **Gujarati, Hindi and English** throughout, stored as `utf8mb4` — including the admin
  and operator interface itself (**Admin → Settings → Interface language**).

---

## Requirements

| | Minimum |
|---|---|
| PHP | 8.2 or newer |
| Extensions | `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`, `zip`, `session` |
| Optional | `curl` (GitHub updates), `gd` (image handling) |
| Database | MySQL 8 or MariaDB 10.4+ |
| Server | Apache with `mod_rewrite` (works without it too) |

---

## Installation (5 minutes, no file editing)

1. Upload every file to your hosting account — usually into `public_html/`.
2. Create an empty MySQL database and a user with full rights on it.
3. Make sure `storage/` and `public/uploads/` are writable (chmod 755, or 775 on some hosts).
4. Open **`https://your-domain.com/install`** and follow the six steps.
5. The installer writes `.env`, creates the tables, seeds defaults, makes your admin
   account, and locks itself.

Full details, including sub-folder installs and troubleshooting, are in
[`docs/INSTALLATION.md`](docs/INSTALLATION.md).

---

## Running a show

0. **Admin → Settings → Sound** — check the upload limit shown there. Songs are
   several megabytes and stock hosting often allows only 2 MB; if it is low, the page
   offers a button that writes a `.user.ini` asking for 64 MB, and shows the text to
   upload by hand when the host will not allow that. Every sound works with no file
   at all if you would rather not upload any.
1. **Admin → Prize ladder** — set the amounts, mark the guaranteed (safe) levels,
   attach gifts.
2. **Admin → Questions** — add questions, or import a CSV. Pin questions to a level
   if you want a fixed running order.
3. **Admin → Participants** — add the contestants.
4. Open **`/display`** on monitor 2 and press **Full screen**.
5. On monitor 1, go to **`/operator/setup`**, pick the participant, create the game.
6. Run the show from **`/operator`**:

```
Start game → Start timer → (participant answers) → click A/B/C/D
   → Lock answer → Reveal result → Next question → …
```

Keyboard shortcuts: `A B C D` select, `Space` start timer, `P` pause,
`L` lock, `R` reveal, `N` next.

If a game from an earlier show was never ended, the setup screen says so and offers
to end it and start the new one — you are never left with a dead button. The same
screen can allow question reuse for a single game when the unused questions run low,
without changing the setting for future shows.

**Before the real show**, tick *Rehearsal* when creating the game. Everything behaves
exactly as it will on the night, but nothing is recorded: gift stock, question
statistics, reports and the hall of fame stay untouched.

**During the show you can also:**

- Open **Audience voting** while a question is up — the TV shows a QR code, the crowd
  votes from their phones, and the poll lifeline then uses those real votes.
- Run a **Fastest Finger First** round from `/operator/fff` to choose who plays next.
  Each contender gets a four-character code for their phone.
- Print the winner's **certificate** from `/admin/certificates` the moment the game ends.
- Leave `/display` on between games — it rotates the hall of fame, your sponsors and
  the registration QR code by itself.

---

## Project layout

```
/app
  /Controllers   Admin, Api, Web and Install controllers
  /Core          Router, Request/Response, Database (PDO), View, Session, Csrf, Validator
  /Middleware    Auth, Role, CSRF, throttle, installed, no-index
  /Repositories  Query objects, one per aggregate
  /Services      GameService (the engine), AudiencePoll, FastestFinger, Certificate,
                 Leaderboard, Auth, Settings, Backup, Update, Migration, Audit
  /Support       Str, Money, Crypto, Uploader, Csv, QrCode, helpers
/config          app, database, game, updates
/database        /migrations (versioned, tracked)  /seeders
/public          /assets (css, js)  /uploads (never executable)
/resources/lang  en, gu, hi interface translations
/resources/views admin, operator, display, public, install, errors, layouts, partials
/routes          web.php, api.php
/storage         logs, cache, backups, tmp  (deny-all, outside the web path)
index.php        front controller      console.php  CLI maintenance
```

---

## Command line helper

```bash
php console.php migrate           # run pending migrations
php console.php migrate:status    # what has and has not run
php console.php seed --demo       # seed defaults (and demo content)
php console.php backup:database   # create a database backup
php console.php cache:clear       # clear application cache
php console.php update:check      # ask GitHub about a newer version
php console.php db:check          # verify the database connection
```

Two test suites ship with the app:

```bash
php tests/verify.php https://your-domain.com admin@example.com YourPassword
node tests/layout.js https://your-domain.com   # needs Playwright; checks the TV screen fits
```

---

## Security

Implemented and tested — see [`docs/VERIFICATION-REPORT.md`](docs/VERIFICATION-REPORT.md):

PDO prepared statements everywhere · bcrypt (cost 12) password hashing ·
CSRF tokens on every state-changing request · output escaping on every template ·
login rate limiting and account lockout · session regeneration and idle timeout ·
role-based authorisation · hardened uploads (extension + MIME + content + SVG
sanitising, randomised names, non-executable directory) · security headers and CSP ·
audit logging · encrypted GitHub token · errors logged, never displayed.

---

## Updating

Configure **Admin → Updates** once with your GitHub owner, repository, branch and a
read-only token. After that, **Check for update → Backup & update now** does everything:
backup, download, verify, replace (protected paths untouched), migrate, clear cache —
and rolls back automatically if any step fails.

See [`docs/UPDATE-GUIDE.md`](docs/UPDATE-GUIDE.md).

---

## Licence and audio

The application code is yours to use for your event. **Do not upload copyrighted
quiz-show music.** All sound effects are configured by you in Admin → Settings →
Sounds; ship only original or properly licensed audio. The visual design is an
original Ganpati Bapa identity and does not copy any broadcast show's artwork.
