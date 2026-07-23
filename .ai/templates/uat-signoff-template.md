# UAT Signoff: {Feature / release}

> UAT signoff cho client acceptance. Dùng kèm `uat-guide-template.md`. Output của Mode A UAT phase.

## Metadata

| Field | Value |
|-------|-------|
| UAT ID | UAT-{NNN} |
| Feature / Release | |
| Client tester | |
| Secomm support | |
| Date | |

## 1. Feature list (scope UAT)

| # | Feature | Spec ref |
|---|---------|----------|
| 1 | {feature} | {SPEC-ID} |

## 2. Test results

| # | Feature | Test case | Result (Pass/Fail) | Notes |
|---|---------|-----------|--------------------|-------|
| 1 | {feature} | {TC-ID} | Pass / Fail | {nếu Fail: bug ref} |

**Summary:** {X}/{Y} pass — {Z} fail.

## 3. Signoff

| Role | Name | Decision | Date |
|------|------|----------|------|
| Client | | Approved / Approved with conditions / Rejected | |
| Secomm PM | | | |

> Conditions / rejects → change request (`change-request-template.md`).

## 4. Outstanding issues

| # | Issue | Severity | Action + owner | Due |
|---|-------|----------|----------------|-----|
| 1 | {issue} | high/medium/low | {action} | {date} |
