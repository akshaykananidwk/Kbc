# Installation guide

This guide assumes no technical knowledge beyond using your hosting control panel.

---

## 1. What you need

- A hosting account running **PHP 8.2 or newer** with Apache.
- A **MySQL 8** or **MariaDB 10.4+** database.
- The ability to upload files (cPanel File Manager, FTP, or SSH).

Most shared hosts let you pick the PHP version in cPanel under
**Select PHP Version** or **MultiPHP Manager**. Set it to 8.2 or higher before
you begin.

---

## 2. Upload the files

Upload the complete folder so that `index.php` sits at the location you want the
quiz show to answer from.

**Whole domain** — `https://quiz.example.com`
```
public_html/
├── index.php
├── .htaccess
├── app/
├── public/
└── …
```

**Sub-folder** — `https://example.com/quiz`
```
public_html/
└── quiz/
    ├── index.php
    ├── .htaccess
    └── …
```
Sub-folders work automatically; the application detects its own base path.

> **Make sure hidden files were uploaded.** `.htaccess` starts with a dot and some
> FTP clients hide it. In cPanel File Manager use *Settings → Show Hidden Files*.
> Without it, clean URLs will not work.

---

## 3. Set folder permissions

Two folders must be writable by PHP:

| Folder | Permission |
|---|---|
| `storage/` (and everything inside) | `755` — or `775` if your host runs PHP as a different user |
| `public/uploads/` (and everything inside) | `755` / `775` |

The project root also needs to be writable **once**, so the installer can create
`.env`. You can set it back to `755` afterwards.

In cPanel File Manager: right-click the folder → *Change Permissions* → tick the
boxes, then tick *Recurse into subdirectories*.

---

## 4. Create the database

In cPanel → **MySQL Databases**:

1. Create a database, e.g. `myaccount_ganpati`.
2. Create a user with a strong password.
3. Add the user to the database and grant **ALL PRIVILEGES**.
4. Write down the four values — you need them in step 5.

> On most shared hosts the database host is `localhost`. Some hosts use a
> different hostname; it is shown on the same cPanel page.

---

## 5. Run the installer

Open:

```
https://your-domain.com/install
```

### Step 1 — System check
Every requirement is checked. Green ticks mean you can continue. Anything red must
be fixed first — the page tells you exactly what. Items marked *optional* (such as
cURL) will not stop the install, but cURL is needed later for GitHub updates.

### Step 2 — Database
Enter the host, port, database name, username and password. The installer connects
before saving anything. If the database does not exist yet, tick
*Create the database if it does not exist* — this only works when your database
user has CREATE permission.

### Step 3 — Administrator
Your name, email, a username, and a password (at least 8 characters with a letter
and a number). This is the account you will sign in with.

### Step 4 — Website
Website name, timezone, language and currency symbol. Leave
*Install demo content* ticked for your first install — it gives you 14 sample
questions in Gujarati, Hindi and English, 5 gifts, 3 participants and a ready-made
10-step prize ladder so you can try the whole system immediately. You can remove it
later in one click from **Settings → Demo data**.

### Step 5 — Install
Review and press **Install now**. The installer:

1. writes `.env`,
2. creates all database tables,
3. inserts the default settings, prize ladder, lifelines and categories,
4. creates your administrator account,
5. optionally adds the demo content,
6. writes `storage/installed.lock`.

### Step 6 — Done
`/install` is now permanently locked. Sign in at `/admin/login`.

---

## 6. First things to do after installing

1. **Settings → General** — upload your logo, a Ganpati image and a favicon.
2. **Prize ladder** — set your real amounts and mark the guaranteed (safe) levels.
3. **Gifts** — add your prizes and attach them to levels.
4. **Lifelines** — enable the ones you want; configure the expert's name and photo.
5. **Questions** — add your own, or edit the demo ones. CSV import is under
   *Questions → Import CSV*.
6. **Participants** — add your contestants.
7. **Users** — create an *Operator* account for whoever runs the show, so they
   cannot change questions by accident.
8. **Backups** — take one before the event.

---

## 6b. Loading the ready-made question bank

200 Gujarati questions ship with the app, 40 in each of five categories:
ધાર્મિકતા, દેશભક્તિ, ભારતદર્શન, કરંટ અફેર્સ and જનરલ નોલેજ.

Over SSH:

```bash
php console.php seed --questions
```

No SSH? **Admin → Questions → Import**, and upload
`docs/gujarati-question-bank.csv` from the package. The same file opens in
Excel if you want to edit, add or translate questions first — keep the column
headings as they are and save as CSV UTF-8.

Then set **Admin → Settings → Game → Question order** to *Two from every
category (balanced)*: a ten-level ladder then asks two questions from each of
the five categories, in a different order every show.

Check the કરંટ અફેર્સ questions before each season — current affairs date.

---

## 7. Setting up the two monitors

1. Connect the second monitor / projector and set Windows or macOS to
   **Extend** the display.
2. Drag a browser window onto monitor 2, open `https://your-domain.com/display`
   and click **Full screen** (or press `F`).
3. On monitor 1, open `https://your-domain.com/operator`.

The display screen needs no sign-in, so a TV browser or a cheap stick PC can show
it. It is read-only and receives no confidential data.

**Test it before the event:** run one complete practice game end to end.

---

## 8. Troubleshooting

**"Page not found" on every URL except the home page**
`mod_rewrite` is off or `.htaccess` was not uploaded. Check hidden files were
uploaded; ask your host to enable `mod_rewrite`.

**A blank white page**
PHP hit a fatal error. Look in `storage/logs/` for today's log file, or view
**Admin → Logs**. Confirm your PHP version is 8.2+.

**"The .env file could not be written"**
The project root is not writable. Set it to `755` (or `775`), run the installer,
then set it back.

**"Could not connect to the database server"**
Check the host, username and password. On cPanel the database and user names
usually carry an account prefix, e.g. `myaccount_ganpati`.

**The installer says it is already installed**
That is deliberate. To genuinely reinstall, take a backup first, then delete
`storage/installed.lock` on the server.

**Music or a sound file will not upload**
Almost always PHP's own limit, which is 2 MB on stock hosting — smaller than
any song. **Admin → Settings → Sound** shows the limit actually in force on
your server; an oversized upload is refused with that number and an
explanation, so you can see it straight away.

To raise it, in order of what usually works:

1. **Admin → Settings → Sound** has a button that writes a `.user.ini` for you.
   This is the file PHP-FPM and CGI hosting read. PHP caches it for up to five
   minutes, so wait, then reload the page to see the new limit.
2. If the button says the host will not allow it, the same panel shows the exact
   text — create `.user.ini` in the application root over FTP
   (`docs/user.ini.example` in the package is the same file).
3. `.htaccess` already sets the values for mod_php hosting.
4. If your host ignores all of that, set `upload_max_filesize` and
   `post_max_size` in the control panel: cPanel → *MultiPHP INI Editor*, or
   *Select PHP Version* → *Options*. Plesk: *PHP Settings*.

`.user.ini` is deliberately **not** shipped inside the package: many hosts stop
PHP writing that file, and an updater that insists on writing it would fail
every update.

Nothing is broken while you sort that out: every sound in the show is generated
in the browser and works with no files at all. Music is the only thing that
needs an upload.

**Other uploads fail (photos, logos)**
`public/uploads/` is not writable, or the file is over the same PHP limit.

**"Create the game" does nothing, or is greyed out**
Either a game from an earlier show was never ended, or the unused questions ran
out. The setup screen now handles both: it names the open game and offers to end
it and start the new one, and it offers **Reuse questions if needed** for a
single game when the question bank is low. You never have to delete games or
participants to get going.

**Gujarati text shows as `?????`**
The database was not created as `utf8mb4`. Create a fresh `utf8mb4` /
`utf8mb4_unicode_ci` database and reinstall.

---

## 9. Going live checklist

- [ ] `APP_DEBUG=false` in `.env` (the installer sets this)
- [ ] HTTPS enabled on the domain
- [ ] `storage/installed.lock` exists
- [ ] A separate Operator account exists for the host
- [ ] Prize ladder, gifts and lifelines configured
- [ ] Enough active questions — at least one per prize level
- [ ] One full practice game completed
- [ ] A database backup taken
- [ ] The display screen tested full-screen on the actual TV
