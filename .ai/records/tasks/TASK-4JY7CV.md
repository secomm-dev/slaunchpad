---
id: TASK-4JY7CV
type: task
title: Align Button Checkbox and Radio foundations
project_code: SLP
external_refs:
  xcorp: SLP-246
mode: B
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-4JY7CV-form-control-alignment.md
risk: low
status: completed
created: 2026-09-24
updated: 2026-09-24
components:
  - CMP-THEME
source_areas:
  - app/design/frontend/Secomm/launchpad/web/tailwind/components/
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
---

# [SLP][TASK-4JY7CV] Align Button, Checkbox and Radio foundations

## Mini Spec

### Goal

Correct the Global Style controls against the current canonical Figma masters.

### Expected Behavior

- Button icon-only variants use component-specific geometry.
- Native checkbox/radio inputs match all supplied semantic states and sizes.

### Constraints / Rules

- Preserve Hyvä native semantics and the dedicated switch control.
- Use semantic design tokens; do not introduce Figma runtime dependencies.

### Out of Scope

- Product swatches and component-specific selectable cards.

### Acceptance Criteria

- [x] CSS geometry/state matrix matches the three canonical nodes.
- [x] Tailwind build and validation pass.
