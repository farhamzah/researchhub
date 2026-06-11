# Agent Report Template

Every agent must report using this format.

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

## Status Rules

Use:

```text
PASS
PARTIAL PASS
FAIL
BLOCKED
```

## PASS Criteria

A task may claim PASS only if:

- Scope is complete.
- No unrelated files are changed.
- Tests pass or clear reason is provided.
- Security requirements are respected.
- Documentation is updated if needed.
- No known blocker remains.

## PARTIAL PASS Criteria

Use PARTIAL PASS if:

- Main feature works.
- Minor issue remains.
- Test is incomplete.
- UI needs visual QA.
- Documentation needs small update.

## FAIL Criteria

Use FAIL if:

- Feature does not work.
- Build fails.
- Migration fails.
- Security is bypassed.
- Unrelated files are modified.
- Data model is inconsistent.
- Required acceptance criteria are not met.

## BLOCKED Criteria

Use BLOCKED if:

- External credential is missing.
- Decision from product owner is required.
- Environment dependency is missing.
- Tool cannot proceed safely.
