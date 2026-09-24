# Feature Spec — Hyvä Global Style Foundation for Secomm Launchpad Core

Specification ID: SPEC-TASK-6V8H2P
Feature ID: NONE
Specification Level: FULL
Specification Status: VALID — approved for implementation

> **Project**: Secomm Launchpad
>
> **External reference**: SLP-246
>
> **Stack**: Magento 2.4.8-p5 · Hyvä 3.x · Tailwind CSS v4 CSS-first
> **Canonical input**: [secomm-launchpad-global-style-foundation-input.md](../project/design/secomm-launchpad-global-style-foundation-input.md)

## 1. Objective

Tạo một Global Style Foundation deterministic và theme-owned cho
`Secomm/launchpad`, dùng native Figma Variables export làm immutable input. Lớp
foundation cung cấp semantic contract ổn định để Launchpad Core và các theme
ngành hàng tương lai tái sử dụng mà không gắn component vào raw Olive values;
foundation đồng thời sở hữu workflow custom icon dành riêng cho Hyvä.

## 2. Source priority

1. Quyết định đã được Designer/Tech Lead phê duyệt trong input/spec.
2. Immutable Figma Variables export trong `design-tokens/source/`.
3. Final Master Components.
4. Semantic variables và representative Ready-to-Dev frames qua Figma MCP.
5. Existing Hyvä/Tailwind hooks trong theme.
6. Hyvä/native patterns và FE proposal theo WCAG 2.2 AA.

Không sửa conflict âm thầm. Mỗi finding được phân loại thành `reuse`, `add`,
`normalize`, `conflict`, `unresolved` hoặc `excluded`.

## 3. Current-state analysis

### 3.1 Theme/build baseline

- Target: `app/design/frontend/Secomm/launchpad`, parent `Hyva/default`.
- Tailwind v4 CSS-first; entry `web/tailwind/tailwind-source.css`.
- Existing generated ownership:
  `generated/hyva-source.css` và `generated/hyva-tokens.css`.
- Existing Hyvä hooks already cover `--color-*`, `--btn-*`, `--form-*` và
  `--outline-*`; implementation must map into these hooks before adding selectors.
- Existing wrapper uses fixed `padding-inline: --spacing(6)` and does not yet
  implement the approved responsive 8/24px padding and override only the native
  80rem container tier to 1408px outer/1360px content.
- Existing `hyva.config.json` contains provisional primary/secondary values that
  will be superseded by the approved generated semantic mapping, without
  conflating Hyvä-generated files and Figma-generated files.

### 3.2 Export inventory and audit

Bundled skill auditor dry-run on 2026-09-19 returned:

| Check | Result |
|---|---:|
| JSON files | 14 |
| Mode-expanded token records | 1,100 |
| JSON errors | 0 |
| Conflicting duplicate Figma IDs | 0 |
| Normalized casing collisions | 0 |
| Incomplete modes | 0 |
| Invalid values | 0 |
| Non-canonical casing | 4 (`applications/Icon-Size`) |
| Suspicious CTA zero values | 12 (`cta` lg/xl) |
| Alias metadata reported unresolved by current transformer | 612 |

The 612 alias findings are not evidence that every semantic token is invalid.
Figma native export preserved `targetVariableName` and `targetVariableSetName`,
but exported target IDs are library/hash IDs that do not match local variable
IDs. The production transformer must validate and resolve an alias by canonical
collection/name fallback when ID resolution is unavailable. Missing or ambiguous
name resolution remains a hard conflict; no resolved color may be silently
flattened as a substitute for the alias chain.

### 3.3 Approved mode contract

| Role | Production | Excluded/deferred |
|---|---|---|
| Color | `light` | `dark` audit-only |
| Brand | `olive` | future child-theme brands |
| Font | `Inter` | other font modes |
| Typography | Mobile base; Tablet 768px; Desktop 1280px | none |

### 3.4 Approved responsive contract

```text
retain Hyvä/Tailwind container max-width rules below 80rem and from 96rem
outer max-width = 1408px only from 80rem through 95.999rem
mobile padding  = 8px
tablet/desktop padding = 24px at 768px and above
80rem-tier content = 1360px; native 96rem-tier content = 1488px
```

Tailwind defaults remain `640/768/1024/1280/1536`. Full-width shells keep
viewport width; inner content uses the container. Footer internal padding and
carousel bleed are component-level exceptions.

## 4. Required behavior

### 4.1 Token pipeline

- Source remains immutable under `design-tokens/source/`.
- Theme owns a dependency-free transformer and machine-readable production
  contract under `web/tailwind/`.
- Audit and generation are explicit scripts. Normal storefront build generates
  CSS but does not write `.ai/evidence`.
- Generated output has a distinct name/ownership from Hyvä generated files.
- Generation is deterministic and rejects normalized collisions, invalid values,
  incomplete approved modes and unresolved production aliases.

### 4.2 CSS layering

Order:

1. `:root` source/custom variables required for aliases.
2. Default `light` semantic values.
3. Default `olive` brand primitives; semantic brand values remain references.
4. `@theme` mappings only for approved semantic Tailwind namespaces.
5. Source-owned low-specificity base styles and Hyvä component hooks.

No runtime dark selector is emitted in this scope. The architecture may retain a
future selector contract, but no unused dark CSS is shipped as approved output.

### 4.3 Typography and fonts

- Inter self-hosted WOFF2 from the approved Google Webfonts Helper source.
- Subsets: latin + vietnamese; styles: normal + italic; weights 400/500/600/700.
- Current typography consumes 400/500/700; 600 remains an approved available face.
- Use `font-display: swap`; preload only the agreed critical face after asset-size
  inspection. Retain the Inter license notice with assets.
- Exported typography names become semantic classes/variables, not raw arbitrary
  values repeated throughout templates.

### 4.4 Global semantics

- Map page/surface/text/border/interaction/status/focus roles from the approved
  `light` export into stable `--ds-*`, Tailwind `--color-*` and Hyvä hooks.
- Expose semantic spacing/radius/shadow/typography APIs only where reusable.
- Do not expose full primitive palettes simply because they are exported.
- Effects combine geometry from `effects.tokens.json` with approved color-mode
  effect colors.

### 4.5 Button and Form

- Button public variants: primary, secondary, tertiary.
- Designed states: default, hover, active, focus-visible, disabled.
- Designed structures: text, leading/trailing icon, icon-only and round icon-only.
- `loading` and generic `full-width` variants are not global Button API in scope.
- Final Input source is component set `2410:14473`: types Default, Leading
  dropdown, Trailing dropdown and Leading text; optional leading/trailing icons,
  label and hint; Placeholder/Hover/Active/Filled/Focus/Disabled states; and
  None/Error/Warning/Success feedback.
- Final Textarea source is component set `2410:14352`, with the same state and
  feedback model, optional label/hint, 160px designed field height and semantic
  label/body typography.
- Checkbox `2174:32588` and Radio `2174:30745` remain approved native-control
  sources. Legacy Input sets `2410:14324` and `2410:14331` are excluded.
- No standalone Select master exists in `2410:14322`. Native Select may consume
  the shared field/token contract, but Select-specific visuals remain gated.
- Implementation maps the design matrix into semantic CSS hooks and native
  Magento/Hyvä markup; it must not generate one selector per Figma permutation.

### 4.6 Hyvä custom icons

- Canonical project source is Figma icon-library node `5:28677`.
- Global Style owns the naming, storage, export, SVG audit, accessibility and
  Hyvä rendering workflow used site-wide.
- Assets are added on demand by consuming components; the complete library is
  not copied into the theme by default.
- Figma semantic names map to
  `Hyva_Theme/web/svg/{style}/{semantic-name}.svg`; filenames do not carry size
  suffixes unless a verified optical-artwork difference requires an exception.
- The Launchpad Core audit identifies `2001:10642` as fill and `2001:13716` as
  outline. Fill representative geometry scales uniformly; outline representative
  stroke weight changes by size, so exact-size outline assets use a size suffix.
- Monochrome SVGs use `currentColor`, explicit render dimensions and
  `Hyva\\Theme\\ViewModel\\SvgIcons`; icon fonts and runtime Figma URLs are
  prohibited.
- POC assets are reusable only after semantic-name and vector-glyph comparison
  against the Launchpad Core library or consuming component instance.
- Detailed workflow: [HYVA_CUSTOM_ICON_WORKFLOW.md](../guides/HYVA_CUSTOM_ICON_WORKFLOW.md).

## 5. Acceptance criteria

The executable AC set is the embedded Mini-Spec AC-001…AC-011 in
`TASK-6V8H2P`; implementation evidence must map every result back to those ACs.

## 6. Validation contract

- Local DDEV; store views `default/vi_VN` and `launchpad_en/en_US`.
- Routes: `/`, `/customer-service`, customer auth/form routes, `/living-room`,
  `/atlas-pouf`, `/checkout/cart/`; `/checkout/` regression-only.
- Browsers: latest stable Chrome/Safari/Firefox/Edge plus Safari iOS and Chrome
  Android at QA time.
- Viewports: 320, 375, 768, 1024, 1280, 1440, 1600 and 1920 CSS px.
- Checks: deterministic output, Tailwind build, semantic resolution, reflow,
  keyboard/focus, contrast, reduced motion, both locales and third-party surfaces.

## 7. Risks and unresolved decisions

| ID | Risk/decision | Implementation effect | Owner |
|---|---|---|---|
| R-01 | Native export alias IDs do not match local IDs | Transformer must resolve unambiguously by collection/name fallback | Tech Lead |
| R-02 | Older Button nodes were superseded by the current Button page | Use current Button set `2410:25972` and Base Button `2410:26973`; exclude Legacy groups | Designer |
| R-03 | Five component sizes do not map one-to-one by name to four applications modes | Final Master Component owns S/M/L/XL/2XL dimensions; reuse matching exported values without inventing a name mapping | Designer/TL |
| R-04 | Button controls at 32/36/40px conflict with project 44px touch baseline | Preserve visual size and provide a minimum 44×44px interaction area | Designer/TL |
| R-05 | Select must be resolved within the supplied Form Elements page | Audit the Select component child under `2410:14322` before finalizing its semantic API | Designer |
| R-06 | Magento CLI currently fails on missing VietNamAddress importer class | Restore local runtime health before browser/build evidence that needs Magento bootstrap | Technical owner |
| R-07 | Icon page has two top-level groups named `Fill Icon Sets` | Audit ownership, styles, vector equality and canonical naming before adding assets | Designer/FE |

## 8. Out of scope

- Header, Menu, Footer and page composition.
- Runtime dark mode and non-Olive child brand implementation.
- Select-specific visual variants beyond the shared native field contract until
  a final Select master is supplied.
- Checkout redesign, CMS authoring or third-party module modification.
- Bulk export of the complete icon library and assets for components outside the
  current implementation scope.
- Fixing unrelated Magento bootstrap/module defects under this ticket.

## 9. Approval gate

This spec is `VALID` after:

1. Designer accepts visual/input decisions and documented deferrals.
2. Tech Lead accepts transformer alias fallback, public token API and unresolved
   Button/Select handling.
3. Acceptance criteria and draft plan are reviewable under `SLP-246`.

Gate closed by user confirmation for the Design source-of-truth and Tech Lead
technical approval under `SLP-246` on 2026-09-22. Scope amendment adding the
Hyvä Custom Icon Foundation was explicitly approved by the user on 2026-09-23;
AC-011 passed after the Launchpad Core structure and representative vectors were
audited on 2026-09-23.
