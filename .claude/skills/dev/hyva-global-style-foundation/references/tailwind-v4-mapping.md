# Tailwind CSS v4 and Hyvä mapping

Use this ownership order:

1. Immutable Figma export input.
2. Generated `--ds-*` source and semantic variables for approved production modes only.
3. Curated Tailwind `@theme` additions/overrides.
4. Existing Hyvä semantic/component hooks.
5. Low-specificity base styles.
6. Reusable components.
7. Scoped Magento/theme integrations.

Mapping rules:

- Source-only value: `--ds-*` under `:root`; do not create a utility without a stable consumer-facing meaning.
- Tailwind namespace: use `@theme` for approved `--color-*`, `--spacing-*`, `--radius-*`, `--shadow-*` and `--breakpoint-*` additions or overrides.
- Hyvä integration: map to existing `--color-*`, `--form-*`, `--btn-*` or `--outline-*` hooks before adding competing selectors.
- Base styles: use low-specificity rules for typography, focus, reduced motion and element defaults that no hook provides.
- Component/page styles: consume semantic aliases; do not hardcode palette shades into templates.

Production mode rules:

- Require an approved color mode and brand theme before generation.
- Emit only approved runtime modes. Keep dark and unused brands/fonts in the audit report, not dormant production selectors.
- Generate mobile typography as the base and desktop overrides at the approved/exported breakpoint.
- When a future child theme needs another brand, change its semantic mapping or generator decision rather than duplicating component CSS.

Import/cascade rules:

- Keep framework/module sources and generated ownership explicit.
- Ensure final runtime semantic aliases win over Hyvä compatibility defaults and legacy inline configuration.
- Production build scripts must never depend on AI-tooling or agent directories such as `.ai`, `.claude`, `.codex`, prompt/skill directories, or equivalent client-specific paths. The transformer and the token contract used by a storefront build live in theme-owned paths; builds never write audit evidence.
