# Evidence template

Evidence for this skill follows the canonical Project AI Tool evidence model
(`.ai/evidence/evidence-policy.md` — "Done requires evidence"). Store artifacts
under `.ai/evidence/{task}/` using the canonical evidence types (command
output, test result, screenshot, log excerpt, code diff summary, deployment
checklist, QA confirmation, client approval, TL approval). This template maps
the skill's checks onto that model — it does not create a second evidence
system.

## Required sections

- **Outcome** — one-line final status with links to the ticket/feature record.
- **Approved inputs / design contract** — decision evidence (TL approval) for
  the approved color/brand/font allowlist, source priority, typography
  breakpoint, container/gutters, component state authority, accessibility
  responsibility and out-of-scope areas.
- **Token audit** — the transformer's `--report` JSON (command output +
  generated artifact): files, collection resolution, selected modes,
  audit-only modes, collisions/conflicts, unresolved or excluded tokens.
- **Deterministic generation** — the exact transformer command + version +
  contract hash, and proof that a repeat run is byte-identical (test result).
- **Generated output** — excerpt + location of the generated CSS and the
  theme-owned transformer/contract paths (code diff summary).
- **Source-owned mappings** — which `--ds-*` values map to which
  `--color-*`/`--form-*`/`--btn-*`/`--outline-*` Hyvä hooks (code diff summary).
- **Files changed + commands run** — full list with excerpts, not full logs.
- **Build + static validation** — Tailwind compile output, PHP/XML lint where
  templates changed (command output).
- **Runtime/visual/accessibility checks** — only what actually ran:
  runtime semantic values, responsive layouts, keyboard/focus, contrast,
  reflow, localization, representative pages, relevant module regressions
  (screenshots/test results). Do not claim browser, OAuth, newsletter,
  checkout/payment or WCAG coverage that was not executed.
- **Design follow-ups** — open design decisions (conflicts, suspicious
  tokens, deferred modes).

## Status taxonomy

For every claim, distinguish `implemented`, `validated`, `conditional`,
`deferred`, plus inference and unresolved decisions. Mask PII/secrets; keep
excerpts rather than full dumps. Historical POC evidence must link to the
current plan/result instead of presenting stale readiness as current state.
