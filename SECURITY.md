# ResearchHub Security Policy

## Security Principle

ResearchHub must be secure by default.

The system handles:

- Dissertation documents
- Research files
- Personal academic data
- Respondent identity
- Survey responses
- Review links
- Google Drive OAuth tokens
- Analysis outputs

Security must be implemented from the beginning.

## Authentication

Support:

- Email-password login
- Google Login
- Optional 2FA later

Rules:

- Use secure password hashing.
- Do not log passwords.
- Do not expose auth errors in a way that leaks account existence.
- Use rate limiting for login attempts.
- Use CSRF protection for browser forms.

## Authorization

Use project-aware authorization.

Every access must check:

```text
Who is the user?
Which project?
Which role?
Which permission?
Which resource?
```

Use policies for:

- Project
- Document
- Survey
- Respondent
- Analysis
- Report
- ReviewLink

## Google OAuth and Drive Security

Rules:

- Store Google OAuth tokens encrypted.
- Never store tokens in plain text.
- Never print tokens in logs.
- Allow user to disconnect Drive.
- Handle expired tokens safely.
- Do not expose private Drive file IDs to unauthorized users.
- Do not permanently delete Drive files without explicit confirmation.
- Store file metadata only in the database.

## Review Link Security

Review links are public-entry features and must be treated as sensitive.

Every review link must support:

- Strong random token
- Expiry date
- Revoke status
- Optional password
- Permission scope
- Access log
- Download control

Review links must not:

- Show documents outside scope.
- Allow access after expiry.
- Allow access after revoke.
- Allow approval unless permission allows.
- Allow download unless permission allows.
- Show respondent identity unless explicitly allowed.

## Survey Security

Public survey links must:

- Only show the intended survey.
- Not show project private data.
- Validate all input.
- Support consent checkbox.
- Support anonymous or identity mode.
- Block duplicate response if configured.
- Support tokenized response if configured.

## Respondent Privacy

Respondent identity must be separated from survey answers.

Modes:

```text
Full identity
Hidden identity
Anonymous
Pseudonym
```

Default analysis display should not show identity.

Only authorized users may view identities.

## File Security

Rules:

- Validate file type.
- Validate file size.
- Store file binary in Google Drive, not database.
- Use metadata in database.
- Consider virus scanning later.
- Use audit log for upload/download.
- Avoid exposing raw storage paths.

## Audit Log

Audit these actions:

- Login
- Logout
- Failed login
- Google Drive connect/disconnect
- Project create/update/delete
- Member add/remove
- Permission change
- Document upload
- Document version upload
- Document download
- Review link create/update/revoke
- Review link access
- Review comment
- Approval/revision decision
- Survey publish/unpublish
- Survey response submit
- Data export
- Analysis run
- Report export

## Agent Safety

AI coding agents must not:

- Read or print secrets.
- Commit `.env`.
- Disable security checks.
- Run destructive commands without approval.
- Modify unrelated modules.
- Remove tests to make builds pass.
- Store credentials in source code.

## Production Readiness Checklist

Before production:

- HTTPS enabled.
- APP_DEBUG=false.
- Strong APP_KEY.
- Database backups configured.
- Queue worker configured.
- Scheduler configured.
- Log rotation configured.
- Rate limiting configured.
- Review link expiry tested.
- Token encryption tested.
- Authorization tests pass.
- Sensitive export permission tested.
