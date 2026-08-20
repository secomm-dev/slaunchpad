# {Tiêu đề ticket — Verb + Object/Outcome (work-item-identity.md §6)}

<!-- LEGACY ticket form (pre-P2A) — `.ai/tickets/` là legacy read-only. Item MỚI (P2A):
     tạo canonical record `.ai/records/tasks|bugs|spikes/TASK-XXXXXX.md` (mint id bằng
     `bin/project-ai-idgen`) với frontmatter parent/display title — xem work-item-identity.md. -->

**Type:** Feature / Bug / Task / Change Request
**Priority:** Critical / High / Medium / Low
**Estimate:** {X}h
**Mode:** A / B / C / D
**Specification Level:** MINI / FULL
**Spec Status:** DRAFT / VALID / INVALID
**Specification:** {REQUIRED: canonical Full Spec path, or `Embedded Mini-Spec`}

## Description

{Mô tả rõ ràng những gì cần làm}

## Mini Spec

> Required when `Specification Level: MINI`. Full-Spec tickets reference their
> shared canonical spec instead of duplicating these rules.

### Goal

{Task giải quyết vấn đề gì}

### Expected Behavior

{Hệ thống phải behave thế nào sau khi hoàn thành}

### Constraints / Rules

{Rules/invariants không được phá}

### Out of Scope

{Không làm gì}

## Acceptance Criteria

- [ ] AC-1: {Given... When... Then...}
- [ ] AC-2: ...
- [ ] AC-3: ...

## Technical Notes

{Implementation hints, affected areas, dependencies}

## Files/Areas Affected

- {file hoặc area}
- {file hoặc area}

## Risks

- {risk}
- {risk}

## Definition of Done

- [ ] Code complete
- [ ] AI pre-review pass
- [ ] TL review approved
- [ ] Tests pass
- [ ] QC verified (Mode A/B) hoặc TL spot-checked (Mode C)
