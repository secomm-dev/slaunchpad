# performance-impact-review

> Function (VI). Performance-sensitive conditional. Lifecycle: **platform**. Comprehensive performance impact assessment cho một change — hot path, query, cache, payload, external call, regression risk.

## Purpose
Assess performance impact toàn diện của một change trên critical path — query (N+1), cache (key/invalidation), payload (size), external call (latency), blocking (sync), regression risk + recommendation.

## When to use
- Change touch checkout/catalog/search/payment hot path.
- Pre-launch performance gate.
- Feature with performance risk (S6).

## Trigger
- Prompt snippet: "Performance impact review cho {change}: hot path, query, cache, payload, external call, regression risk. Read 06 + PERFORMANCE_STANDARD."

## Required inputs
- Change (diff/feature) + scope

## Required project files to read
- `project-context/06`, `AGENTS.md` §12, `.ai/project-context/engineering-standards/PERFORMANCE_STANDARD.md`

## Required agents / skills / rules / hooks
- Agents: performance-reviewer, sa
- Skills: `magento-checkout-impact` (Magento), `shopify-theme-review` (Shopify)
- Rules: `production-readiness.md`, `engineering-standards-enforcement.md`
- Hooks: `before-deploy`

## Required memory / evidence
- Memory: `CONTINUOUS_LEARNING.md` (perf gotcha), `project-context/06`
- Evidence: `.ai/evidence/{task}/perf-impact-review.md`

## Execution steps (11-step)
1. Context (06/PERFORMANCE_STANDARD) 2. Memory 3. Rules 4. Skill 5. Agent 6. Research change scope on hot path 7. Review: query (N+1?), cache (key/invalidation?), payload (size/pagination?), external call (latency/blocking?), regression (baseline?) 8. Validate: severity + critical path impact 9. Evidence 10. Memory 11. Next: load-test nếu critical path

## Output format
Performance impact report: hot-path finding (query/cache/payload/external/regression) + severity (S0–S3) + recommendation + verdict.

## Failure handling
- S0 (checkout/payment regression) → block release.
- N+1 on hot path → fix before merge.

## Related audits / standards
- Audits: Performance
- Standards: PERFORMANCE_STANDARD, ARCHITECTURE_STANDARD, DEVELOPMENT (perf-coding) + tech (mysql/cache)
