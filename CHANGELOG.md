# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [1.0.0] - 2026-11-24

First public release of phpPassMan as a standalone project.

### Added
- SecureSend: share an entry with people outside the organisation through a link with expiry time, view limit and optional password; active links can be listed and revoked.
- Audit log of changes, views and shares, per-entry history and a global admin log with filtering and CSV export.
- Encrypted database backups, on demand or scheduled via `backup_cron.php`, with download and restore.
- Custom fields and file attachments for entries.
- Password history for entries.
- Folder password policies: complexity level and renewal period.
- Password generator modes: Random, Melodic and Passphrase (BIP39 word list); password strength indicator.
- Deny rules for users and groups.
- CSV / XLSX import.
- Login and password-reset links by e-mail, forced password change, SMTP settings with test message.
- Polish translation (gettext), chosen automatically from the browser language.
- CSRF protection for all forms and requests.

### Changed
- Entries are encrypted with AES-256-GCM (random IV on every save); notes, custom-field values and attachment names are encrypted too.
- Account passwords are hashed with Argon2id.
- Project renamed from phpMyVault to phpPassMan.

### Removed
- LDAP authentication.
- Dependency on the PHP mcrypt extension.

[Unreleased]: https://github.com/NorbertDudek/phpMyPassword/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/NorbertDudek/phpMyPassword/releases/tag/v1.0.0
