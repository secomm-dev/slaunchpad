# HYVA_STANDARD

> Technology standard — Magento Hyvä theme. English. Extends `magento.md` + shared standards. Enforcement in `coding-rules/magento-rules.md` (Hyvä section). Generated as `HYVA_STANDARD.md` when `stack_variant = magento-hyva`.

## Purpose / Scope / Applicability
Hyvä theme engineering (Tailwind + Alpine.js + Hyvä checkout). Applies to Magento Hyvä projects.

## Mandatory Rules
- **Alpine.js**: component state + behavior in Alpine; keep logic in `<script type="module/x-magento-init">` or Alpine `x-data`; no jQuery.
- **Tailwind**: use utility classes; follow the project Tailwind config/purge; avoid inline custom CSS where a utility exists.
- **ViewModel**: data via Hyva-compatible view-models (`ArgumentInterface`); JSON-serialize to frontend safely.
- **Templates**: `.phtml` renders Tailwind/Alpine; no business logic; Hyva layout XML format (not Luma).
- **Performance**: ship minimal JS (Hyvä's premise); lazy-load; avoid heavy frontend deps; respect Hyvä cache + page-cache tags.

## Recommended Practices
- Reuse Hyva child-theme conventions; follow Hyva checkout customization patterns.
- Test Tailwind purge doesn't strip used dynamic classes.

## Anti-patterns
jQuery; heavy JS bundles; Luma layout XML in a Hyva theme; business logic in `.phtml`; missing purge config; breaking Hyva checkout payment flow.

## Validation Checklist
- [ ] Alpine/Tailwind conventions; no jQuery; no heavy JS
- [ ] ViewModels Hyva-compatible; templates have no logic
- [ ] Hyva layout XML (not Luma); Tailwind purge correct
- [ ] Hyva checkout payment flow intact

## Related
**Agents**: hyva-migration, magento-reviewer · **Skills**: magento-module-analysis, magento-checkout-impact · **Functions**: implement-task, audit-code-quality, refactor-code, validate-theme-build · **Audits**: Hyva, Magento · **Enforcement**: `coding-rules/magento-rules.md` (Hyvä section)
