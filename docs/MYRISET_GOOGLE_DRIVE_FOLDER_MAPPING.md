# MyRiset Google Drive Folder Mapping

MyRiset is the source of truth for metadata, status, permissions, and audit history. Google Drive stores files, folders, and generated exports.

No automatic public link creation is allowed from folder mapping alone.

## Current Folder Structure

Current global folders:

| Drive path | Current type | Stored metadata |
| --- | --- | --- |
| `MyRiset` | `researchhub_root` | `drive_folders` |
| `MyRiset/Projects` | `projects_root` | `drive_folders` |
| `MyRiset/Templates` | `templates` | `drive_folders` |
| `MyRiset/Reports` | `global_reports` | `drive_folders` |
| `MyRiset/Exports` | `global_exports` | `drive_folders` |

Current project folders:

| Drive path | Current type | Stored metadata |
| --- | --- | --- |
| `MyRiset/Projects/{Project Title}` | `project_root` | `drive_folders` |
| `MyRiset/Projects/{Project Title}/Documents` | `documents` | `drive_folders` |
| `MyRiset/Projects/{Project Title}/Surveys` | `surveys` | `drive_folders` |
| `MyRiset/Projects/{Project Title}/Validation` | `validation` | `drive_folders` |
| `MyRiset/Projects/{Project Title}/Supervision` | `supervision` | `drive_folders` |
| `MyRiset/Projects/{Project Title}/Reports` | `reports` | `drive_folders` |
| `MyRiset/Projects/{Project Title}/Exports` | `exports` | `drive_folders` |

## Recommended Future Folder Structure

```text
MyRiset/
└── Projects/
    └── {Project Title}/
        ├── 01_Documents/
        ├── 02_Surveys/
        ├── 03_Validation/
        ├── 04_Supervision/
        ├── 05_Analysis/
        ├── 06_Reports/
        ├── 07_Publication/
        └── 99_Exports/
```

`05_Analysis` and `07_Publication` are not created by the current bootstrap action. Add them later in a focused task with tests and an upgrade plan for existing users.

## Module Mapping

| Module | Drive folder | File types | Created by | Metadata stored in DB | Future action |
| --- | --- | --- | --- | --- | --- |
| Documents | `Documents` / future `01_Documents` | DOCX, PDF, PPTX, XLSX, images, approved academic files | Document upload/version workflow | `documents`, `document_versions`, `drive_folders` | Implement live Drive upload and stale-file recovery |
| Survey Builder | `Surveys` / future `02_Surveys` | Survey instrument exports, printable questionnaires, schemas | Survey builder/export workflow | `surveys`, survey page/question/scoring tables, future `drive_files` | Export survey instrument to Docs/PDF |
| Expert Validation | `Validation` / future `03_Validation` | Validation invitation packs, validation reports, Aiken/CVI outputs | Validation result/report workflow | `survey_validation_rounds`, assignments, scores, future `drive_files` | Export validation report to Docs/PDF and validation matrix to Sheets |
| Supervision | `Supervision` / future `04_Supervision` | Supervision summaries, meeting notes, follow-up trackers | Supervision workflow | `supervision_sessions`, feedback, follow-up items, resources, future `drive_files` | Export supervision summary to Docs and follow-up tracker to Sheets |
| Analysis | Future `05_Analysis` | CSV, Markdown, DOCX, Sheets, charts | Analysis export workflow | `analysis_results`, `analysis_tables`, `analysis_narratives`, future `drive_files` | Store analysis exports in Drive instead of download-only |
| Report Generator | `Reports` / future `06_Reports` | Project progress reports, final reports, generated drafts | Report/export workflow | documents, analysis metadata, journey summaries, future `drive_files` | Add report export storage and version tracking |
| Publication Manager | Future `07_Publication` | Journal manuscripts, cover letters, response-to-reviewer files | Future publication workflow | documents with `journal_article` or `publication_draft`, future publication metadata | Add publication module and folder |
| Temporary exports | `Exports` / future `99_Exports` | CSV, DOCX, Markdown, temporary report bundles | Export services | export audit logs, future `drive_files` | Add retention/cleanup policy |
| Templates | Global `Templates` | Project template documents, reusable instrument templates | Admin/template workflow | project template catalog and future template metadata | Store reusable academic templates |
| Global Reports | Global `Reports` | Cross-project reports | Future admin reporting | future report metadata | Add only after cross-project reporting is approved |
| Global Exports | Global `Exports` | Cross-project export bundles | Future admin exports | future export metadata | Add retention and authorization rules |

## Metadata Rules By Module

Documents:

- Store document status, version, review metadata, and visibility in MyRiset.
- Store file binary in Drive once live upload is implemented.
- Store Drive file ID, folder ID, file name, MIME type, size, checksum, and safe links in `document_versions`.

Surveys:

- Store survey schema, questions, identity mode, scoring, and status in MyRiset.
- Exported survey files can be stored in Drive.
- Respondent identity must not be exported by default.

Validation:

- Store validation rounds, assignments, scores, and Aiken/CVI results in MyRiset.
- Store generated validation report files in Drive only after explicit export.
- Do not expose validator private contact data in generated public links.

Supervision:

- Store sessions, feedback, resources, and follow-up items in MyRiset.
- Store generated supervision summaries and follow-up sheets in Drive only after explicit export.
- Only visible resources may appear in public supervision links.

Analysis:

- Store analysis payloads and tables in MyRiset.
- Store generated CSV, DOCX, Markdown, and future Sheets exports in Drive only after explicit export.
- Default exports must not include respondent identity.

Reports and Publication:

- Store academic status and version metadata in MyRiset.
- Store generated report/publication files in Drive after explicit export.
- Sharing with external people requires a future controlled sharing workflow.

## Naming Convention

Recommended format:

```text
{project-slug}_{module}_{document-type}_{version}_{YYYY-MM-DD}
```

Examples:

```text
disertasi-pharmvr_documents_proposal_v02_2026-06-14.docx
disertasi-pharmvr_validation_aiken-cvi_v01_2026-06-14.pdf
disertasi-pharmvr_supervision_session-03_v01_2026-06-14.docx
disertasi-pharmvr_publication_bmc-draft_v01_2026-06-14.docx
```

Rules:

- Use project slug, not raw title, when possible.
- Use module slug.
- Use stable document/report type.
- Use version label.
- Use `YYYY-MM-DD`.
- Do not include respondent names, token values, private folder paths, or Google OAuth data.

## Future Implementation Tasks

1. Add a Drive file metadata model only if `document_versions` is not enough for cross-module exports.
2. Add live Drive upload for document versions.
3. Add export-to-Drive actions for analysis, validation, supervision, and reports.
4. Add stale folder/file detection.
5. Add Google Picker for selected existing files.
6. Add Google Docs exports.
7. Add Google Sheets exports.
8. Add controlled sharing after policy and confirmation UX are approved.
