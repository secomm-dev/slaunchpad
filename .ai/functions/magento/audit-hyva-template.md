# audit-hyva-template (Magento Hyvä)

> Function (VI guidance). Copy vào `.ai/functions/audit-hyva-template.md`. Magento Hyvä project only.

## Mục đích
Audit Hyvä theme/template — Tailwind/Alpine component convention, Hyvä checkout integration, layout XML khác biệt Luma, render correctness.

## Trigger
- Prompt snippet: "Audit Hyvä template {component/section}: Tailwind/Alpine convention, Hyva checkout impact, layout XML, render. Read 09/10."

## Required inputs
- Hyvä component/section/template path

## Required project files to read
- `project-context/09_MAGENTO_MODULE_MAP.md` (Hyva-overridden module/view-model), `10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md` (Hyva checkout)

## Dependencies
- Agent: hyva-migration, magento-reviewer
- Dev skill: `hyva-alpine-component`, `hyva-tailwind-section`
- Audit: Hyva (`audit-workflows.md` §10)

## Execution steps
1. Check Tailwind/Alpine convention (Hyva pattern, không Luma LESS).
2. Check Hyva checkout integration (không break payment).
3. Check layout XML (Hyva format khác Luma).
4. Check view-model binding + render correctness.
5. Rate S0–S3 + evidence + next action.

## Expected output
Hyva template audit: finding + convention deviation + checkout risk + next action.

## Evidence required
Report lưu `.ai/evidence/audit-hyva-template-{component}.md` (+ screenshot).

## Memory files to update
- `CONTINUOUS_LEARNING.md` (Hyva gotcha)

## Failure handling
- S0 (Hyva checkout break payment) → escalate Tier 2.

## When to improve/update
- Khi Hyva version change deprecate pattern → update + record.
