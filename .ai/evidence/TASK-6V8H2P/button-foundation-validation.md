# Button Foundation Validation — 2026-09-23

## Source evidence

- Canonical Button page: `2410:25885`.
- Current Button set: `2410:25972`; Base Button set: `2410:26973`.
- Legacy Button groups on the page are excluded.
- Verified variants: Primary, Secondary, Tertiary and Transparent.
- Verified states: Default, Hover, Focus, Active and Disabled.
- Verified master sizes:
  - S: 36px; 16px × 8px padding; Label M; 16px icon.
  - M: 40px; 20px × 10px padding; Label M; 16px icon.
  - L: 44px; 20px × 10px padding; Label L; 16px icon.
  - XL: 48px; 24px × 12px padding; Label L; 16px icon.
  - 2XL: 60px; 32px × 16px padding; Label XL; 24px icon.

## Conflict disposition

The earlier “2XL has no approved token” finding is superseded. The final Master
Component is authoritative for the five public sizes. The four Figma Variable
application modes do not form a complete one-to-one name mapping, so production
does not rename or synthesize a fifth exported mode. Matching semantic values are
reused; the complete Button size API is component-owned.

## Automated validation

- `npm run tokens:audit`: PASS — 14 files, 1,100 records, zero unresolved
  production aliases, zero invalid values and zero normalized casing conflicts.
- `npm run build`: PASS twice.
- Deterministic token CSS SHA-256 (two runs):
  `0b6825343c62d129dbd8f619334c1ec3b6243053c41e933fc0af764089cae71b`.
- Deterministic production CSS SHA-256 (two runs):
  `88cac7bfe80121518d6e3aeef9fdc9ac0a1fe0de0e16bda995bb6d85948e6b9b`.
- `git diff --check`: PASS.

## Remaining validation

Runtime visual, keyboard, contrast and responsive browser evidence remains part
of the final representative-page QA gate.

The project validator could not run because the checked-in
`.ai/bin/project-ai-validate` currently has an unmatched quote near line 831.
This is a toolkit/runtime defect outside the theme implementation scope.
