# query-impact-review

> Function (VI). Performance-sensitive conditional. Lifecycle: **platform**. Query impact review — N+1, missing index, SELECT *, pagination, EXPLAIN analysis.

## Purpose
Review DB query impact của một change — N+1 (looped query), missing index on hot column, `SELECT *`, unpaginated bulk read, EXPLAIN analysis cho slow query.

## When to use
- Change touch DB query (collection/model/Eloquent/raw SQL).
- Slow-query investigation.
- Pre-launch DB performance gate.

## Trigger
- Prompt snippet: "Query impact review cho {change}: N+1, missing index, SELECT *, pagination, EXPLAIN. Read MYSQL_STANDARD + 06."

## Required inputs
- Change (query/collection/model)

## Required project files to read
- `project-context/06`, `.ai/project-context/engineering-standards/technologies/mysql.md`

## Required agents / skills / rules / hooks
- Agents: performance-reviewer, sa
- Rules: `production-readiness.md`
- Hooks: `before-deploy`

## Required memory / evidence
- Memory: `CONTINUOUS_LEARNING.md`, `project-context/06`
- Evidence: `.ai/evidence/{task}/query-impact.md` (+ EXPLAIN output)

## Execution steps (11-step)
1. Context (06/mysql standard) 2. Memory 3. Rules 4. Agent 5. Research query pattern 6. Identify: N+1 (looped find?), index (hot column indexed?), SELECT * (unnecessary column?), pagination (offset vs cursor?), bulk read (unbounded?) 7. Run EXPLAIN cho slow query (staging) 8. Validate: severity + recommendation (eager-load/add index/remove SELECT */paginate) 9. Evidence (EXPLAIN + before/after) 10. Memory 11. Next

## Output format
Query impact: N+1 finding + index gap + SELECT * + pagination + EXPLAIN + recommendation.

## Failure handling
- N+1 on hot path (S0/S1) → fix (eager-load/batch) before merge.
- Missing index on hot column → add migration.
- Unbounded bulk read → paginate.

## Related audits / standards
- Audits: Performance
- Standards: PERFORMANCE_STANDARD, technologies/MYSQL_STANDARD, DEVELOPMENT (perf-coding)
