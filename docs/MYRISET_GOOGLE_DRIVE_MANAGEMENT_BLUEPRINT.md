# MyRiset Google Drive Management Blueprint

## 1. Purpose

Google Drive is the external storage layer for MyRiset files, folders, and generated exports. MyRiset remains the source of truth for research workflow, authorization, metadata, audit history, and academic status.

Use Google Drive for:

- Research document file storage.
- Project folder structure.
- Uploaded or linked proposal, chapter, instrument, ethics, manuscript, and report files.
- Generated academic documents and export files.
- Future Google Docs and Google Sheets export targets.

Do not use Google Drive as the primary database for MyRiset business state.

## 2. Non-goals

This blueprint does not implement:

- Google Docs export.
- Google Sheets export.
- Google Picker.
- Public Drive sharing.
- Service account workflow.
- A new token system.
- AI generation.
- Email, WhatsApp, or payment features.
- Database migrations.

## 3. Architecture Principle

MyRiset stores:

- Project status.
- Document metadata.
- Document version metadata.
- Survey metadata.
- Validation result metadata.
- Supervision metadata.
- Analysis metadata.
- Drive file and folder IDs.
- Sync status.
- Audit logs.

Google Drive stores:

- Uploaded or linked files.
- Generated documents.
- Generated sheets.
- Exported reports.
- Project folder structure.

Rules:

- Do not store business logic only in Drive folder names.
- Do not rely on Drive as the only source of truth.
- Do not create public Drive links by default.
- Do not expose private Drive IDs outside authorized contexts.
- Keep all authorization decisions in MyRiset policies and services.

Current implementation already follows this direction for folder metadata:

- `DriveConnection` stores encrypted OAuth tokens.
- `DriveFolder` stores Drive folder IDs, folder type, path, and safe web view link metadata.
- `DocumentVersion` already has fields for `drive_file_id`, `drive_folder_id`, `file_name`, `mime_type`, `web_view_link`, and storage status.
- `DocumentStorageService` intentionally blocks live Google Drive upload until the upload flow is implemented safely.

## 4. OAuth and Scope Strategy

Preferred current scope:

```text
https://www.googleapis.com/auth/drive.file
```

Why:

- Per-file access.
- Narrower than full Drive scope.
- Better user trust.
- Enough for files and folders created or opened by the app.

Avoid unless justified:

```text
https://www.googleapis.com/auth/drive
```

Future optional scopes:

- Google Docs API scope for Docs export.
- Google Sheets API scope for Sheets export.

Do not request future scopes until the related feature exists and the user understands why access is needed.

Current route and environment naming:

```text
Local canonical route:
http://127.0.0.1:8001/auth/google/drive/callback

Production canonical route:
https://myriset.net/auth/google/drive/callback
```

Task alignment note:

```text
Local future alias, not implemented yet:
http://127.0.0.1:8001/google/drive/callback

Production future alias, not implemented yet:
https://myriset.net/google/drive/callback
```

Use the canonical `/auth/google/drive/callback` values until a route alias is intentionally implemented and tested.

Google Drive can remain unconfigured until OAuth is ready.

Rules:

- Client ID and client secret stay in `.env` only.
- Do not commit client secret.
- Do not paste client secret into chat.
- Do not log OAuth token payloads.
- Do not render access tokens or refresh tokens.

## 5. Folder Strategy

Current global structure from `config/researchhub_drive.php`:

```text
MyRiset/
├── Projects/
├── Templates/
├── Reports/
└── Exports/
```

Current project structure:

```text
MyRiset/
└── Projects/
    └── {Project Title}/
        ├── Documents/
        ├── Surveys/
        ├── Validation/
        ├── Supervision/
        ├── Reports/
        └── Exports/
```

Recommended refined project structure:

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

Comparison:

- Current implementation has Documents, Surveys, Validation, Supervision, Reports, and Exports.
- Current implementation does not create dedicated Analysis or Publication folders yet.
- Recommended future enhancement: add Analysis and Publication folder types and migrate display names to numbered names only after a focused task and tests.
- Do not rename existing real Drive folders automatically without a deliberate migration plan.

## 6. File Naming Strategy

Recommended deterministic format:

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

Current support:

- `DocumentFileNameSuggestionService` already generates deterministic document names using project slug, document type, version, date, and extension.
- Future export services should reuse the same naming shape and include the module name where useful.

Rules:

- Do not rename real files in this task.
- Avoid personal names or respondent identity in file names.
- Use version labels consistently.
- Use ISO date format `YYYY-MM-DD`.

## 7. Metadata Strategy

Current metadata exists in:

- `drive_connections`
- `drive_folders`
- `document_versions`
- Domain tables such as projects, documents, surveys, validation rounds, supervision sessions, and analysis results.

Future `drive_files` style tracking should include:

```text
id
user_id
research_project_id
document_id nullable
module
google_file_id
google_folder_id
file_name
mime_type
web_view_link nullable
version_label nullable
source_type
sync_status
last_synced_at
created_by
updated_by
created_at
updated_at
```

Suggested statuses:

```text
created
linked
exported
synced
stale
failed
revoked
```

Rules:

- Store Drive IDs and metadata only when the user is authorized for the project/resource.
- Keep raw file binary outside the database.
- Keep audit logs free of tokens, token hashes, private paths, and respondent identity dumps.

## 8. Module Integration Map

Detailed mapping is maintained in `docs/MYRISET_GOOGLE_DRIVE_FOLDER_MAPPING.md`.

High-level map:

- Documents -> Documents / future `01_Documents`.
- Survey Builder -> Surveys / future `02_Surveys`.
- Expert Validation -> Validation / future `03_Validation`.
- Supervision -> Supervision / future `04_Supervision`.
- Analysis -> future `05_Analysis`.
- Report Generator -> Reports / future `06_Reports`.
- Publication Manager -> future `07_Publication`.
- Temporary exports -> Exports / future `99_Exports`.

## 9. Security and Privacy Rules

Critical rules:

- Never render tokens.
- Never log access tokens.
- Never log refresh tokens.
- Never log client secrets.
- Encrypt stored tokens.
- No public sharing by default.
- No automatic public link creation.
- Revoke connection should disable Drive actions.
- Use least privilege scopes.
- Use per-user Drive connection unless a project-level service account design is explicitly approved later.
- Public survey, validation, supervision, and review links must not reveal Drive private links unless a future permission explicitly allows it.
- Do not expose respondent identity in Drive exports by default.

Sharing policy now:

```text
No automatic sharing.
No public file permissions.
No link sharing unless explicitly implemented later.
```

Future controlled sharing:

- Share selected report with promotor.
- Share validation report with authorized validator.
- Share publication folder with co-author.

Do not implement sharing until there is a policy, UI confirmation, and audit logging.

## 10. Connection and Revoke Workflow

Connection workflow:

1. Admin opens Google Drive Settings.
2. MyRiset checks client ID, client secret, redirect URI, and scope configuration.
3. User clicks Connect Google Drive.
4. OAuth state is stored in session.
5. Google returns to the callback route.
6. MyRiset validates OAuth state.
7. MyRiset stores encrypted tokens and safe account metadata.
8. User can create MyRiset global folders.

Revoke/disconnect workflow:

1. User clicks Disconnect / Revoke Connection.
2. MyRiset clears local encrypted tokens.
3. MyRiset marks the connection disconnected.
4. Drive actions become unavailable until reconnect.
5. Audit log records safe metadata only.

Current UI should show:

- Connection status.
- Connected Google account if safe.
- Scope summary.
- Client ID configured or missing.
- Client secret configured or missing.
- Redirect URI.
- Folder bootstrap status.
- Global folder status.
- Project folder status.
- Last Drive action.
- Last error summary.
- Revoke/disconnect action.
- Create MyRiset Folders action.

Do not show:

- Access token.
- Refresh token.
- Client secret.
- Raw Google API response.
- Private file path.

Guidance text:

```text
Google Drive belum dikonfigurasi.
Isi GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET di .env server.
```

If connected but folders missing:

```text
Google Drive sudah terhubung, tetapi folder MyRiset belum dibuat.
Klik Create MyRiset Folders.
```

## 11. Error Handling Strategy

| Error | Technical cause | Safe user message | Admin action | Developer action |
| --- | --- | --- | --- | --- |
| `redirect_uri_mismatch` | Google Cloud redirect URI differs from MyRiset config | Redirect URI Google Drive belum cocok. | Copy the URI shown in Settings into Google Cloud. | Confirm route and `GOOGLE_REDIRECT_URI` match. |
| `invalid_client` | Client ID/secret invalid or missing | OAuth client belum valid. | Recheck `.env` values on the server. | Confirm config cache and Google Cloud client type. |
| `access_denied` | User cancelled consent or lacks access | Koneksi Google Drive dibatalkan. | Retry only if intended. | No action unless repeated unexpectedly. |
| `token_expired` | Access token expired and refresh did not recover | Sesi Google Drive kedaluwarsa. Sambungkan ulang. | Reconnect Google Drive. | Add refresh-token handling if missing in future task. |
| `refresh_token_revoked` | Google account revoked app access | Akses Google Drive dicabut. Sambungkan ulang. | Reconnect and recreate folders if needed. | Clear stale token metadata safely. |
| `folder_not_found` | Stored folder ID no longer exists or was moved/deleted | Folder Drive tidak ditemukan. | Re-run folder bootstrap. | Mark folder metadata stale/failed. |
| `insufficient_permissions` | Scope too narrow or file owned elsewhere | Izin Drive tidak cukup untuk aksi ini. | Reconnect with approved scope if needed. | Avoid full-drive scope unless approved. |
| `quota_or_rate_limit` | Google API quota/rate limit reached | Google Drive sedang membatasi permintaan. Coba lagi nanti. | Retry later. | Add backoff/job retry in future task. |
| `network_error` | Temporary network/API failure | Google Drive belum dapat dihubungi. | Retry after connectivity recovers. | Log safe exception class and retry context. |

Error logs must record safe class/reason only, not raw token payloads.

## 12. Future Roadmap

Near-term tasks:

- Add Drive upload action for document versions, using `DocumentStorageService` and project folder metadata.
- Add project folder bootstrap action from project pages.
- Add safe Drive file metadata model if cross-module file tracking becomes broader than `document_versions`.
- Add user-friendly stale folder recovery.

Google Picker future design:

- Select existing Drive document.
- Attach to MyRiset document.
- Attach existing article draft.
- Attach existing approval letter.
- Attach existing validation report.

Picker should store selected file metadata, not copy the entire file unless requested.

Google Docs future exports:

- Academic narrative output.
- Validation report.
- Supervision summary.
- Project progress report.
- Publication draft.

Google Sheets future exports:

- Survey responses.
- Validation matrix.
- Aiken/CVI table.
- Follow-up revision tracker.
- Timeline export.

Do not implement Picker, Docs export, or Sheets export until separate tasks define permissions, UI, and tests.

## 13. QA Checklist

- Open Google Drive Settings.
- Verify configuration status.
- Verify no secret/token appears.
- Verify redirect URI is visible.
- Verify connected/disconnected state.
- Verify folder bootstrap guidance.
- Verify project folder mapping doc.
- Verify Drive optional behavior.
- Verify Drive settings can show missing OAuth guidance without breaking the core app.
- Verify connected state shows account metadata without tokens.
- Verify disconnect clears local credentials.
- Verify global folder bootstrap is idempotent.
- Verify project folder bootstrap respects project authorization.
- Verify production docs state Google Drive is optional.
- Verify no public file sharing is created automatically.
