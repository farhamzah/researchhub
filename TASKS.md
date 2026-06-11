# ResearchHub Task List

## Current Task

### TASK 00B - Resolve Contract Review Findings

Status:
Ready for execution.

Agent:
Codex Architect

Reasoning:
High

Scope:

- Resolve contract review findings before Git initialization and initial commit.
- Finalize UUID primary key strategy.
- Make `REPORT_TEMPLATE.md` the canonical agent report format.
- Formalize acceptance criteria for TASK 01 through TASK 05.

Must not do:

- Do not implement application code.
- Do not install Laravel.
- Do not initialize Git.
- Do not add secrets.
- Do not edit `.env`.
- Do not run destructive commands.
- Do not weaken existing security statements.

Acceptance Criteria:

- `DATABASE_PLAN.md` clearly states UUID as final primary key strategy.
- `AGENTS.md` states `REPORT_TEMPLATE.md` is canonical.
- `AGENTS.md` report format matches `REPORT_TEMPLATE.md`.
- `TASKS.md` has formal acceptance criteria for TASK 01 through TASK 05.
- No application code is implemented.
- No secrets are added.
- No unrelated files are modified.
- Agent report is provided using `REPORT_TEMPLATE.md`.

Required Report:

```text
TASK ID:
TASK NAME:
AGENT:
TOOL:
BRANCH:
STATUS CLAIM:

SUMMARY:
- 

CHANGED FILES:
- 

DATABASE/MIGRATION CHANGES:
- 

SECURITY CHANGES:
- 

TEST RESULT:
- 

BROWSER/SCREENSHOT EVIDENCE:
- 

KNOWN ISSUES:
- 

RISKS:
- 

NEXT RECOMMENDATION:
- 
```

## Completed Tasks

### TASK 00 - Agent Contract Pack

Status:
Completed.

Agent:
Codex Architect

Reasoning:
High

Scope:

- Create the initial documentation and agent contract files.

Files created:

```text
AGENTS.md
ARCHITECTURE.md
ROADMAP.md
TASKS.md
SECURITY.md
DATABASE_PLAN.md
UI_UX_GUIDE.md
REPORT_TEMPLATE.md
```

Must not do:

- Do not implement application features.
- Do not add secrets.
- Do not modify unrelated files.
- Do not run destructive commands.

Acceptance Criteria:

- All contract files are created in root repository.
- All files are readable and consistent.
- No application logic is changed.
- No secrets are added.
- No unrelated files are modified.
- Agent report is provided.

Required Report:

Use `REPORT_TEMPLATE.md`.

## Next Tasks

### TASK 01 - Laravel + Filament Skeleton

Agent:
Codex Backend

Reasoning:
High

Scope:

- Install Laravel if repository is empty.
- Configure PostgreSQL as the intended database.
- Install and configure Filament.
- Prepare auth baseline.
- Create basic dashboard shell.
- Keep UUID strategy documented before domain migrations are created.

Must not do:

- Do not implement ResearchHub business features yet.
- Do not implement Google Drive.
- Do not implement document vault.
- Do not implement survey builder.
- Do not implement analysis features.
- Do not commit `.env`.
- Do not add secrets.
- Do not run destructive commands.

Acceptance Criteria:

- Laravel app exists and boots.
- Filament is installed/configured.
- PostgreSQL is the intended database configuration.
- UUID strategy is documented before domain migrations.
- `.env` is not committed.
- `.env.example` is safe.
- No ResearchHub business features are added yet.
- No secrets are committed.
- Git working tree is clean or clearly explained.
- Install/test commands are reported.
- Known issues are reported.

Required Report:

Use `REPORT_TEMPLATE.md`.

### TASK 02 - Users, Roles, Permissions, Projects

Agent:
Codex Backend + Security

Reasoning:
High

Scope:

- Implement users, roles, permissions, research projects, and project members.
- Add project-level roles and permissions.
- Add project dashboard foundation only as needed for the module.
- Add project policies.
- Add baseline activity log.
- Add seeders for baseline roles and permissions.

Must not do:

- Do not implement Google Drive.
- Do not implement document vault.
- Do not implement survey builder.
- Do not implement analysis features.
- Do not bypass authorization to make UI work.
- Do not add secrets.
- Do not run destructive commands.

Acceptance Criteria:

- Users, roles, permissions, `research_projects`, and `project_members` are implemented.
- UUID primary keys are used.
- Project-level authorization policy exists.
- Baseline activity log exists.
- Seeders exist for baseline roles/permissions.
- No Google Drive, document vault, survey builder, or analysis features are added.
- Tests or clear verification steps are provided.

Required Report:

Use `REPORT_TEMPLATE.md`.

### TASK 03 - Google Drive Connection

Agent:
Codex Drive Integration

Reasoning:
High

Scope:

- Implement Google OAuth connection per user.
- Store encrypted Drive tokens.
- Store Drive connection metadata.
- Create or stage ResearchHub and project folder auto-create behavior.
- Add disconnect/reconnect flow.
- Add safe Drive error handling.
- Enforce authorization checks.

Must not do:

- Do not log OAuth tokens.
- Do not commit OAuth tokens or credentials.
- Do not expose private Drive links to unauthorized users.
- Do not permanently delete Drive files without explicit confirmation.
- Do not implement document vault beyond integration points required for Drive connection.
- Do not implement survey builder or analysis features.
- Do not run destructive commands.

Acceptance Criteria:

- Each user can connect Google Drive.
- OAuth token storage is encrypted.
- Drive connection metadata is stored.
- Folder auto-create plan or implementation exists.
- Disconnect/reconnect flow exists or is clearly staged.
- No tokens are logged or committed.
- Drive errors are handled safely.
- Authorization checks exist.

Required Report:

Use `REPORT_TEMPLATE.md`.

### TASK 04 - Document Vault

Agent:
Codex Documents + Antigravity UI

Reasoning:
High

Scope:

- Implement document categories.
- Implement document metadata.
- Implement document versioning.
- Use Google Drive file references or clear Drive-ready stubs if Drive is not ready.
- Implement document status workflow.
- Add project-level authorization.
- Add audit logging for upload, version, and download actions.

Must not do:

- Do not store file binaries in the database.
- Do not expose private Drive links to unauthorized users.
- Do not implement survey builder.
- Do not implement analysis features.
- Do not implement secure review links beyond integration hooks.
- Do not add secrets.
- Do not run destructive commands.

Acceptance Criteria:

- Document categories exist.
- Document metadata exists.
- Document versioning exists.
- Files are not stored as database binaries.
- Google Drive file references are used or clearly stubbed if Drive is not ready.
- Document status workflow exists.
- Project-level authorization is enforced.
- Upload/version/download actions are audit-logged.

Required Report:

Use `REPORT_TEMPLATE.md`.

### TASK 05 - Secure Review Links

Agent:
Codex Security + Antigravity QA

Reasoning:
High

Scope:

- Implement secure review link tokens.
- Implement expiry and revoke behavior.
- Implement scoped permissions.
- Implement access logs.
- Implement reviewer comment behavior where allowed.
- Implement approve/request-revision behavior where allowed.
- Implement download control.
- Add public review page states for access denied, expired, and revoked links.

Must not do:

- Do not store raw review tokens.
- Do not expose documents outside review scope.
- Do not allow access after expiry or revoke.
- Do not allow comments, approval, revision requests, or downloads without permission.
- Do not expose respondent identity unless explicitly allowed.
- Do not add secrets.
- Do not run destructive commands.

Acceptance Criteria:

- Secure review links use strong random tokens.
- Raw tokens are not stored; only token hashes are stored.
- Links support expiry.
- Links support revoke.
- Permissions are scoped.
- Access logs are recorded.
- Expired and revoked links are blocked.
- Reviewer can comment only if permission allows.
- Reviewer can approve/request revision only if permission allows.
- Download is controlled by permission.

Required Report:

Use `REPORT_TEMPLATE.md`.
