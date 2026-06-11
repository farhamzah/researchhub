# ResearchHub UI/UX Guide

## UI Direction

ResearchHub should look:

- Clean
- Premium
- Academic
- Modern
- Calm
- Easy to navigate
- Not crowded

Visual inspiration:

- Notion-like clarity
- Google Drive-like document organization
- Premium SaaS dashboard
- Academic research workspace

## Recommended Style

Use:

```text
Primary: Deep navy
Accent: Emerald or cyan
Background: Soft gray
Cards: White
Danger: Red
Warning: Amber
Success: Green
Muted: Gray
```

## Layout

Use sidebar navigation.

Main sidebar:

```text
Dashboard
Projects
Documents
Surveys
Respondents
Analysis
Reports
References
Guidance
Review Links
Settings
```

## Dashboard Components

Dashboard should show:

- Project cards
- Active project status
- Research progress timeline
- Pending review items
- Active surveys
- Respondent count
- Latest documents
- Analysis jobs
- Recent activity
- Quick action button

## Document Vault UI

Must support:

- Search
- Filter by category
- Filter by status
- Filter by file type
- Version badge
- Status badge
- Upload button
- Review link button
- Latest activity
- Empty state

## Survey Builder UI

Must support:

- Survey title
- Survey description
- Question list
- Add question
- Drag reorder later
- Question type selector
- Required toggle
- Preview
- Publish/unpublish
- Response count
- Export

## Review Page UI

Public review page must be simple.

Must show:

- ResearchHub branding
- Document title
- Version
- Expiry notice
- Reviewer permission
- View/download button if allowed
- Comment form if allowed
- Approve/revision buttons if allowed
- Access denied state
- Expired link state
- Revoked link state

## Survey Public UI

Must show:

- Survey title
- Description
- Consent section if configured
- Questions
- Progress indicator
- Submit button
- Success page
- Closed survey page
- Already submitted page if duplicate blocked

## UX Rules

- Always provide empty states.
- Always show clear status badges.
- Always use confirmation for destructive actions.
- Never show raw technical errors to normal users.
- Use readable typography.
- Keep forms clean.
- Avoid clutter.
- Prioritize speed and clarity.
- Mobile width must be usable.
