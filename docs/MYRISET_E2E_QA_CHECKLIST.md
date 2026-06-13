# MyRiset End-to-End QA Checklist

Run demo data first:

```bash
php artisan db:seed --class=MyRisetDemoSeeder
```

Use local demo login when available:

```text
Email: admin@researchhub.test
Password: password
```

Do not use real personal data, real tokens, production Google credentials, or private files during this QA pass.

## 1. Login

- URL/path: `/admin/login`
- Expected screen: MyRiset sign-in page.
- Expected action: Login with the local demo admin.
- Pass/fail: [ ]
- Notes:

## 2. Dashboard Action Center

- URL/path: `/admin`
- Expected screen: Dashboard with Action Center, Pending Follow-Up, Expert Validation Pending, Supervisor Feedback, and Timeline Risks.
- Expected action: Confirm demo items for Disertasi PharmVR are visible and no token/hash/private data is shown.
- Pass/fail: [ ]
- Notes:

## 3. Project Detail

- URL/path: `/admin/projects/research-projects`
- Expected screen: Research Projects list includes Disertasi PharmVR.
- Expected action: Open project actions for Alur Riset, timeline, validators, and supervision where available.
- Pass/fail: [ ]
- Notes:

## 3A. Project Research Journey QA

- URL/path: `/admin/projects/{researchProject}/journey`
- Expected screen: Alur Riset page for Disertasi PharmVR.
- Expected action: Open Disertasi PharmVR project, choose Alur Riset / Research Journey, and verify overall progress is visible.
- Expected step check: Confirm all eleven steps appear with status badges, descriptions, metrics, and real CTA links.
- Expected next action check: Confirm Langkah berikutnya points to a meaningful next action, not a generic empty state.
- Expected safety check: Confirm no raw validation tokens, token hashes, public review URLs, respondent identity, private file paths, or Drive folder IDs appear.
- Pass/fail: [ ]
- Notes:

## 4. Documents CRUD

- URL/path: `/admin/documents`
- Expected screen: Documents list includes Proposal Disertasi PharmVR, BAB I Pendahuluan, BAB III Metodologi Penelitian, Draft Artikel BMC Medical Education, and Instrumen Validasi Ahli.
- Expected action: View/edit metadata only; do not upload private files for this checklist.
- Pass/fail: [ ]
- Notes:

## 5. Survey CRUD and Builder

- URL/path: `/admin/surveys`
- Expected screen: Survey list includes Angket Evaluasi Pembelajaran PharmVR.
- Expected action: Open builder and confirm twelve demo questions are present: eleven Likert questions plus one short-text question.
- Expected scoring check: Confirm the scoring setup includes one scale, four indicators, and scored Likert items for usability, engagement, CPOB/GMP understanding, and material relevance.
- Expected response check: Confirm demo responses are available for descriptive analysis without exposing respondent identity.
- Pass/fail: [ ]
- Notes:

## 5A. Survey Builder Wizard QA

- URL/path: `/admin/surveys/{survey}/builder`
- Expected screen: Builder for Angket Evaluasi Pembelajaran PharmVR shows stepper tabs for Setup Survey, Indikator, Pertanyaan, Skoring, Preview, Validasi Ahli, and Respons & Analisis.
- Expected setup check: Confirm title, project, status, identity mode, description, question count, response count, validation status, and analysis status are visible.
- Expected indicator check: Confirm four indicators and the Evaluasi Pembelajaran PharmVR scale are visible.
- Expected question check: Confirm question cards show order, type badge, required badge, indicator/scoring badge, option count, edit, duplicate, move, and delete lock state.
- Expected scoring check: Confirm scoring summary shows scoreable questions, indicator coverage, missing scoring count, and indicators used.
- Expected preview check: Confirm the admin-only preview looks like a respondent-facing survey and does not submit or create responses.
- Expected validation check: Confirm readiness checklist includes title, description, questions, Likert options, scoring, indicators, validation round, submitted validation, and Aiken/CVI availability.
- Expected response/analysis check: Confirm response count, completed count, last response, and analysis result status are visible.
- Expected lock check: Confirm response lock warning appears when responses exist.
- Expected safety check: Confirm no respondent identity, raw validation tokens, token hashes, public supervision URLs, private file paths, or Drive IDs appear.
- Pass/fail: [ ]
- Notes:

## 6. Expert Validator Registry

- URL/path: `/admin/expert-validators`
- Expected screen: Demo validators for CPOB material, media learning, and instrument methodology are listed.
- Expected action: Confirm validator emails use `example.test` and no real phone/private data is present.
- Pass/fail: [ ]
- Notes:

## 7. Expert Validation Link Public Flow

- URL/path: `/admin/surveys/{survey}/validation`
- Expected screen: Validation round Validasi Instrumen Angket Evaluasi PharmVR with two submitted assignments and one pending assignment.
- Expected action: Generate a validation link only if needed for manual QA, copy once, and verify public validation form in a private browser session.
- Pass/fail: [ ]
- Notes:

## 8. Expert Validation Results Aiken/CVI

- URL/path: `/admin/surveys/{survey}/validation/rounds/{round}/results`
- Expected screen: Result page shows submitted expert scores for all twelve questions, mixed accepted/revision recommendations, and Aiken/CVI-ready summary.
- Expected action: Confirm comments and recommendations render without exposing tokens or respondent data.
- Pass/fail: [ ]
- Notes:

## 8A. Academic Output Blocks QA

- URL/path: `/admin/surveys/{survey}/builder`, `/admin/surveys/{survey}/validation/rounds/{round}/results`, `/admin/projects/{researchProject}/supervision`, `/admin/projects/{researchProject}/journey`
- Expected screen: Copy-ready Academic Output blocks appear for Survey Instrument Summary, Expert Validation Summary, Aiken/CVI Interpretation, Survey Response / Analysis Summary, Supervision Summary, Follow-Up Revision Summary, and Project Progress Summary.
- Expected action: Copy each narrative and confirm the text is clear academic Indonesian, deterministic, and based only on visible structured data.
- Expected incomplete-data check: Confirm empty surveys, empty validation rounds, missing analysis results, and projects without follow-ups show cautious fallback text.
- Expected safety check: Confirm narratives do not expose raw tokens, token hashes, public review URLs, respondent identity, validator contacts, private file paths, Drive IDs, Google OAuth data, or secrets.
- Expected scope check: Confirm no AI generation, export workflow, Google Drive action, email, WhatsApp, migration, or scoring calculation is triggered by these blocks.
- Pass/fail: [ ]
- Notes:

## 9. Supervision Link Public Flow

- URL/path: `/admin/projects/{researchProject}/supervision`
- Expected screen: Bimbingan Proposal dan Validasi Instrumen PharmVR session exists with feedback status.
- Expected action: Generate a review link only if needed for manual QA, copy once, and verify public supervision feedback form in a private browser session.
- Pass/fail: [ ]
- Notes:

## 10. Supervision Resources and Follow-Up

- URL/path: `/admin/projects/{researchProject}/supervision`
- Expected screen: Shared Resources include proposal, validation instrument, survey, validation round, demo descriptive analysis result, Google Scholar, and manual note. Follow-Up items include two pending and one completed item.
- Expected action: Confirm visible resources do not expose private file paths and follow-up statuses/due dates appear.
- Pass/fail: [ ]
- Notes:

## 10A. Survey Analysis Demo

- URL/path: `/admin/surveys`
- Expected screen: Angket Evaluasi Pembelajaran PharmVR has submitted demo responses and a descriptive analysis result.
- Expected action: Open the analysis result and confirm indicator/scale summaries render for usability, engagement, CPOB/GMP understanding, and material relevance.
- Pass/fail: [ ]
- Notes:

## 11. Research Links

- URL/path: `/admin/research-links`
- Expected screen: BPOM CPOB 2024, Google Scholar, BMC Medical Education, Scopus, and Research Methods Resource appear as active/pinned links.
- Expected action: Confirm links use safe `https` URLs and open with a new-tab behavior where applicable.
- Pass/fail: [ ]
- Notes:

## 12. Google Drive Settings

- URL/path: `/admin/settings/google-drive`
- Expected screen: Google Drive settings page.
- Expected action: Skip real connection unless local Google Cloud OAuth credentials are configured. Confirm disconnected/readiness state is clear and no secret values are shown.
- Pass/fail: [ ]
- Notes:
