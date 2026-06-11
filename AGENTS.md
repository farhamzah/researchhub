# AGENTS.md - ResearchHub

## Project Name

ResearchHub

## Product Type

ResearchHub is a multi-user research management system for dissertation and academic research workflows.

The system is designed first for one main researcher, but it must be architected from day one to support many users, many projects, supervisors, examiners, validators, enumerators, and respondents.

## Core Product Goals

ResearchHub must help researchers manage:

- Research projects
- Dissertation documents
- Proposal files
- Chapter files
- Presentations
- Posters
- Journal manuscripts
- Reference articles
- Research instruments
- Ethics documents
- Survey data
- Respondent data
- Statistical analysis
- Academic reports
- Supervisor and examiner reviews
- Revision history
- Audit trail

## Stack

Use:

- Laravel
- Filament
- PostgreSQL
- Laravel Sanctum
- Google OAuth
- Google Drive API
- Python analysis service later

## Architecture Style

Use modular monolith.

Do not build a microservice architecture at the beginning.

Keep the system clean by organizing code into domain modules.

Recommended module structure:

```text
app/
  Modules/
    Auth/
    Users/
    ResearchProjects/
    Documents/
    DriveIntegration/
    ReviewLinks/
    Surveys/
    Respondents/
    Analysis/
    Reports/
    References/
    Guidance/
    AuditLogs/
    Notifications/
```

Each module should use clear layers when needed:

```text
Controllers
Requests
Actions
Services
Models
Policies
DTOs
Jobs
Events
Resources
```

## Non-Negotiable Architecture Rules

- Keep business logic out of controllers.
- Use Actions or Services for domain operations.
- Use Form Requests for validation.
- Use Policies for authorization.
- Use Jobs for slow operations.
- Use Events for audit-worthy actions when useful.
- Use migrations for every database change.
- Use UUID primary keys for all main ResearchHub domain tables from the first migration.
- Use foreign UUIDs for domain relationships.
- Do not use auto-increment bigint IDs for main domain tables.
- Use seeders for baseline roles, permissions, and document categories.
- Keep Google Drive file binary outside the database.
- Store only metadata, external IDs, file references, status, version, and access data in the database.
- Do not create unrelated features in a task.
- Do not refactor unrelated modules without explicit instruction.
- Do not silently change architecture decisions.

## Security Rules

ResearchHub handles sensitive academic data, research data, respondent information, review links, and Google Drive tokens. Security is mandatory.

- Never store Google OAuth tokens in plain text.
- Encrypt external service tokens.
- Never print secrets in logs.
- Never commit `.env`, credentials, private keys, token dumps, or service account files.
- Always enforce project-level authorization.
- Always enforce document-level authorization.
- Always enforce survey-level authorization.
- Review links must support expiry.
- Review links must support revoke.
- Review links must use strong random tokens.
- Public survey links must not expose private project data.
- Respondent identity must support hidden, anonymized, and pseudonym mode.
- Audit all sensitive actions.
- Do not bypass authentication or authorization to make UI work.
- Do not expose private Drive links to unauthorized users.
- Do not allow reviewers to access documents outside their review scope.
- Do not allow respondents to see analysis, documents, or project dashboard.
- Do not remove validation or policies to pass tests.

## Agent Safety Rules

- Never run destructive commands such as `rm -rf`, `format`, `del /s`, `db:wipe`, `migrate:fresh`, or database drop without explicit approval.
- Never read `.env` unless the task explicitly requires environment validation.
- Never print secrets, API keys, OAuth tokens, database credentials, or private file paths.
- Never commit `.env`, `.env.*`, credentials, service account files, token dumps, or private keys.
- Never disable authentication or authorization.
- Never bypass tests by removing assertions.
- Never modify unrelated modules.
- Always summarize terminal commands executed.
- Always summarize changed files.
- Always report known issues.

## Git Rules

Branch pattern:

```text
feature/task-00-contract-pack
feature/core-auth
feature/research-projects
feature/drive-integration
feature/document-vault
feature/review-links
feature/survey-builder
feature/analysis-engine
fix/*
docs/*
```

Commit style:

```text
docs(agent): add initial ResearchHub contract pack
feat(projects): add research project workspace
feat(documents): add document vault metadata
feat(review): add secure expiring review links
fix(auth): enforce project policy
test(survey): add survey response tests
```

## Definition of Done

A task is done only when:

- Scope is completed.
- No unrelated files are modified.
- Security rules are followed.
- Relevant tests are added or updated when code is changed.
- Migrations run successfully when database is changed.
- UI is usable when UI is changed.
- Documentation is updated when architecture or behavior changes.
- Agent report is produced.

## Required Agent Report Format

`REPORT_TEMPLATE.md` is the canonical agent report format.

Every completed task must report using this exact field set:

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

## Status Claim Options

Use only:

```text
PASS
PARTIAL PASS
FAIL
BLOCKED
```

## Product Owner Decision

The Product Owner is the user.

The technical PM and reviewer is ChatGPT.

After each task, the agent report must be sent back to ChatGPT for review before the next major task begins.
