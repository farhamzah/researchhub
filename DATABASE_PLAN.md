# ResearchHub Database Plan

## Database

Use PostgreSQL.

## Primary Key Strategy

Use UUID primary keys for all main ResearchHub domain tables from the first migration.

Rules:

- Use UUID for all main domain entities.
- Use foreign UUIDs for relationships.
- Do not use auto-increment bigint IDs for main domain tables.
- Pivot and log tables may also use UUID unless there is a clear technical reason not to.
- This decision must be applied before TASK 01 creates migrations.

## Core Tables

```text
users
roles
permissions
role_user
permission_role

research_projects
project_members

drive_connections
drive_folders

document_categories
documents
document_versions
document_comments
document_approvals

review_links
review_link_access_logs

surveys
survey_pages
survey_questions
survey_logic_rules
survey_responses
survey_answers
respondents

analysis_jobs
analysis_results
analysis_tables
analysis_charts
analysis_narratives

reports
report_templates
report_exports

references
reference_notes
evidence_maps

guidance_sessions
guidance_notes

activity_logs
notifications
settings
```

## User

Fields:

```text
id
name
email
password
google_id nullable
avatar nullable
email_verified_at nullable
created_at
updated_at
```

## Research Project

Fields:

```text
id
owner_id
title
slug
description
research_type
institution
status
started_at
target_finished_at
created_at
updated_at
deleted_at
```

## Project Member

Fields:

```text
id
project_id
user_id nullable
email nullable
name nullable
role
status
invited_at
accepted_at nullable
created_at
updated_at
```

## Drive Connection

Fields:

```text
id
user_id
provider
email
access_token encrypted
refresh_token encrypted
token_expires_at
scopes json
status
last_connected_at
last_error nullable
created_at
updated_at
```

## Drive Folder

Fields:

```text
id
project_id
user_id
folder_type
drive_folder_id
name
path
web_view_link nullable
created_at
updated_at
```

## Document Category

Fields:

```text
id
name
slug
description nullable
sort_order
is_default
created_at
updated_at
```

Default categories:

```text
Proposal
BAB I
BAB II
BAB III
BAB IV
BAB V
Etik
Surat Izin
Instrumen
Validasi Ahli
Artikel Referensi
Data Mentah
Data Bersih
Hasil Analisis
Laporan
Presentasi
Poster
Publikasi Jurnal
Bukti Kegiatan
Foto/Video
Lampiran
```

## Document

Fields:

```text
id
project_id
category_id
owner_id
title
description nullable
status
current_version_id nullable
visibility
tags json nullable
created_at
updated_at
deleted_at
```

Status:

```text
draft
submitted
under_review
revision_required
approved
final
archived
```

## Document Version

Fields:

```text
id
document_id
version_number
uploaded_by
drive_file_id
drive_folder_id nullable
file_name
mime_type
file_size
checksum nullable
web_view_link nullable
web_download_link nullable
notes nullable
created_at
updated_at
```

## Review Link

Fields:

```text
id
project_id
document_id nullable
survey_id nullable
created_by
token_hash
password_hash nullable
label
permissions json
expires_at
revoked_at nullable
max_access_count nullable
access_count default 0
created_at
updated_at
```

Important:
Store token hash, not raw token.

## Review Link Access Log

Fields:

```text
id
review_link_id
ip_address
user_agent
accessed_at
action
metadata json nullable
created_at
updated_at
```

## Survey

Fields:

```text
id
project_id
created_by
title
slug
description nullable
schema json
status
identity_mode
is_public
published_at nullable
closed_at nullable
created_at
updated_at
deleted_at
```

Status:

```text
draft
published
closed
archived
```

Identity mode:

```text
full_identity
hidden_identity
anonymous
pseudonym
```

## Survey Question

Fields:

```text
id
survey_id
page_id nullable
question_key
type
label
help_text nullable
options json nullable
settings json nullable
is_required
sort_order
created_at
updated_at
```

## Respondent

Fields:

```text
id
project_id
survey_id
pseudonym_code nullable
name nullable encrypted
email nullable encrypted
identifier nullable encrypted
institution nullable
metadata json nullable
created_at
updated_at
```

## Survey Response

Fields:

```text
id
survey_id
respondent_id nullable
response_token_hash nullable
status
submitted_at nullable
ip_address nullable
user_agent nullable
score_total nullable
metadata json nullable
created_at
updated_at
```

## Survey Answer

Fields:

```text
id
survey_response_id
survey_question_id
question_key
answer_value json
score nullable
created_at
updated_at
```

## Analysis Job

Fields:

```text
id
project_id
survey_id nullable
created_by
type
status
input_config json
started_at nullable
finished_at nullable
error_message nullable
created_at
updated_at
```

## Activity Log

Fields:

```text
id
user_id nullable
project_id nullable
action
subject_type nullable
subject_id nullable
ip_address nullable
user_agent nullable
metadata json nullable
created_at
updated_at
```

## Important Database Rules

- UUID is the final primary key strategy for all main ResearchHub domain tables.
- Use foreign UUID columns for relationships between domain tables.
- Do not use auto-increment bigint IDs for main domain tables.
- Pivot and log tables may also use UUID unless there is a clear technical reason not to.
- Use soft deletes for important user content.
- Use indexes on project_id, user_id, survey_id, document_id, token_hash.
- Do not store raw review tokens.
- Do not store Google tokens without encryption.
- Encrypt sensitive respondent fields.
