# MyRiset Architecture

## Product Summary

MyRiset is a web-based research management platform designed for dissertation and academic research workflows.

The first target user is one main researcher, but the system must be multi-user and multi-project from the beginning.

## Architecture Decision

Use a modular monolith architecture with Laravel.

Reason:

- Faster to build.
- Easier to deploy.
- Easier to maintain.
- Suitable for early-stage product.
- Can be split later if needed.
- Lower cost than microservices.

## Main Components

```text
MyRiset Web App
    |
Laravel Application
    |
Domain Modules
    |
PostgreSQL Database
    |
Google Drive API
    |
Python Analysis Service later
```

## Core Modules

```text
Auth
Users
ResearchProjects
Documents
DriveIntegration
ReviewLinks
Surveys
Respondents
Analysis
Reports
References
Guidance
AuditLogs
Notifications
```

## Platform Scope

Initial platform:

- Web responsive app.
- Admin/research dashboard using Filament.
- Public review pages.
- Public survey pages.

Later:

- Mobile companion app.
- AI research assistant.
- Zotero integration.
- Advanced report templating.
- Collaboration workspace.

## Data Ownership

Each user can connect their own Google Drive account.

MyRiset stores:

- User data
- Project metadata
- Document metadata
- Drive file references
- Survey schema
- Survey responses
- Respondent metadata
- Analysis results
- Review comments
- Approval status
- Audit logs

MyRiset must not store large document binaries in the database.

## Database Identity Strategy

MyRiset uses UUID primary keys for all main domain entities from the first migration.

Rules:

- Use UUID primary keys for users, projects, documents, review links, surveys, respondents, analysis records, reports, references, guidance records, audit logs, and other main domain entities.
- Use foreign UUIDs for relationships between domain entities.
- Do not use auto-increment bigint IDs for main domain tables.
- Pivot and log tables may also use UUID unless there is a clear technical reason not to.
- This decision is final before TASK 01 creates migrations.

## Google Drive Strategy

Each user connects Google Drive using OAuth.

MyRiset creates a folder structure in the user's Drive.

Example:

```text
MyRiset/
  Project Name/
    01_Proposal/
    02_BAB_I_II_III/
    03_BAB_IV_V/
    04_Etik_dan_Izin/
    05_Instrumen/
    06_Survey/
    07_Data/
    08_Analisis/
    09_Presentasi/
    10_Publikasi/
    11_Lampiran/
```

Database stores Drive metadata:

```text
drive_file_id
drive_folder_id
web_view_link
mime_type
size
checksum if available
owner_user_id
project_id
document_id
document_version_id
```

## Review Link Strategy

Supervisors, examiners, validators, and reviewers may review documents through secure links without login.

Review links must support:

- Strong random token
- Expiry
- Revoke
- Password optional
- Permission scope
- Access log
- Download control
- Comment permission
- Approval permission

## Survey Builder Strategy

Use internal survey builder.

Survey structure can be stored as JSON schema for flexibility.

Responses must also be stored in relational tables for analysis.

Survey features:

- Sections
- Pages
- Question types
- Required fields
- Likert scale
- Likert matrix
- Multiple choice
- Short answer
- Long answer
- Number
- Date
- File upload
- Consent checkbox
- Hidden fields
- Conditional logic later
- Scoring later

## Respondent Privacy Strategy

Respondent identity and response data must be separated.

The system must support:

```text
Full identity mode
Hidden identity mode
Anonymous mode
Pseudonym mode: R001, R002, R003
```

Default display for analysis should use anonymized or pseudonymized data unless the user has permission to view identities.

## Analysis Strategy

Initial analysis can be implemented in Laravel for basic summaries.

Advanced analysis should use a Python analysis service.

Analysis outputs:

- Tables
- Charts
- Statistical values
- Academic narrative
- Exportable report

Target statistical methods:

- Descriptive statistics
- Frequency
- Percentage
- Mean
- Median
- Standard deviation
- Validity
- Reliability
- Normality
- Homogeneity
- Paired t-test
- Wilcoxon
- Independent t-test
- Mann-Whitney
- ANOVA
- ANCOVA
- Effect size
- N-gain
- Correlation
- Regression
- Crosstab

## Report Strategy

Reports must be generated in academic style.

Target exports:

```text
DOCX
PDF
XLSX
CSV
ZIP package
```

Reports:

- Progress report
- Guidance report
- Survey report
- Expert validation report
- Analysis report
- Dissertation Chapter IV draft
- Seminar package
- Examiner package
- Publication package

## Authorization Strategy

Authorization must be project-aware.

Use policies for:

- Project
- Document
- Document version
- Review link
- Survey
- Survey response
- Respondent identity
- Analysis result
- Report
- Reference
- Guidance note

## Audit Strategy

Audit important actions:

- Login
- Google Drive connection
- Project creation
- Member invitation
- Document upload
- Document version upload
- Document download
- Review link creation
- Review link access
- Review comment
- Approval decision
- Survey publish
- Survey response submission
- Data export
- Analysis run
- Report export
- Permission changes
