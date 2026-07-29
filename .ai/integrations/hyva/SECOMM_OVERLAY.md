# Secomm Hyvä Skill Overlay

This is the **shared** Secomm governance overlay applied to every official Hyvä AI
skill imported from `hyva-themes/hyva-ai-tools`. It is Secomm-owned and
intentionally thin: upstream skill bodies stay byte-identical under
`skills-source/dev-skills/magento/hyva-*/`; this overlay adds only the
organizational constraints the upstream skills do not carry.

It is propagated to projects and reinforced by the project's own
`project-context/CODING_RULES.md` + `AGENTS.md §7.2` (Hyvä additions) for
`magento-hyva` stacks. It does **not** restate the upstream skill.

## Rule priority

1. Project safety rules in `AGENTS.md`, `project-context/`, and approved specs/tickets.
2. This Secomm Hyvä overlay.
3. Official upstream Hyvä skill guidance.

When upstream guidance conflicts with a project constraint, **stop before risky
changes, report the conflict, and follow the project safety constraint**. Do not
edit upstream-owned files to hide a conflict; record which rule controlled.

## Required operating rules

- **Inspect before changing**: read the relevant `project-context/` subset, existing module/theme files, and the persisted Hyvä capability record before generating code.
- **Extension-first Magento**: plugins, layout handles, ViewModels, DI, and theme overrides before modifying vendor/third-party code.
- **Respect theme & store-view boundaries**: default work targets the project's active Hyva child theme (e.g. `app/design/frontend/<Vendor>/<theme>/`) unless the task explicitly selects another theme. Respect Hyva↔Luma fallback ownership — do not apply Hyva frontend skills to a change that runs under Luma fallback unless it crosses a Hyva-owned boundary.
- **Tailwind CSS v4 is CSS-first**: use `tailwind-source.css` with `@theme`/`@source`; do **not** create `tailwind.config.js`.
- **CSP compatibility**: Alpine/frontend work must be CSP-safe by default (register inline scripts per Hyva CSP requirements; no inline event handlers that violate CSP).
- **Keep in scope**: accessibility, responsive behaviour, Core Web Vitals, cache safety, FPC compatibility.
- **No direct production modification**; no changes to payment/checkout/shipping/order/customer-PII/DB-schema/deployment without the escalation in `AGENTS.md §11–§12`.
- **Never print secrets** from `auth.json`, env vars, Composer credentials, deployment config, or the private Hyva Packagist repository.
- **Shell commands** (`hyva-exec-shell-cmd` and any skill using it): distinguish read-only inspection from mutation; show the exact command before risky execution; preserve the project's Warden/DDEV/docker/local wrapper; do not assume a command succeeded — validate output/side-effects; refuse unsafe path traversal; no unreviewed `curl | sh`; no destructive command without explicit task need; no production mutation by default.
- **Focused validation + evidence**: use the lowest sufficient validation level (L1→L2→L3) and save evidence under `.ai/evidence/`.
- **Quota / no loops**: reuse persisted Hyvä detection until an invalidation file changes; do not re-invoke `hyva-theme-list` / `hyva-exec-shell-cmd` repeatedly within one task; do not rescan Composer/themes per step.

## Utility-skill guardrails

Utility skills (`hyva-exec-shell-cmd`, `hyva-theme-list`, `hyva-compile-tailwind-css`,
`hyva-cms-components-dump`) are composable dependencies. Use them on demand and
cache their result within a task; do not expose them as mandatory everyday
user-facing commands.
