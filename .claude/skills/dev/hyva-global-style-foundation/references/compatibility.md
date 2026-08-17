# Compatibility matrix

Honest compatibility claims only — do not overclaim. Statuses below are the
skill's applicability, not a promise of validation coverage; per-environment
regression checks still follow the evidence template.

| Environment | Expected status |
|---|---|
| Magento 2 + Hyvä + Tailwind v4 CSS-first | **Supported** (the skill's target environment) |
| Magento 2 + Hyvä + Tailwind v3 (`tailwind.config.js`) | **Not applicable** — activation is blocked by the `tailwind_v4` feature-flag gate; migrate the theme to v4 CSS-first first, then re-detect |
| Magento 2 Luma (no Hyvä theme) | **Not applicable** — `hyva-active` classification absent; no Hyva frontend skill applies (Hyvä↔Luma fallback boundary) |
| Non-Magento projects | **Not applicable** — `stack: magento`, `technology: hyva` |
| Hyvä Checkout (React-based) | **Global foundation only unless explicitly scoped** — the foundation styles global surfaces; checkout-specific customization is out of scope (Tier 2 escalation per project AGENTS.md) |
| Third-party Hyvä-compatible modules | **Supported but regression validation required** — third-party modules consuming global tokens/hooks must be re-validated on representative pages after regeneration |
| Multi-brand token modes | **Supported only for approved modes** — one production allowlist per project contract (`generation.approved_modes`); other exported brand modes stay `audit-only` |
| Dark mode / alternate color modes | **Audit-only unless explicitly production-approved** — never emitted as dormant runtime selectors just because the export contains them |
| Generic Figma page design / page composition / PoC 2 design generation | **Out of scope** — do not activate this skill for those tasks |

## Applicability detection (summary)

Applicable when, and only when: `Magento 2` AND `Hyvä frontend`
(classification `hyva-active` / `hyva-migration-planned`) AND feature flag
`tailwind_v4` (CSS-first v4 markers in the theme's `web/tailwind/`, no
`tailwind.config.js`). Implemented in `generator-rules/capability-detection-rules.md`
(clean-init) and the `retrofit_hyva()` detection in `bin/project-ai-upgrade`
(retrofit) — one rule, two synchronized code paths. Tailwind v3 projects get
the skill recorded as inapplicable with the reason ("requires Tailwind v4
CSS-first"), never a silent skip.
