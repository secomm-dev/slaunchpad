# review-hyva-template

> Function (VI). Hyvä stack. Lifecycle: **platform**. Review Hyvä template (.phtml) — Tailwind/Alpine convention, no business logic, Hyva layout XML format, render correctness.

## Purpose
Review một Hyvä template file — Tailwind utility class dùng đúng, Alpine component structure, không business logic trong .phtml, Hyva layout XML (không Luma format), view-model binding đúng.

## When to use
- Khi modify/create Hyvä template (.phtml, layout XML).
- Code review Hyva theme change.

## Trigger
- Prompt snippet: "Review Hyva template {file}: Tailwind/Alpine convention, no logic trong .phtml, Hyva layout XML, view-model binding. Read 09/10."

## Required inputs
- Template file path (.phtml / layout XML)

## Required project files to read
- `project-context/09_MAGENTO_MODULE_MAP.md` (Hyva-overridden module/view-model), `10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md`

## Required agents / skills / rules / hooks
- Agents: hyva-migration, magento-reviewer
- Skills: `magento-module-analysis` + dev skill `hyva-alpine-component`, `hyva-tailwind-section`
- Rules: `project-conventions-first.md`, `backward-compatibility.md`
- Hooks: `before-pr`

## Required memory / evidence
- Memory: `CONTINUOUS_LEARNING.md` (Hyva gotcha)
- Evidence: `.ai/evidence/{task}/hyva-template-review.md` (+ screenshot)

## Execution steps (11-step)
1. Context (09/10) 2. Memory (CONTINUOUS_LEARNING) 3. Rules (conventions) 4. Dev skills (hyva-*) 5. Agent (hyva-migration) 6. Research existing Hyva convention 7. Review: Tailwind utility (không inline CSS), Alpine x-data/x-init đúng, no business logic trong .phtml, layout XML Hyva format, view-model (`ArgumentInterface`) binding 8. Validate: render test (mobile/desktop), no jQuery 9. Evidence (review + screenshot) 10. Update memory 11. Next

## Output format
Hyva template review: Tailwind/Alpine convention + logic-in-template flag + layout XML format + render + verdict.

## Failure handling
- Business logic trong .phtml → move to view-model.
- Luma layout XML format → convert to Hyva.
- jQuery dependency → remove (Hyva = no jQuery).

## Related audits / standards
- Audits: Hyva, Magento, Code Quality
- Standards: HYVA_STANDARD (Alpine, Tailwind, ViewModel, Template, Performance), DEVELOPMENT, REVIEW
