# check-cache-impact (Magento)

> Function (VI guidance). Magento project only.

## Mục đích
Check cache impact của một change — full-page cache (Varnish), block cache key, hole-punching, customer-group cache, indexer invalidation. Ngăn stale price/content.

## Trigger
- Prompt snippet: "Check cache impact của change {diff}: FPC, block cache key, customer-group cache, indexer invalidation, stale risk."

## Required inputs
- Change diff (pricing/block/indexer)

## Required project files to read
- `project-context/11_CRON_QUEUE_INDEXER_CACHE.md`, `06`, `10`

## Dependencies
- Agent: performance-reviewer, magento-reviewer
- Skill: `magento-checkout-impact` (nếu checkout/pricing)
- Rule: `backward-compatibility.md`

## Execution steps
1. Identify cache type affected (FPC, block, layout, full text, customer-group).
2. Check cache key include mọi affecting param (customer group, store, date).
3. Check hole-punching (Hyva/Luma) correctness.
4. Check indexer invalidation (price/catalogrule/search) — stale risk.
5. Finding + verdict.

## Expected output
Cache impact report: cache type affected + key correctness + stale risk + fix.

## Evidence required
Report lưu `.ai/evidence/{task}/cache-impact.md`.

## Memory files to update
- `CONTINUOUS_LEARNING.md`, `project-context/06` (cache risk), `11`

## Failure handling
- Stale price/content risk (S0/S1) → fix cache key/indexer trước merge.

## When to improve/update
- Khi cache pattern mới (e.g., new cache type) → record.
