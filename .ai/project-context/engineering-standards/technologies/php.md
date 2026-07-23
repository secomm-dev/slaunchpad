# PHP_STANDARD

> Technology standard — PHP. English. Extends shared standards. Applies to PHP backends (Magento/Laravel). Enforcement in `coding-rules/shared-rules.md` + `{stack}-rules.md`.

## Purpose / Scope / Applicability
PHP language-level engineering. Applies to all PHP code.

## Mandatory Rules
- PSR-12 (or project-declared standard); `declare(strict_types=1)`.
- Typed signatures (params + return); no silent type coercion.
- Null-safety: explicit null handling; null-coalescing where appropriate.
- No `eval`/unserialize on untrusted input; parameterized DB; no shell from untrusted input.

## Recommended Practices
- Use readonly properties / value objects for immutability.
- Prefer enums (PHP 8.1+) over magic constants.

## Anti-patterns
`eval`; `unserialize` on input; missing types; silent coercion; magic constants.

## Validation Checklist
- [ ] PSR-12 + strict_types; typed signatures
- [ ] Null handled explicitly; no `eval`/unsafe unserialize

## Related
**Agents**: developer, magento-reviewer · **Functions**: review-code, audit-code-quality · **Rules**: security-first · **Enforcement**: `coding-rules/shared-rules.md`
