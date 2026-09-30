---
id: TASK-DWW34M
type: task
title: Implement Secomm Launchpad Core Hyva Header
project_code: SLP
parent: null
external_refs:
  xcorp: SLP-246
mode: B
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-DWW34M-launchpad-core-header.md
risk: medium
status: completed
created: 2026-09-24
updated: 2026-09-25
components:
  - CMP-THEME
source_areas:
  - app/code/Secomm/ThemeHelper/
  - app/design/frontend/Secomm/launchpad/Magento_Theme/
  - app/design/frontend/Secomm/launchpad/Hyva_Theme/web/svg/
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
---

# [SLP][TASK-DWW34M] Implement Secomm Launchpad Core Hyvä Header

## Mini Spec

### Goal

Implement the reusable Launchpad Core Header from the approved Figma master
components while preserving Magento/Hyvä runtime behavior.

### Expected Behavior

- Responsive 64px Header matches the Desktop/Mobile/Tablet source nodes.
- Search, account, wishlist, cart and language remain runtime-driven.
- Contextual homepage treatment transitions to a full-width solid sticky Header.
- Sticky behavior is controlled by Store View scoped Admin config and defaults on.
- Header uses exact locally committed Figma logo/icons via Hyvä custom icons.

### Constraints / Rules

- Follow `SPEC-TASK-DWW34M`; no vendor/third-party edits.
- Use Hyvä PHTML/Alpine/Tailwind v4 and Global Style semantic tokens.
- Keep Snowdog mega-menu/drawer implementation outside this task.

### Out of Scope

- Snowdog menu content/panels/drawer, Footer and page implementation.
- Site-wide dark mode and new search/account/cart business features.

### Acceptance Criteria

- [x] Header shell implements the canonical 375, 640 and 1440px geometry.
- [x] Runtime Header actions and the approved config contract pass local QA.
- [x] Sticky/contextual states and the accessibility contract are implemented.
- [x] Every Header asset passes local SVG and rendered-geometry checks.
- [x] Build/static/runtime validation evidence is recorded.

## Artifacts

- Specification: [SPEC-TASK-DWW34M](../../specs/SPEC-TASK-DWW34M-launchpad-core-header.md)
- Plan: [TASK-DWW34M Header Plan](../../plans/TASK-DWW34M-launchpad-core-header.md)
- Refinement plan: [Header shell and Snowdog level-1 refinement](../../plans/TASK-DWW34M-header-shell-menu-l1-refinement.md) (implemented 2026-09-28 — equal `minmax(0,1fr)` desktop columns, Snowdog level-1 theme override with hidden-scrollbar horizontal overflow, mobile/tablet geometry normalization; evidence in the refinement section of the validation record)
- Design analysis: [Header, Menu and Footer analysis](../../project/design/secomm-launchpad-header-menu-footer-analysis.md)
- Validation evidence: [Header validation](../../evidence/TASK-DWW34M/header-validation.md)
