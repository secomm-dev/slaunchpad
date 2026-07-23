# MYSQL_STANDARD

> Technology standard — MySQL. English. Applies to MySQL databases (Magento/Laravel/middleware).

## Mandatory Rules
- Schema via migrations; foreign keys + constraints explicit; index hot columns.
- No `SELECT *` on hot paths; parameterized queries only; no string-concatenated SQL.
- Migrations backward-compatible (additive first; drop in a later release); test on staging with production-like volume.
- Pagination via keyset/cursor for large sets; avoid offset on big tables.

## Recommended Practices
- Use EXPLAIN for slow queries; prefer covering indexes; archive over delete for large data.

## Anti-patterns
`SELECT *`; missing indexes on hot columns; non-additive migrations; offset on huge tables; concatenated SQL.

## Validation Checklist
- [ ] Migrations additive + tested; FK + constraints + indexes
- [ ] No `SELECT *`; parameterized; keyset pagination on large sets

## Related
**Agents**: sa, developer · **Functions**: refactor-code, audit-performance · **Rules**: backward-compatibility · **Audits**: Performance · **Memory**: project-context/06 (schema risk)
