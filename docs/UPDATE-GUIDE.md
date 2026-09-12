# Update guide

Once configured, updating is two clicks. You never upload a ZIP again.

---

## 1. One-time setup

Go to **Admin → Updates** and fill in:

| Field | Example | Notes |
|---|---|---|
| Repository owner | `akshaykananidwk` | Your GitHub username or organisation |
| Repository name | `kbc` | Just the name, not the full URL |
| Branch | `main` | The branch releases are cut from |
| GitHub token | `ghp_…` | Only needed for a **private** repository |

Press **Save configuration**.

### Creating the token (private repositories only)

1. GitHub → **Settings → Developer settings → Personal access tokens →
   Fine-grained tokens → Generate new token**.
2. **Repository access:** *Only select repositories* → choose this one repository.
3. **Permissions:** *Repository permissions → Contents → **Read-only***.
   Nothing else. The updater never writes to GitHub.
4. Set an expiry you are comfortable with and generate the token.
5. Paste it into the field and save.

**How the token is protected**

- Encrypted with AES-256-GCM using `APP_KEY` from `.env` before it is stored.
- Shown in the admin panel only as a mask (`ghp_**************`).
- Never included in any API response — the update API explicitly strips it.
- Never written to a log file; the logger redacts any key containing
  `token`, `password`, `secret` or `key`.
- Sent only in an `Authorization` header over HTTPS, never in a URL.

Leaving the masked field untouched keeps the saved token. To remove it, tick
*Remove the stored token* and save.

---

## 2. Checking for an update

Press **Check for update**. The system asks GitHub for the newest commit on your
branch and shows:

- your current version and commit,
- the latest version and commit,
- the commit message, author and date,
- the list of changed files,
- release information when a release exists.

If you are up to date it says so and stops. **Nothing installs automatically** —
an update only ever happens because you pressed the button.

---

## 3. Installing the update

Press **Backup & update now** and confirm. Keep the page open; it can take a minute.

The sequence, with the status recorded at each step:

| Step | What happens |
|---|---|
| 1. Checking | Repository, branch and token are verified |
| 2. Backing up | Database backup **and** a snapshot of your application files |
| 3. Downloading | Package fetched from GitHub |
| 4. Validating | Rejects a truncated file, a package with unsafe paths, or one that is not this application |
| 5. Updating | Application files replaced — protected paths untouched |
| 6. Migrating | New database migrations run automatically and are recorded |
| 7. Clearing cache | Application, view and temporary caches cleared |
| 8. Completed | New version and commit recorded |

---

## 4. What is never overwritten

These paths are skipped by the updater, so your configuration and your event data
survive every update:

```
.env
.env.local
config/local.php
config.php
public/uploads      ← every photo, gift image and sound you uploaded
storage             ← logs, cache, backups and installed.lock
.htaccess.local
```

Edit the list in `config/updates.php` under `protected_paths` if your setup needs more.

---

## 5. If something goes wrong

The update rolls back automatically. Every file is restored from the snapshot taken
in step 2, the cache is cleared, and the run is recorded as **rolled back** with the
exact error.

The database backup is kept as well, under **Admin → Backups**, so you can restore
it manually if a migration caused a problem.

To roll a past update back by hand, open **Admin → Updates → Details** on that run.

---

## 6. The update log

**Admin → Updates** lists every run with its old and new version, commit SHA and
message, backup status, number of files changed, migration status, rollback status
and any error. Open **Details** for the full step-by-step log.

Statuses: `checking · downloading · backing_up · updating · migrating ·
clearing_cache · completed · failed · rolled_back`.

---

## 7. Updating from the command line

If you have SSH access:

```bash
php console.php update:check     # see whether an update is available
php console.php backup:database  # take a backup
php console.php migrate          # run pending migrations after a manual file update
php console.php cache:clear      # clear caches
```

---

## 8. Good practice

- **Never update during an event.** Update the day before, then run a practice game.
- **Take a manual backup first** even though the updater makes one.
- **Read the commit message** on the check screen before installing.
- **Use a read-only token** scoped to the single repository.
- After updating, open `/admin` and confirm the dashboard says
  *Installer locked: yes* and *Debug mode: off*.
