# CODING_STANDARD

> Engineering standard — Coding (Coding Principles + Clean Code + OOP + Naming, consolidated). English. The enforcement-grade `[BLOCK]`/`[WARN]` items live in `CODING_RULES.md`; this doc is the rationale + depth.

## Purpose / Scope / Applicability
The baseline quality bar for all code. Applies to every change. Foundational subsets get their own docs: `SOLID_STANDARD`, `COMMENT_STANDARD`, `DESIGN_PATTERN_STANDARD`.

## Mandatory Rules
- **Read the room**: follow existing project conventions + declared standard (PSR-12 for PHP, Airbnb for JS/TS) when this standard is silent.
- **Single responsibility**: each class/function does one thing.
- **Readable over clever**: optimize for the next reader; no implicit/surprising behavior (Least Surprise).
- **Naming**: intention-revealing, searchable, consistent with the codebase; no abbreviations except domain-standard ones.
- **OOP**: encapsulate state; prefer composition; polymorphism over type-switching; no god objects.
- **Small units**: functions ≤ ~30 lines, ≤ ~3 params; files ≤ a few hundred lines; split when growing.
- **No dead code**: remove commented-out code, unused imports/vars; every `TODO/FIXME` carries a ticket id + owner.

## Recommended Practices
- Pure functions where feasible; immutability where it reduces surprise.
- Fail fast: validate at boundaries; return early.
- Domain language in names (ubiquitous language for DDD areas).

## Anti-patterns
- Magic numbers/strings; boolean flag params; deep nesting; long argument lists; inconsistent naming; clever one-liners; dead/commented code; uncommented TODO.

## Validation Checklist
- [ ] Follows project conventions / declared standard
- [ ] Names are intention-revealing + consistent
- [ ] Functions/classes small + single-responsibility
- [ ] No dead/commented code; TODOs have tickets
- [ ] No magic values; no boolean flags

## Examples (short)
- ❌ `function proc($d, $f, $x=true)` → ✅ `function calculateTieredPrice(Order $order, Tier $tier)`.
- ❌ `if ($a==1)` magic → ✅ `if ($status === Order::STATUS_PAID)`.

## Technology overrides
`technologies/php.md` (PSR-12, strict_types), `typescript.md` (strict, no `any`), `magento.md`/`laravel.md` (framework conventions).

## Related
- **Agents**: developer, tl, all reviewers
- **Skills**: review-code
- **Functions**: implement-task, review-code, refactor-code, generate-unit-test
- **Rules**: project-conventions-first, no-duplicate-knowledge, engineering-standards-enforcement
- **Hooks**: before-commit, before-pr
- **Audits**: Code Quality
- **Memory**: CONTINUOUS_LEARNING
- **Project types**: all
