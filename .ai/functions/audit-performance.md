# audit-performance

> Function (VI guidance). Copy vào `.ai/functions/audit-performance.md`.

## Mục đích
Run Performance audit — identify perf risk ở critical eCommerce path (checkout/catalog/search/cache) trước khi hit customer. Orchestrator cho Performance audit.

## Trigger
- Command: `/audit performance`
- Prompt snippet: "Run performance audit: N+1, missing index, cache key, blocking call ở hot path. Finding S0–S3 per audit-workflows §4."

## Required inputs
- Scope (pre-launch / slow page / scaling)

## Required project files to read
- `project-context/06`, `AGENTS.md` §12
- Checkout/catalog/search code, caching config, monitoring data (nếu có)

## Dependencies
- Agent: performance-reviewer, project-auditor
- Audit: Performance (`audit-workflows.md` §4)
- Skill: `magento-checkout-impact` (Magento), `shopify-theme-review` (Shopify theme perf)

## Execution steps
1. Identify hot path (checkout/payment/search/catalog).
2. Check N+1, missing index, `SELECT *`, cache key correctness, blocking external call.
3. Check large payload / unpaginated read.
4. Rate S0–S3 + evidence + next action.

## Expected output
Performance audit report: verdict + finding (S0–S3) + next action (index, move call off critical path, fix cache key).

## Evidence required
Report lưu `.ai/evidence/audit-performance-{date}.md` (+ page-speed data nếu có).

## Memory files to update
- `CONTINUOUS_LEARNING.md` (perf gotcha), `project-context/06`

## Failure handling
- S0 (checkout/payment regression) → block release; escalate.

## When to improve/update
- Khi perf pattern recurrent → thêm checklist; record.
