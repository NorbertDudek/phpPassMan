# Security Policy

phpPassMan stores secrets, so security reports are taken seriously and handled with priority.

## Supported versions

Security fixes are made for the latest release and the `main` branch.

## Reporting a vulnerability

**Please do not open a public issue.** Report the problem privately through
[GitHub Security Advisories](https://github.com/NorbertDudek/phpPassMan/security/advisories/new).

Include as much as you can:

- affected file(s) or page(s) and the version / commit
- steps to reproduce or a proof of concept
- the impact you expect (data disclosure, privilege escalation, etc.)

You will get an acknowledgement within a few days. Once a fix is available it will be released together with an advisory, crediting you unless you prefer to stay anonymous.

## Hardening checklist for administrators

- Serve the application over **HTTPS only** and set `$require_https = true`.
- Make sure `resources/config.php`, `backups/` and (after installation) `setup/` are not reachable from the web. Apache rules ship in `.htaccess`; on nginx add them yourself.
- Keep `$backup_dir` outside the web root and keep a copy of `$secret_key` offline.
- Use the superuser account only for administration; give everyone a personal account.
- Keep PHP and the database server up to date.
