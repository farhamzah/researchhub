# MyRiset UI/UX Consistency and Accessibility Audit

Audit date: 2026-06-13

## Scope

This audit reviewed the current MyRiset UI after the dashboard, project templates, project journey, document revision flow, survey builder, expert validation, supervision, academic output, public link polish, and Google Drive settings foundation work.

## Pages Checked

- Dashboard: `resources/views/filament/pages/dashboard.blade.php`
- Project Templates catalog and preview
- Project Research Journey
- Survey Builder Wizard
- Expert Validation Results
- Supervision admin workflow
- Public Expert Validation link
- Public Supervision Review link
- Public review layout
- Google Drive Settings page
- Document, Project, Survey, Research Link Filament resources
- Academic output block component

## Issues Found

### P0: Must Fix Before Demo

- Some custom pages used similar but not identical header/card/action patterns, which made Task 38 pages feel separate from existing admin flows.
- Public review pages had good labels but lacked a skip link for keyboard users.
- Status badges were implemented in several pages with repeated local class maps, increasing risk of inconsistent labels and contrast.

### P1: Should Fix Before Production

- Long custom pages still mix English and Indonesian copy. The current product is usable, but localization should be decided before production.
- Survey Builder still exposes advanced question configuration concepts. The current UI is safer than raw-only JSON, but a guided non-technical editor remains a future improvement.
- Validation result tables remain dense on small screens, although wrappers prevent severe overflow.
- Google Drive Settings has its own CSS token set. It is readable and safe, but should later be aligned with reusable MyRiset components.

### P2: Later Polish

- Dashboard and admin pages could benefit from a shared app-shell breadcrumb/context bar.
- Academic Output blocks are consistent enough for now but could later use a shared copy interaction pattern across all narrative sections.
- Filament resource tables still depend on Filament defaults for some empty states.

## Fixes Applied

- Added reusable MyRiset Blade UI components:
  - `x-myriset.page-header`
  - `x-myriset.section-card`
  - `x-myriset.status-badge`
  - `x-myriset.empty-state`
  - `x-myriset.action-link`
- Standardized Project Templates catalog and preview headers, cards, and action links.
- Standardized Project Journey status badges and action links, while preserving the existing journey service output.
- Improved Survey Builder status badges and empty states with clearer guidance.
- Added public layout skip link for keyboard navigation.
- Standardized public validation and supervision status badges and section markers.
- Improved Expert Validation Results empty state guidance.
- Added UI consistency tests for standard components, safe empty states, and sensitive data non-exposure.
- Added public UX accessibility tests for skip link, public status badges, and token/hash non-exposure.

## Accessibility Notes

- Form fields reviewed on critical public validation and supervision pages use visible labels.
- Public pages now include a skip link to the main content.
- Buttons and links in reusable components include visible focus outlines.
- Badges include text labels and do not rely on color alone.
- The design remains on a light academic interface with white cards and soft borders.
- No automated axe-style accessibility dependency was added; verification is by source inspection and feature tests.

## Manual QA Checklist

- Open `/admin` and confirm dashboard title is clear and not visually duplicated.
- Open `/admin/projects/templates` and confirm header, cards, and template CTAs are consistent.
- Open a template preview and confirm the create form labels are visible and readable.
- Open a Project Journey page and confirm status badges include readable text labels.
- Open Survey Builder and confirm empty states explain the next step.
- Open Validation Results and confirm no raw tokens, public URLs, or respondent identity are visible.
- Open Public Validation and Public Supervision links and tab through the page from the skip link to form fields.
- Resize major custom pages to mobile width and confirm cards stack without horizontal overflow.

## Deferred Issues

- Decide final language strategy: English-only, Indonesian-only, or Laravel localization.
- Replace advanced survey configuration with a fully guided question editor.
- Align Google Drive Settings CSS with reusable MyRiset components in a later UI pass.
- Add automated accessibility tooling if the team approves an extra dev dependency.
