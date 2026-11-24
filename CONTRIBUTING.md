# Contributing to phpPassMan

Thanks for your interest in improving phpPassMan! Bug reports, translations, documentation fixes and code contributions are all welcome.

## Reporting bugs and ideas

- Search [existing issues](https://github.com/NorbertDudek/phpPassMan/issues) first.
- Use the issue templates. **Never paste passwords, `config.php` or real vault data.**
- Security problems go through a private advisory — see [SECURITY.md](SECURITY.md).

## Development setup

1. PHP 7.4+ with the extensions listed in the README, and MySQL or MariaDB.
2. Clone the repository and copy `resources/config.orig.php` to `resources/config.php` (it is git-ignored).
3. Start a local server, e.g. `php -S localhost:8000`, and open `http://localhost:8000/setup/init.php` to create the tables.

## Code guidelines

- Plain PHP without a framework; keep the existing style (tabs, `include` of `header.php` / `footer.php`).
- Every form and state-changing request must carry the CSRF token (`resources/js/csrf.js`).
- Escape output with `htmlspecialchars()`; use prepared statements for every query.
- Sensitive values are stored encrypted — use the existing helpers in `resources/functions.php`, never store secrets in plain text.
- Schema changes go into `setup/init.php` and must be safe to run on an existing database (`CREATE TABLE IF NOT EXISTS`, `run_init_add_column()`).
- Keep the PHP 7.4 compatibility — CI lints every file on PHP 7.4 to 8.3.

## Translations

User-facing strings are wrapped in `_()` (gettext). After adding or changing strings, update `locale/pl_PL/LC_MESSAGES/message.po` and compile it:

```bash
msgfmt locale/pl_PL/LC_MESSAGES/message.po -o locale/pl_PL/LC_MESSAGES/message.mo
```

A new language goes into `locale/<locale>/LC_MESSAGES/` in the same way.

## Commits and pull requests

- One topic per pull request; describe what changed and how you tested it.
- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`…).
- Add a line to `CHANGELOG.md` under *Unreleased*.

By contributing you agree that your work is released under the [GPL-3.0](LICENSE) license.
