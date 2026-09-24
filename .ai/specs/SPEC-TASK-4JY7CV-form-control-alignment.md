---
id: SPEC-TASK-4JY7CV
type: specification
title: Align Button Checkbox and Radio foundations with current Figma masters
feature_id: NONE
specification_level: FULL
status: VALID
owner: TASK-4JY7CV
external_ref: SLP-246
created: 2026-09-24
updated: 2026-09-24
---

# SPEC-TASK-4JY7CV — Form control alignment

Specification ID: SPEC-TASK-4JY7CV
Feature ID: NONE
Specification Level: FULL

## Goal

Align the existing Launchpad Global Style Button, Checkbox and Radio foundations
with the current canonical Figma masters without changing native Magento/Hyvä
form semantics.

## Design authority

- Parent Button page/inventory: `2410:25885`.
- Current Button set: `2410:25972`.
- Base Button: `2410:26973`.
- Checkbox: `2174:32588`.
- Radiobutton: `2174:30745`.
- Shared Base CheckRadio primitive: `2174:33021`.
- Legacy Button `2410:25890` and Legacy Icon Button `2410:25923` are excluded.

## Expected behavior

- Button text/leading/trailing variants retain S/M/L/XL/2XL geometry.
- Icon-only and round icon-only use their Figma-specific control and glyph sizes.
- Checkbox and Radio retain native inputs and expose S/M/L at 16/20/24px.
- Default, hover, checked, focus and disabled states use current semantic tokens,
  exact master geometry and a 4px focus ring.
- Existing `role="switch"` controls keep the separate Hyvä switch contract.

## Acceptance criteria

- Button icon-only: S 32/16, M 40/16, L 44/24, XL 48/24 and 2XL 60/32
  (control/glyph pixels).
- Checkbox/Radio focus border uses brand-400; selected hover remains brand-600.
- Selected marks scale per control size and disabled states match the masters.
- Tailwind build and static diff checks pass.

## Out of scope

- Component/page-specific selectable cards, swatches and toggle-button groups.
- Replacing native controls with JavaScript widgets.
