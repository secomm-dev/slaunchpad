---
name: hyva-global-style-foundation
description: Turn approved Figma foundations, Figma variables, DTCG-style token exports and master components into a deterministic Magento 2 Hyva global-style foundation using Tailwind CSS v4 CSS-first. Use for design-token import, semantic token mapping, global style foundation, fonts, semantic colors, typography, spacing, containers/gutters, global Button/Form/Header/Footer styling, and representative-page validation. Only for Hyva themes on Tailwind v4 CSS-first — NOT for Tailwind v3 or tailwind.config.js themes, Luma-only themes, non-Magento projects, child-theme scaffolding (hyva-child-theme), full page composition, arbitrary Figma-to-page generation, checkout customization, generic theme development, AI design generation, or PoC 2 Figma design creation.
requires: hyva-exec-shell-cmd, hyva-theme-list, hyva-compile-tailwind-css
---

# Hyvä Global Style Foundation

Establishes the design-system content of a Hyvä theme: design tokens, fonts,
semantic colors, typography, spacing, containers/gutters, Button, Form,
supporting primitives, and Header/Footer visual foundations, validated on a
representative page.

**Scope boundary** — `hyva-child-theme` owns the theme *structure*: child-theme
scaffolding, inheritance, layout handles, parent template overrides,
template/layout integration. This skill owns the theme's design-system
*content*: what the theme compiles. A workflow that creates a new child theme
and then builds its foundation invokes both — in sequence, never merged.
Page-level composition stays out of scope (future capability, no coupling).

**Applicability** — Magento 2 AND Hyvä frontend AND Tailwind CSS v4 CSS-first.
Not applicable (record the reason, do not activate) for: Hyvä + Tailwind v3,
Luma-only themes, non-Magento projects, generic Figma page-design tasks. See
[compatibility.md](references/compatibility.md).

## Workflow — first run

Follow this order; do not treat repository cleanup as a design-system phase.

1. Read project governance and inspect the child theme, parent Hyvä templates, Tailwind entry (`tailwind-source.css`), Hyvä config, generated ownership and existing global/component hooks. Confirm the theme is Tailwind v4 CSS-first; if it still uses `tailwind.config.js` (v3), stop — record the skill as inapplicable with that reason.
2. Inventory node-specific Figma inputs: Foundations, Master Components, representative responsive frames, assets and exported variables. Use design context per manageable node; split oversized frames. Treat screenshots/variable definitions as supplemental evidence. Figma-generated reference code is evidence, not Magento/Hyvä production code.
3. Establish an explicit design contract before CSS implementation: default font, production color/brand/mode allowlist, semantic roles, typography breakpoint, container/gutters, component state authority, accessibility responsibility, validation page and out-of-scope areas. Block only decisions that would materially change the system.
4. Store immutable exports in a theme-owned source directory. Copy `assets/templates/token-contract.yaml` to a theme-owned contract path (e.g. `<theme>/web/tailwind/tokens/contract.yaml`) and adapt it to the project's actual export layout — collection locations, mode names and audit heuristics live in that contract, never in this skill or the transformer. Read [token-contract.md](references/token-contract.md), then audit before generating:

   ```bash
   node scripts/token-transformer.mjs --contract <theme>/web/tailwind/tokens/contract.yaml --dry-run --report <audit.json>
   ```

5. Classify findings as `reuse`, `add`, `normalize`, `conflict`, `unresolved` or `excluded`. Never mutate exports or silently resolve a collision. Keep unapproved modes audit-only.
6. Read [tailwind-v4-mapping.md](references/tailwind-v4-mapping.md). Copy/adapt the dependency-free transformer into a production-owned theme path; production builds must not depend on this skill or any AI-tooling directory. Generate only the modes approved in the contract:

   ```bash
   node <theme>/web/tailwind/scripts/token-transformer.mjs \
     --contract <theme>/web/tailwind/tokens/contract.yaml \
     --output <theme>/web/tailwind/generated/design-tokens.css \
     --report <audit.json>
   ```

   The run also writes `<generated-dir>/design-tokens.manifest.json` — the ownership + provenance record for what was generated.
7. Map generated `--ds-*` values into curated Tailwind v4 namespaces and existing Hyvä hooks in a **source-owned** mapping layer. Keep source-owned base styles, component APIs and Magento integration in separate layers.
8. Implement in order: font/semantic foundations → container → Button/Form → consumed supporting primitives → Header/Footer → representative page. Preserve Magento behavior (authentication, checkout, payment) and override the minimum parent templates.
9. Validate deterministic generation (repeat run must be byte-identical) and build, runtime semantic values, PHP/XML, responsive layouts, keyboard/focus/contrast/reflow, localization, representative pages and relevant module regressions — only claim checks that actually ran.
10. Record the project's design decisions in `.ai/project/design/hyva-global-style-foundation.yaml` (from `assets/templates/project-config.yaml`; theme paths, token source, approved modes with rationale, transformer/contract/generated paths, validation pages). This governance record is AI-facing only; production builds read the theme-owned contract, never this file. Never invent unresolved values — leave them TBD and report them.
11. Produce evidence with [evidence-template.md](references/evidence-template.md), following the canonical evidence policy (`.ai/evidence/{task}/`). Separate `implemented`, `validated`, `conditional`, `deferred` and `not-executed`; never claim browser, OAuth, newsletter, checkout/payment or WCAG coverage that was not executed.

## Workflow — subsequent runs (change-only)

Never repeat first-run work when a foundation already exists.

1. Read the existing theme-owned contract and `<generated-dir>/design-tokens.manifest.json` (generator versions, contract sha256, per-file input checksums, approved modes).
2. Audit the new/changed token input (`--dry-run --report`), then compare against the manifest: changed input checksums, changed contract, changed versions.
3. Regenerate the CSS only for what changed — the transformer is deterministic, so regeneration with identical inputs produces zero diff; with changed tokens it produces the minimal generated-layer diff. **Do not** rebuild Header, Footer, Button or Form when their source-owned implementation does not require structural changes; a token value change flows through `--ds-*` variables without touching source-owned files.
4. Verify the diff touches only `generated`-ownership files (plus intentional source-owned mapping edits); preserve unrelated templates and business behavior.
5. Update the manifest-backed evidence (new audit report + manifest) and the `.ai/project/design/` record when decisions changed.

## Generated-file ownership

| Class | Examples | Rules |
|---|---|---|
| Immutable input | Figma/DTCG exports (`*.tokens.json`) | Never mutate; never normalize in place; transformer verifies and reports only |
| Generated | `design-tokens.css`, `design-tokens.manifest.json`, audit reports | Deterministic; safe to replace; never manually edit; carry generator provenance in the manifest |
| Source-owned | semantic Tailwind/Hyvä mapping layer, component API, theme-specific source styles | AI/developer may modify; regeneration must never blindly overwrite |

## Versions

`skill_version` (dev-skill-manifest.yaml + the project's `applied_capabilities`
stamp), `contract_schema_version` (contract `schema_version`, emitted in the
manifest), `transformer_version` (`TRANSFORMER_VERSION`, in the manifest and
audit report). Update semantics — doc-only vs behavior vs transformer vs
contract migration — are defined in [versioning.md](references/versioning.md).
Retrofit distinguishes update types from these versions; never blind-replace.

## Hard rules

- Do not create `tailwind.config.js` in a Tailwind v4 CSS-first theme.
- Do not edit exports or generated CSS manually.
- Do not emit or activate dark/brand/font modes outside the approved production allowlist in the project contract; dark mode stays audit-only unless explicitly production-approved.
- Do not expose every primitive as a utility; publish stable semantic meanings only.
- Use `--ds-*` for source/project tokens and existing `--color-*`, `--form-*`, `--btn-*` and `--outline-*` hooks for Hyvä integration.
- Preserve aliases where runtime theming semantics matter; do not flatten them into arbitrary component values.
- Keep production scripts and the production contract theme-owned, deterministic, path-appropriate and independent of AI tooling (`.ai`, `.claude`, `.codex`, prompt/skill directories).
- Exclude documentation-only, empty or suspicious component tokens until a design decision resolves them (contract `audit.rules` + `exclude.paths`).
- Treat Figma-generated reference code as evidence, not Magento/Hyvä production code.
- Keep page composition scoped; a representative page validates the foundation but does not become part of the global API.
- Preserve authentication, checkout and other business behavior while changing visual structure.

## Adapters

Canonical behavior lives in this `SKILL.md`, `references/`, `assets/` and
`scripts/`. `agents/openai.yaml` is OpenAI/Codex adapter metadata only
(display name + default prompt) — never a second source of truth. Codex and
GitHub Copilot reach the same capability via natural-language routing in
`codex/AGENTS.md` / `.github/copilot-instructions.md`.
