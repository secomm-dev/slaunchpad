# cache-impact-review

> Function (VI). Performance-sensitive conditional. Lifecycle: **platform**. Cache impact review — cache type, key correctness, invalidation, stale risk, hole-punching.

## Purpose
Review cache impact của một change — FPC/block cache key, customer-group cache, indexer invalidation, hole-punching (Hyva/Luma), stale risk.

## When to use
- Change touch pricing/catalog/block/indexer/cache config.
- Pre-launch cache validation.
- Stale-content bug.

## Trigger
- Prompt snippet: "Cache impact review cho {change}: cache type, key correctness, invalidation, stale risk. Read 11 (Magento cache/cron/indexer)."

## Required inputs
- Change (pricing/block/indexer/cache config)

## Required project files to read
- `project-context/11_CRON_QUEUE_INDEXER_CACHE.md` (Magento), `06`

## Required agents / skills / rules / hooks
- Agents: performance-reviewer, magento-reviewer
- Skills: `magento-checkout-impact` (if pricing)
- Rules: `backward-compatibility.md`
- Hooks: `before-deploy`

## Required memory / evidence
- Memory: `CONTINUOUS_LEARNING.md`, `project-context/06`/`11`
- Evidence: `.ai/evidence/{task}/cache-impact.md`

## Execution steps
1. Context (11/06) 2. Memory 3. Rules 4. Skill 5. Agent 6. Research cache type affected 7. Review: cache key (include customer-group/store/date?), invalidation (correct tag? indexer?), hole-punching (Hyva dynamic block?), stale risk (what goes stale?) 8. Validate: severity + stale scenario 9. Evidence 10. Memory 11. Next

## Output format
Cache impact: type affected + key correctness + invalidation + stale risk + fix.

## Failure handling
- Stale price/content (S0/S1) → fix cache key/indexer before merge.
- Missing cache tag → add.

## Related audits / standards
- Audits: Performance, Magento
- Standards: PERFORMANCE_STANDARD, MAGENTO_STANDARD (Cache), ARCHITECTURE (Caching pattern)
