# Changelog

## 0.5.0 - 2026-11-01

- Rebuild installation migrations as a fresh-install baseline with auto-increment id, created_at, updated_at and nullable deleted_at on every default application table.
- Use matching Core ^0.5, standardize controller filters, views, exports and seeds, and remove historical schema conversion migrations.
- Make migrate:create use the Core migration template; createTable automatically adds the standard columns and deleted_at index.
- Reject MD5 accounts without rewriting their passwords. Preserve modern bcrypt cost rehashing.
- Verify empty and wb_ prefixes, all table columns, model timestamps, soft deletion/restore/permanent deletion, generated migration rollback, repeat execution and occupied-table refusal; retain HTTP authentication, CRUD and attachment tests.
- This release supports new installations only. Earlier release tags and their acceptance reports are unchanged.

## 0.4.0 - 2026-11-01

- Require the matching published WebBase Core ^0.4 release and commit the resolved lock file.
- Use bcrypt passwords, explicit admin:init initialization, renewed login sessions and default CSRF protection for administration writes.
- Add login/captcha rate limits and account, password and permission revalidation.
- Support database table prefixes in initial and upgrade migrations; keep deleted_at as the soft-delete field.
- Validate category tree changes and recycle-bin operations, rejecting missing-record and unbounded updates.
- Default to wangEditor with validated PDF/Office attachments and reproducible local LESS builds.
- Verify published dependencies in isolated HTTP/database tests, including packaged login/admin assets.
- Keep the public Starter limited to reusable administration features; product extensions remain separate.

Upgrade existing projects using docs/installation-security.md. Core, Starter application code and password-column migrations must be upgraded together.
