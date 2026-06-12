# MyRiset UI/UX Review
**UIUX-01 — Audit, Visual Polish Plan, and Next Feature Wireframe**
*Prepared by: Antigravity UI/UX Review + Product Experience Agent*
*Date: 2026-06-12*

---

## 1. Executive Summary

MyRiset is a modular Laravel + Filament research management platform that has delivered solid
backend feature coverage through 15 development tasks. The core workflows — Projects, Documents,
Review Links, Survey Builder, Response Management, Analysis Center, and Export — are all
functionally in place and security-hardened.

However, the current UI/UX state reflects **"engineering-first, experience-second"** execution.
The backend is production-ready. The front-end experience needs a targeted polish sprint before
the product can be presented to supervisors, examiners, or institutional review boards.

This review identifies 47 specific UI/UX issues across 8 flow areas, rates them by priority,
and proposes two fully wireframed future features: **Research Timeline** and **Resource Link
Library**.

**Overall Assessment: PARTIAL PASS — Functional, Needs Polish.**

---

## 2. Current Strengths

| Strength | Detail |
|---|---|
| Consistent card/section pattern | All pages use `rounded-lg border bg-white p-6 shadow-sm` consistently |
| Security language visible | Raw token warning, identity-filtered disclaimer, export-once message are present |
| Status badges implemented | Document status, survey status, identity mode all shown in Filament tables |
| Empty state handling | `@empty` / `@forelse` used throughout; dashed empty states exist |
| Privacy-filtered display | `$privacyService->display()` correctly abstracts respondent identity in views |
| Confirmation on destructive actions | Publish, close survey use `requiresConfirmation()` in Filament |
| Action buttons properly scoped | All row actions respect `can()` policy checks |
| Export DOCX labeled as draft | Analysis Center explicitly marks output as academic draft |
| Review link once-only warning | Admin review link page explains raw URL appears only once |
| Rate limiting exists | Review, survey, and password routes all have throttle middleware |
| Public page responsive base | `max-w-4xl`, `sm:px-6 lg:px-8` used throughout public pages |

---

## 3. Current UI/UX Risks

| # | Risk | Severity | Scope |
|---|---|---|---|
| R1 | No persistent top navigation on non-Filament admin pages | HIGH | All custom admin pages |
| R2 | Review link status column shows raw string from DB (no badge coloring) | HIGH | Review links admin |
| R3 | Survey builder exposes raw JSON textarea for Options and Settings to all users | HIGH | Survey Builder |
| R4 | Analysis disclaimer is in Indonesian; rest of UI is in English | MEDIUM | Analysis Center |
| R5 | "Export CSV" and "Export CSV with identity" look nearly identical — easy to click wrong | HIGH | Responses |
| R6 | Revoke button has no confirmation dialog | HIGH | Review links admin |
| R7 | Document status displayed as raw DB string (e.g. `revision_required`) | MEDIUM | Document Vault |
| R8 | No Filament dashboard widgets — dashboard is empty shell | HIGH | Dashboard |
| R9 | Survey responses "Export Row Preview" shows raw JSON in dark pre block — alarming to non-tech users | MEDIUM | Responses |
| R10 | No "back to project" context anywhere in admin pages | MEDIUM | All custom admin pages |
| R11 | `response status` column shows raw `submitted` string — no badge | MEDIUM | Response list |
| R12 | Thank-you page has no branding beyond small uppercase label | LOW | Public survey |
| R13 | Public survey page renders questions from BOTH paginated pages and unpaged questions (double render risk) | HIGH | Public survey |
| R14 | Survey scoring page shows JSON textarea for interpretation rules — no friendly guide | HIGH | Scoring |
| R15 | Analysis run button has no spinner/loading state — appears broken for large surveys | MEDIUM | Analysis Center |
| R16 | No feedback when "Run Descriptive Analysis" completes successfully if no session message | MEDIUM | Analysis Center |

---

## 4. Page-by-Page Findings

### 4.1 Dashboard / Admin Entry

**Status: NOT BUILT — Empty Filament shell**

| Finding | Priority | Safe Fix? |
|---|---|---|
| No dashboard widgets exist | HIGH | No (needs backend) |
| No project quick-start card | HIGH | No (needs backend) |
| No respondent count, survey count, or document count summary | HIGH | No (needs backend) |
| Filament default branding only — no MyRiset identity shown | MEDIUM | Yes (copy/label) |

**UX Risk:** A new user logging in for the first time sees an empty Filament panel with no guidance.
No onboarding message. No "start here" direction. The product identity "MyRiset" does not
appear prominently on the dashboard entry.

**Recommendation:** Add a welcome widget block as a `StatsOverviewWidget` once backend is ready.
For now, a static information panel Blade page would significantly reduce first-session confusion.

---

### 4.2 Project Management (Filament Resource)

**Status: Functional. Minimal table.**

| Finding | Priority | Safe Fix? |
|---|---|---|
| No project description shown in table | LOW | Yes (column) |
| Project status badge has no color mapping | MEDIUM | Yes (badge colors) |
| No "members count" column visible | LOW | No (needs backend) |
| No project-level quick actions visible | MEDIUM | Partially (Filament actions) |
| Project ownership is not visible in table | MEDIUM | Yes (column) |
| No search or filter by research_type | LOW | Yes (Filament filter) |

---

### 4.3 Document Vault

**Status: Functional. Several UX risks.**

| Finding | Priority | Safe Fix? |
|---|---|---|
| Document `status` shows raw DB value (`revision_required`, `under_review`) | HIGH | Yes (label map) |
| Document `visibility` column shows raw value | MEDIUM | Yes (label map) |
| Version column shows "No version" for documents without uploads — confusing | MEDIUM | Yes (copy) |
| "Review Links" button label is good but column is crowded | LOW | Yes (spacing) |
| No visual distinction between latest version and older versions | HIGH | No (needs backend) |
| No document description shown in table | LOW | Yes (tooltip) |
| No quick "Open in Drive" link visible in table | MEDIUM | Yes (link column) |

**Recommendation (safe, immediate):** Map status labels using a `formatStateUsing()` callback:

```
draft → Draft
submitted → Submitted
under_review → Under Review
revision_required → Revision Required
approved → Approved
final → Final
archived → Archived
```

Similarly for `visibility`:

```
private → Private
project_members → Project Members
public → Public
```

---

### 4.4 Review Links (Admin Page)

**Status: Functional. Several critical UX risks.**

| Finding | Priority | Safe Fix? |
|---|---|---|
| `status` column shows raw string, no badge colors | HIGH | Yes (badge HTML) |
| Revoke button submits immediately — no confirmation | CRITICAL | Yes (JS confirm) |
| Raw token once-warning is present but small — easy to miss | HIGH | Yes (alert styling) |
| "Copy this URL now" warning needs stronger visual treatment | HIGH | Yes (alert banner) |
| Status column values: active/expired/revoked not visually distinct | HIGH | Yes (badge colors) |
| No permissions summary shown clearly | MEDIUM | Yes (pill list) |
| Create form: "Custom permissions" section always visible — confusing | MEDIUM | Yes (conditional show) |
| Password field has no "optional" hint text inline | LOW | Yes (placeholder) |
| `max_access_count` has no explanation of what it means | MEDIUM | Yes (help text) |
| Expired status not visually distinguished from active in table | HIGH | Yes (badge color) |

**Critical Risk:** The Revoke action submits a POST form immediately. No JavaScript confirmation
guard exists. A researcher could accidentally revoke a live review link sent to an examiner.
This is a HIGH UX safety risk.

**Safe fix:** Add `onclick="return confirm('Revoke this review link? This cannot be undone.')"` to
the revoke button.

---

### 4.5 Survey Builder

**Status: Functional. Significant UX friction for non-technical users.**

| Finding | Priority | Safe Fix? |
|---|---|---|
| Raw JSON textarea for Options and Settings exposed to all users | HIGH | No (needs builder UI) |
| Options JSON placeholder is `{"choices":["Option A","Option B"]}` — technical | HIGH | No (needs builder UI) |
| Question key field ("optional") is unclear — users may not know what it does | MEDIUM | Yes (help text) |
| "Sort order" field exposed raw — could be auto-managed | MEDIUM | No (needs auto-sort) |
| No visual preview of how Likert scale will look | HIGH | No (needs preview) |
| No question type icon or visual grouping | LOW | Yes (label copy) |
| Has responses warning is amber but small | MEDIUM | Yes (banner style) |
| Pages section and Questions section feel like two disconnected forms | MEDIUM | No (UX layout) |
| No drag-and-drop reorder (documented as future) | LOW | No |
| "Duplicate" button looks same weight as "Save" — should be secondary | LOW | Yes (class tweak) |

**Critical UX Risk:** A non-technical researcher is expected to type raw JSON:
`{"choices":["Option A","Option B"]}` to create a multiple-choice question. This is a major
barrier for usability. The JSON textarea approach works for developer users but is NOT suitable
for the target academic user.

**Recommendation:** In the backlog, create a guided choice builder UI that replaces JSON textarea
for common question types. For now, add a collapsible help section explaining each JSON format.

---

### 4.6 Survey Responses

**Status: Good UX foundations. Three specific issues.**

| Finding | Priority | Safe Fix? |
|---|---|---|
| "Export CSV" and "Export CSV with identity" look nearly identical | CRITICAL | Yes (styling) |
| Response `status` column shows raw string — no badge | MEDIUM | Yes (copy) |
| "Export Row Preview" raw JSON dark block is alarming to non-technical users | MEDIUM | Yes (label/section) |
| No pagination label (showing X of Y responses) | LOW | Yes (Laravel default) |
| No filter by status (submitted vs partial) | LOW | No (needs backend) |
| Respondent column shows display name but no identity mode context | MEDIUM | Yes (badge) |

**Critical Risk:** Export buttons are same size, same rounded style. "Export CSV with identity"
is styled amber/outline but placed immediately next to the default CSV export. A researcher in a
hurry could accidentally export with identity — sharing PII they did not intend to share.

**Safe fix:**
- Move "Export CSV with identity" to a separate, clearly labeled danger section
- Add a `⚠️ Includes respondent identity` warning label directly on the button
- Add a browser `onclick confirm()` on the identity export button

---

### 4.7 Analysis Center

**Status: Good overall. Three specific issues.**

| Finding | Priority | Safe Fix? |
|---|---|---|
| Analysis disclaimer is in Indonesian — mixes languages | HIGH | Yes (translate to English) |
| "Run Descriptive Analysis" button has no loading state | MEDIUM | No (needs JS) |
| Export buttons (CSV, Markdown, DOCX) have no grouping or visual hierarchy | MEDIUM | Yes (button group) |
| "Export DOCX Draft" is labeled — good. But CSV and Markdown not labeled as "draft" | MEDIUM | Yes (label) |
| "Hidden Omitted" stat card label is unclear | LOW | Yes (relabel) |
| No tooltip explaining what "descriptive only" means | LOW | Yes (help text) |
| Copy-ready narrative textarea is useful — but label "Copy-ready narrative block" is subtle | LOW | Yes (label) |
| Previous results list has no status column | MEDIUM | Yes (column) |

**Safe Fix — Language consistency:** Replace the Indonesian disclaimer on line 54 of
`analysis/admin/show.blade.php`:

```
Before: "DOCX yang dihasilkan adalah draf akademik deskriptif otomatis..."
After:  "The generated DOCX is an automatic descriptive academic draft. It does not include
         inferential conclusions and must be verified by the researcher and supervisor before
         use in any official academic document."
```

**Safe Fix — Stat card relabel:**
- `Hidden Omitted` → `Hidden Questions Excluded`

---

### 4.8 Report Export

**Status: Functional. Grouped in Analysis Center.**

| Finding | Priority | Safe Fix? |
|---|---|---|
| Export buttons are top-right header buttons — not grouped as export section | MEDIUM | Yes (grouping) |
| DOCX is labeled "Export DOCX Draft" — good | ✅ PASS | — |
| CSV and Markdown not labeled as draft/academic | MEDIUM | Yes (label) |
| No "download started" feedback | LOW | No (needs JS) |
| No export history or audit trail shown on page | LOW | No (needs backend) |

---

## 5. Recommended Quick Wins

These are **safe, immediate, non-breaking UI improvements** that can be applied without backend
changes, migration, or security changes.

### QW-01 — Review Link: Add Revoke Confirmation
**File:** `resources/views/review-links/admin/index.blade.php`
**Change:** Add `onclick="return confirm('Revoke this link? This cannot be undone.')"` to the revoke button.
**Impact:** Prevents accidental revocation of live examiner review links.

### QW-02 — Review Link: Style Token Warning as Alert Banner
**File:** `resources/views/review-links/admin/index.blade.php`
**Change:** Upgrade "Copy this URL now" section to use a bold amber alert with icon.
**Impact:** Ensures researcher sees the once-only token warning before navigating away.

### QW-03 — Response Export: Separate Identity Export Visually
**File:** `resources/views/surveys/admin/responses/index.blade.php`
**Change:** Move "Export CSV with identity" to its own danger zone section with `⚠️ Exports PII` label and confirm dialog.
**Impact:** Prevents accidental PII exposure.

### QW-04 — Analysis Disclaimer: Translate to English
**File:** `resources/views/analysis/admin/show.blade.php`
**Change:** Replace Indonesian disclaimer text with English equivalent.
**Impact:** Language consistency. Removes mixed-language confusion.

### QW-05 — Analysis: Relabel "Hidden Omitted" Stat Card
**File:** `resources/views/analysis/admin/show.blade.php`
**Change:** `Hidden Omitted` → `Hidden Questions Excluded`
**Impact:** Removes technical jargon from summary card.

### QW-06 — Document Status: Human-Readable Labels in Filament Table
**File:** `app/Filament/Resources/Documents/DocumentResource.php`
**Change:** Add `formatStateUsing()` to status and visibility columns.
**Impact:** Researcher sees "Under Review" instead of `under_review`.

### QW-07 — Survey Builder: Add Help Text to Question Key Field
**File:** `resources/views/surveys/admin/builder/index.blade.php`
**Change:** Add small `<p class="text-xs text-gray-500">` below the question key field.
**Impact:** Removes confusion about what "question key" is.

### QW-08 — Survey Builder: Add JSON Format Help Text
**File:** `resources/views/surveys/admin/builder/index.blade.php`
**Change:** Add collapsible `<details>` section explaining each JSON field format.
**Impact:** Reduces friction for non-technical survey builders.

### QW-09 — Survey Response Status: Add Badge
**File:** `resources/views/surveys/admin/responses/index.blade.php`
**Change:** Wrap `$response->status` in a styled badge span.
**Impact:** Visual clarity for response list scanning.

### QW-10 — Public Survey: Fix Double-Render of Questions
**File:** `resources/views/surveys/show.blade.php`
**Change:** Lines 68-76 render questions from `$survey->questions` even when pages are present. This risks double-rendering. Add `@if ($survey->pages->isEmpty())` guard.
**Impact:** Prevents questions from appearing twice when survey has pages.

---

## 6. Recommended Larger Improvements

These require backend work or significant frontend effort. Documented for planning only.

### LI-01 — Filament Dashboard Widgets
Build StatsOverviewWidget with:
- Total projects
- Active surveys
- Total responses
- Documents awaiting review
- Drive connection status

### LI-02 — Survey Builder: Visual Question Editor
Replace raw JSON textarea for Options/Settings with:
- Choice list builder (add/remove text inputs)
- Likert scale selector (1–5, 1–7 scale range UI)
- Preview panel showing real question rendering

### LI-03 — Review Link Status Badges
Add color-coded status badges in the review links admin table:
- `active` → Green badge
- `expired` → Gray badge
- `revoked` → Red badge

### LI-04 — Document: Version Timeline View
Add a collapsible version history panel on document detail view showing version number, file name, uploader, and date.

### LI-05 — Navigation Context Bar
Add a sticky breadcrumb or project context bar to all custom admin pages:
`MyRiset Admin > Projects > [Project Name] > Surveys > Survey Builder`

### LI-06 — Public Review Page: Expiry Countdown
Show a visible expiry countdown on the review link page when less than 24 hours remain.

### LI-07 — Analysis: Loading Spinner on "Run Analysis"
Add a POST form with spinner feedback when the analysis job is submitted.

### LI-08 — Mobile-Responsive Admin Pages
Current custom admin pages use `max-w-6xl` and `max-w-7xl` grids. On mobile (< 640px), the
Survey Builder layout and the Scoring page with 6-column grids are not usable.

---

## 7. Suggested Dashboard Structure

The following is the recommended MyRiset dashboard composition for Sprint 10:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  MyRiset                              [User Avatar] [Notification]  │
│─────────────────────────────────────────────────────────────────────────│
│  DASHBOARD                                                              │
│                                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌────────────┐ │
│  │  Projects    │  │  Documents   │  │  Responses   │  │ Drive      │ │
│  │     3        │  │    12        │  │    47        │  │ Connected  │ │
│  └──────────────┘  └──────────────┘  └──────────────┘  └────────────┘ │
│                                                                         │
│  ┌─────────────────────────────┐  ┌─────────────────────────────────┐  │
│  │  MY PROJECTS                │  │  RECENT ACTIVITY                │  │
│  │  ─────────────────────────  │  │  ─────────────────────────────  │  │
│  │  > Research Project Alpha   │  │  > Document uploaded — BAB I    │  │
│  │    [In Progress] [3 docs]   │  │  > Survey published — Baseline  │  │
│  │                             │  │  > Review link created          │  │
│  │  > Survey Validation Study  │  │  > Analysis run completed       │  │
│  │    [Published] [47 resp]    │  │  > Review link revoked          │  │
│  │                             │  │                                 │  │
│  │  [+ New Project]            │  │  [View all activity]            │  │
│  └─────────────────────────────┘  └─────────────────────────────────┘  │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  PENDING ITEMS                                                  │   │
│  │  ─────────────────────────────────────────────────────────────  │   │
│  │  Documents awaiting review: 2          [View Documents]         │   │
│  │  Active review links expiring in 24h: 1   [View Review Links]  │   │
│  │  Surveys with no analysis yet: 1          [View Surveys]        │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 8. Proposed Feature Wireframe 1: Research Timeline

### Feature Name
**Research Timeline** (also referred to as Project Timeline)

### Purpose
Allow researchers to plan dissertation milestones with start/end dates, track actual vs planned
progress, calculate weighted completion percentage, and identify delays early.

### Placement
1. **Project Detail Page** — collapsible Timeline tab alongside Documents, Surveys
2. **Dashboard Widget** — compact progress bar per project
3. **Dedicated Menu** — `Timeline` in the sidebar navigation under Projects

### Information Architecture

```
Project: Research Project Alpha
└── Timeline
    ├── Overview (Gantt-style bar chart)
    │   ├── Overall progress: 62%
    │   ├── Weighted progress: 58%
    │   └── Timeline health: [On Track / Delayed / At Risk]
    │
    ├── Milestones
    │   ├── Milestone 1: Literature Review
    │   │   ├── Status: Completed
    │   │   ├── Plan start: 2026-01-01 → Plan end: 2026-02-28
    │   │   ├── Actual start: 2026-01-05 → Actual end: 2026-03-07
    │   │   ├── Delay: 7 days
    │   │   ├── Weight: 15%
    │   │   └── Tasks (3/4 completed)
    │   │
    │   ├── Milestone 2: Instrument Development
    │   │   ├── Status: In Progress
    │   │   ├── Plan start: 2026-03-01 → Plan end: 2026-03-31
    │   │   ├── Actual start: 2026-03-10 → Actual end: —
    │   │   ├── Delay: 9 days (started late)
    │   │   ├── Weight: 20%
    │   │   └── Tasks (1/3 completed)
    │   │
    │   └── [+ Add Milestone]
    │
    └── Tasks (flattened view)
        ├── Filter by: Milestone | Status | Owner
        └── Table: Task name | Milestone | Owner | Plan | Actual | Status | Progress
```

### Milestone Card Wireframe

```
┌─────────────────────────────────────────────────────────────────┐
│  MILESTONE                                        [Edit] [Delete]│
│  Literature Review                                              │
│  ─────────────────────────────────────────────────────────────  │
│  Status:  [● Completed ▾]     Weight: 15%                       │
│                                                                 │
│  Planning:  2026-01-01  →  2026-02-28                           │
│  Actual:    2026-01-05  →  2026-03-07                           │
│                                                                 │
│  ⚠ Completed 7 days late                                        │
│                                                                 │
│  Tasks: ████████░░░░  3/4 complete   (75%)                      │
│  ─────────────────────────────────────────────────────────────  │
│  TASKS                                                          │
│  [x] Literature search — due 2026-01-31 — Owner: Researcher     │
│  [x] Read and annotate — due 2026-02-14 — Owner: Researcher     │
│  [x] Write draft review — due 2026-02-21 — Owner: Researcher    │
│  [ ] Supervisor approval — due 2026-02-28 — Owner: Supervisor   │
│                                                                 │
│  [+ Add Task]                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Status Values

| Status | Badge Color | Description |
|---|---|---|
| Not Started | Gray | No activity yet |
| In Progress | Blue | Work has begun |
| Completed | Green | Fully done |
| Delayed | Amber | Past planned end date |
| Cancelled | Red/muted | Removed from scope |

### Progress Formulas

```
Simple Progress = (Completed Tasks / Total Tasks) × 100%

Weighted Progress = (Sum of Completed Milestone Weights) / (Sum of All Milestone Weights) × 100%

Delay Indicator = Actual End Date − Planned End Date (in days)
  Positive = late, Negative = early, Zero = on time
```

### Database Tables (proposed — do not implement yet)

```
project_milestones
  id uuid
  project_id uuid FK
  title
  description nullable
  status (not_started|in_progress|completed|delayed|cancelled)
  weight decimal (e.g. 0.15 for 15%)
  plan_start_date
  plan_end_date
  actual_start_date nullable
  actual_end_date nullable
  sort_order
  notes nullable
  created_at, updated_at

project_milestone_tasks
  id uuid
  milestone_id uuid FK
  project_id uuid FK
  title
  owner_user_id uuid nullable FK
  status (not_started|in_progress|completed|cancelled)
  plan_date nullable
  actual_completion_date nullable
  progress_pct int nullable
  notes nullable
  sort_order
  created_at, updated_at
```

### UX Principles for Timeline Feature

- Show delay badges prominently — amber for ≤7 days late, red for >7 days late
- Progress bar color: gray (not started), blue (in progress), green (completed), red (delayed)
- Allow drag-and-drop reorder of milestones in a future iteration
- Timeline Gantt bar: horizontal bars showing plan (light) vs actual (dark) per milestone
- Weighted progress should be the primary metric shown on the dashboard widget

---

## 9. Proposed Feature Wireframe 2: Research Resource Link Library

### Feature Name
**Research Links** (secondary: Resource Library)

### Purpose
Allow researchers to save and organize important URLs — journals, conferences, regulation pages,
datasets, ethics boards, statistical tools, and learning resources — in a categorized, searchable
library linked to specific projects.

### MVP Approach
- Manual URL input only
- Optional thumbnail_url or favicon_url field (no auto-scraping)
- Category assignment
- Project association
- Favorite/pin toggle
- Open link button

Auto-metadata preview (title extraction, og:image, favicon fetch) can be a future enhancement.

### Placement
1. **Project detail page** — "Resources" tab
2. **Global sidebar** — `Research Links` menu (cross-project library view)
3. **Dashboard** — "Pinned Resources" quick-access panel

### Information Architecture

```
Research Links
└── Project: Research Alpha
    ├── All Categories
    ├── Filter: [Journal] [Conference] [Dataset] [Statistics] [Ethics] [Other]
    ├── Sort: Recently Added | Alphabetical | Last Accessed
    │
    ├── Cards Grid View (2–3 columns)
    │   ├── Resource Card
    │   │   ├── [Thumbnail/Favicon]
    │   │   ├── Site Name
    │   │   ├── Category Badge
    │   │   ├── Description (2 lines max)
    │   │   ├── [Open Link ↗] [Pin ♡] [Edit] [Delete]
    │   │   └── Last checked: 2026-06-10
    │   │
    │   └── [+ Add Resource]
    │
    └── List View (compact table)
        ├── Site Name | URL | Category | Project | Pinned | Actions
```

### Resource Card Wireframe

```
┌────────────────────────────────────────────────┐
│  [🖼 Thumbnail 80×60]   Journal               │  ← category badge
│                                                │
│  PubMed Central                                │  ← site name
│  https://www.ncbi.nlm.nih.gov/pmc/             │  ← URL (truncated)
│                                                │
│  Free full-text archive of biomedical and      │  ← description
│  life sciences journal literature.             │
│                                                │
│  Last checked: 2026-06-10                      │
│                                                │
│  [Open ↗]  [♡ Pin]  [Edit]  [Delete]          │
└────────────────────────────────────────────────┘
```

### Category List

| Category | Icon Suggestion |
|---|---|
| Journal | 📄 |
| Conference | 🎤 |
| Regulation | ⚖️ |
| Reference | 📚 |
| Dataset | 📊 |
| Repository | 🗄️ |
| Google Drive | ☁️ |
| OJS | 🏛️ |
| Ethics | 🔒 |
| Statistics | 📈 |
| Learning Resource | 🎓 |
| Other | 🔗 |

### Add Resource Form Wireframe

```
┌─────────────────────────────────────────────────────────┐
│  ADD RESOURCE                                           │
│  ───────────────────────────────────────────────────    │
│  URL *                                                  │
│  [ https://www.ncbi.nlm.nih.gov/pmc/            ]       │
│                                                         │
│  Site Name *                                            │
│  [ PubMed Central                               ]       │
│                                                         │
│  Category                                               │
│  [ Journal ▾ ]                                          │
│                                                         │
│  Description                                            │
│  [ Free full-text archive of biomedical and...  ]       │
│  [ life sciences journal literature.            ]       │
│                                                         │
│  Project (optional)                                     │
│  [ Research Project Alpha ▾ ]                           │
│                                                         │
│  Thumbnail URL (optional)                               │
│  [ https://... or leave blank for favicon       ]       │
│                                                         │
│  [ ] Pin to dashboard                                   │
│                                                         │
│  [Cancel]                          [Add Resource]       │
└─────────────────────────────────────────────────────────┘
```

### Database Tables (proposed — do not implement yet)

```
research_links
  id uuid
  project_id uuid nullable FK (null = global)
  created_by uuid FK users
  site_name
  url
  description nullable
  category (journal|conference|regulation|reference|dataset|repository|google_drive|ojs|ethics|statistics|learning_resource|other)
  thumbnail_url nullable
  favicon_url nullable
  is_pinned boolean default false
  last_checked_at nullable
  notes nullable
  created_at, updated_at, deleted_at
```

### UX Principles for Resource Library Feature

- Cards grid is the primary view (visual, thumbnail-forward)
- List/table is secondary (compact, scannable)
- Search should match site_name, URL, and description
- Filter by category should be pills/tabs, not a dropdown
- Pinned resources appear at the top or in a dedicated section
- "Open" button opens in new tab — never in same window
- Delete should confirm with a simple dialog
- Duplicate URL detection should warn but not block
- Favicon fallback: use `https://www.google.com/s2/favicons?sz=64&domain={url}` if no thumbnail

---

## 10. Prioritized UI/UX Backlog

### Sprint 10 — Critical Safety Fixes (do now)

| ID | Item | Effort | Risk |
|---|---|---|---|
| UX-S10-01 | Revoke button confirmation dialog | 1h | Critical |
| UX-S10-02 | Identity export button danger styling + confirm | 1h | Critical |
| UX-S10-03 | Translate analysis disclaimer to English | 30m | High |
| UX-S10-04 | Document status/visibility human-readable labels | 1h | High |
| UX-S10-05 | Survey: Fix double-render question loop | 30m | High |
| UX-S10-06 | Review link: Upgrade token warning to alert banner | 30m | High |
| UX-S10-07 | Survey Builder: Help text for question key field | 30m | Medium |
| UX-S10-08 | Survey Builder: JSON format help section | 1h | Medium |
| UX-S10-09 | Analysis: Relabel "Hidden Omitted" card | 15m | Low |
| UX-S10-10 | Response status: Add badge styling | 30m | Medium |

### Sprint 10 — Polish (do soon)

| ID | Item | Effort |
|---|---|---|
| UX-S10-11 | Review link status badges (active/expired/revoked color) | 1h |
| UX-S10-12 | Export buttons: Group as Export section on Analysis page | 1h |
| UX-S10-13 | Navigation context breadcrumb on all custom admin pages | 2h |
| UX-S10-14 | Survey Builder: Duplicate button styled as secondary | 30m |
| UX-S10-15 | Thank-you page: Add larger branding and response ID | 30m |

### Sprint 11 — Feature Enhancements (plan soon)

| ID | Item | Effort |
|---|---|---|
| UX-S11-01 | Filament dashboard widgets (stats overview) | 1 day |
| UX-S11-02 | Survey Builder: Visual choice list editor | 2 days |
| UX-S11-03 | Analysis: Loading spinner on run button | 2h |
| UX-S11-04 | Mobile responsive audit for custom admin pages | 1 day |
| UX-S11-05 | Public review page: expiry countdown | 3h |
| UX-S11-06 | Document version timeline panel | 1 day |

### Sprint 12 — New Features (plan for Q3)

| ID | Item | Effort |
|---|---|---|
| UX-S12-01 | Research Timeline feature — full implementation | 5 days |
| UX-S12-02 | Resource Link Library — full implementation | 3 days |
| UX-S12-03 | PDF export of analysis report | 2 days |
| UX-S12-04 | Print-friendly view for review page | 1 day |
| UX-S12-05 | Collaboration comments on documents | 5+ days |

---

## Appendix A — Safe Code Changes Applied in This Task

No code changes were applied in UIUX-01. All identified safe changes are documented above as
Quick Wins (Section 5) and can be applied in a follow-up task.

## Appendix B — Browser/Visual QA Status

```
Browser visual QA unavailable because: MyRiset is a local Laravel application with no
publicly accessible staging URL. Browser screenshot evidence was not captured.
Visual analysis is based on source code inspection of all Blade templates and Filament Resources.
```

## Appendix C — Language Inconsistency Reference

The following file contains mixed Indonesian/English language that should be resolved:

- `resources/views/analysis/admin/show.blade.php` line 54: Indonesian disclaimer text
- `resources/views/analysis/admin/show.blade.php` line 96: Indonesian disclaimer appended to
  copy-ready narrative textarea

All other UI text is in English. Recommend standardizing all UI text to English. Any
Indonesian-language support should be handled via Laravel localization (`.lang` files) in a
future task.

---

*End of UIUX-01 Review — MyRiset UI/UX Audit, Visual Polish Plan, and Next Feature Wireframe*
