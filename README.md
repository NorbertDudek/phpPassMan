<div align="center">

<img src="images/logo.png" alt="phpPassMan logo" width="64">

# phpPassMan

**A self-hosted, multi-user password vault for teams — written in plain PHP and MySQL.**

[![CI](https://github.com/NorbertDudek/phpMyPassword/actions/workflows/ci.yml/badge.svg)](https://github.com/NorbertDudek/phpMyPassword/actions/workflows/ci.yml)
[![License: GPL v3](https://img.shields.io/badge/license-GPLv3-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg?logo=php&logoColor=white)](#requirements)
[![MySQL / MariaDB](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-supported-4479A1.svg?logo=mysql&logoColor=white)](#requirements)
[![Languages](https://img.shields.io/badge/i18n-EN%20%7C%20PL-lightgrey.svg)](#languages)

[Features](#features) · [Installation](#installation) · [Security](#security-model) · [Upgrading](#upgrading) · [Support](#support-the-project)

</div>

---

phpPassMan keeps your company's passwords on **your own server**. Entries are organised into folders with fine-grained permissions for users and user groups, every change and view is recorded in an audit log, and a single entry can be shared with someone outside the organisation through an expiring **SecureSend** link.

It is free and open source — no user limits, no paid tiers, no feature restrictions.

## Features

**Vault**
- Password entries with login, password, URL, notes, tags and expiry date
- Custom fields defined per entry (name + value) and file attachments
- Folders (password groups) with nested structure, icons and instant search
- Password history — previous passwords of an entry stay available for reference
- CSV / XLSX import and CSV export

**Password policies and generator**
- Per-folder complexity requirements and password renewal period
- Built-in generator with three modes: *Random*, *Melodic* (pronounceable syllables) and *Passphrase* (BIP39 word list)
- Live password strength indicator

**Teams and permissions**
- Users, user groups and folder-level permissions shared with individual users or whole groups
- Explicit deny rules for users and groups that override inherited access
- Administrator role and a local superuser for initial setup
- Login and password-reset links sent by e-mail; forced password change on next login

**SecureSend — share with people outside your team**
- Share a whole entry (including custom fields and attachments) via a one-off link
- Expiry time, view limit and optional link password
- The recipient sees the data only after clicking *Show*
- The shared copy is encrypted like the entry itself and is deleted when the link expires or is revoked
- Active links are listed in the entry panel and can be revoked at any time

**Audit and operations**
- Audit log of every change, view and share — including recipients opening SecureSend links
- Per-entry history visible to everyone with access; global log (filter + CSV export) for administrators
- Encrypted database backups — on demand or on a schedule via cron — with download and restore from the admin panel
- SMTP configuration with a test message, configurable attachment size limit and history retention

> [!NOTE]
> phpPassMan has no browser extension. It is a web application you open in any modern browser.

## Requirements

| Component | Version / module |
|-----------|------------------|
| PHP | 7.4 or newer (tested up to 8.3) |
| PHP extensions | `pdo_mysql`, `openssl`, `mbstring`, `intl`, `gettext`, `zip`, `zlib` |
| Password hashing | PHP built with Argon2 support (`PASSWORD_ARGON2ID`) |
| Database | MySQL or MariaDB |
| Web server | Apache (rules shipped in `.htaccess`) or nginx (see below) |
| HTTPS | Strongly recommended — required for production use |

## Installation

1. **Get the code** — clone the repository or download the latest release into a folder served by your web server:
   ```bash
   git clone https://github.com/NorbertDudek/phpMyPassword.git passman
   ```

2. **Create a database and a database user** (the installer does not create them):
   ```sql
   CREATE DATABASE passman;
   CREATE USER 'passman'@'localhost' IDENTIFIED BY 'a-strong-password';
   GRANT ALL ON passman.* TO 'passman'@'localhost';
   ```

3. **Configure** — copy `resources/config.orig.php` to `resources/config.php` and fill in:
   - `$db_host`, `$db_name`, `$db_user`, `$db_pass`
   - `$secret_key` — a long random value, for example:
     ```bash
     php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
     ```
   - `$superuser` / `$superuser_password` — the initial admin account; store a hash instead of plain text:
     ```bash
     php -r "echo password_hash('your password', PASSWORD_DEFAULT), PHP_EOL;"
     ```
   - `$require_https = true;` on any server reachable from the network

   > [!WARNING]
   > **Never change `$secret_key` after data has been stored** and keep a copy of it somewhere safe outside the server. Every entry and every backup is encrypted with it — without the key nothing can be recovered.

4. **Create the tables** — open `https://your-server/passman/setup/init.php` in a browser.

5. **Log in** with the superuser account, then create real user accounts, user groups and folders. Use the superuser only for administration.

6. **Lock down the installer** — delete the `setup/` folder or deny access to it on the web server.

7. **Optional: scheduled backups** — set the schedule in *Admin → Backup* and add a cron job:
   ```cron
   */15 * * * * php /var/www/passman/backup_cron.php
   ```
   `$backup_dir` in `config.php` lets you keep backups outside the web root (recommended).

8. **Optional: e-mail** — enter SMTP settings in *Admin → Settings* and send a test message. E-mail is needed for login links, password resets and SecureSend notifications.

### nginx

The `.htaccess` files are ignored by nginx. Add equivalent rules to your server block:

```nginx
location ~ ^/passman/resources/config\.php { deny all; }
location ^~ /passman/backups/ { deny all; }
location ^~ /passman/setup/   { deny all; }   # after installation
```

## Upgrading

1. Make a backup (*Admin → Backup*) and keep a copy of `resources/config.php`.
2. Replace the application files with the new version — do not overwrite `resources/config.php`.
3. Open `setup/init.php` once more. It is safe to run on an existing database: it creates missing tables and adds missing columns.
4. Lock down `setup/` again.

## Security model

- **Entry encryption** — logins, passwords, notes, custom-field values and attachment names are encrypted with **AES-256-GCM** using a fresh random IV on every save. Encryption keys are derived from `$secret_key` (HMAC-SHA256), which lives in `config.php`, never in the database.
- **Account passwords** are hashed with **Argon2id**.
- **SecureSend** stores only a SHA-256 hash of the link token; the shared snapshot is encrypted and removed when the link expires, reaches its view limit or is revoked. Link passwords are hashed and failed attempts are counted.
- **CSRF tokens** protect every form and AJAX request; idle sessions expire after `$session_timeout`.
- **Backups** are encrypted with the same secret key.
- **Audit trail** — views, edits, shares and SecureSend openings are logged with user, time and IP address.

Found a vulnerability? Please report it privately — see [SECURITY.md](SECURITY.md).

## Languages

The interface is available in **English** and **Polish** and follows the browser language automatically (gettext, `locale/`). Translations of other languages are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).

## Contributing

Bug reports, ideas and pull requests are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before you start.

## Support the project

phpPassMan is free. If it saves you time, you can support its development:

- [PayPal](https://paypal.me/norbertdudekpoland)
- [Buy Me a Coffee](https://buymeacoffee.com/norbertdudek)

Need custom features, priority support or help with deployment? Contact [KANDISoft](https://kandisoft.pl).

## License

phpPassMan is released under the [GNU General Public License v3.0](LICENSE).
It started as a fork of [phpMyVault](https://github.com/search?q=phpMyVault&type=repositories) and has since been substantially rewritten and extended.
