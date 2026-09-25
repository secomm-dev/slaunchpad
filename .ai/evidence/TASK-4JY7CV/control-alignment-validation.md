# TASK-4JY7CV — Button, Checkbox and Radio alignment

Date: 2026-09-24

## Figma comparison

- Button page `2410:25885` is the parent design inventory. Current Button
  `2410:25972` and Base Button `2410:26973` are the canonical Button sources;
  Legacy Button `2410:25890` and Legacy Icon Button `2410:25923` are excluded.
- Base Button `2410:26973`: icon-only control/glyph matrix corrected for all
  five sizes; text and leading/trailing geometry was already aligned.
- Checkbox `2174:32588`: S/M/L, default, hover, checked, focus and disabled
  variants compared against the current component set and variable bindings.
- Radiobutton `2174:30745`: the same size/state matrix was compared.
- Shared Base CheckRadio `2174:33021` was used to verify common primitive
  geometry and state treatment.

## Implementation

- Native Magento/Hyvä checkbox and radio semantics are preserved.
- Figma mark vectors are embedded as local CSS data images; no runtime Figma
  URL is retained.
- Existing `role="switch"` rules remain later in the stylesheet and preserve
  switch geometry/checked rendering.

## Validation

- Tailwind CSS v4.3.2 build: PASS.
- Compiled form selectors contain brand-400 focus and brand-600 checked-hover.
- Compiled default form control is 20px with 10px Checkbox / 8px Radio marks.
- Static diff check: PASS.
- Local runtime default Checkbox: 20×20px, 1px gray-primary border and 4px
  radius: PASS.
- Local runtime Hyvä switch remains 36×20px with 2px border and round geometry:
  PASS.
