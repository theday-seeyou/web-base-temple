# Changelog

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
