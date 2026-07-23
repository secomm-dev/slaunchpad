# MAGENTO_STANDARD (covers Adobe Commerce)

> Technology standard — Magento 2 / Adobe Commerce. English. **Extends/overrides** shared standards (more specific wins). Enforcement items in `coding-rules/magento-rules.md` → `CODING_RULES.md`. Generated as `MAGENTO_STANDARD.md`.

## Purpose / Scope / Applicability
Magento 2 / Adobe Commerce engineering conventions. Applies when `platform = magento`.

## Mandatory Rules (Magento-specific)
- **Service Contracts**: expose module APIs via interfaces (`Api\Data\*Interface`, `Api\*RepositoryInterface`) — not direct model access.
- **Repository pattern**: data access via repositories; no direct `save/delete` on models outside the repo.
- **ViewModel**: use `ArgumentInterface` view-models for template data; no business logic in `.phtml`.
- **Plugins over Preferences**: extend via `di.xml` plugins (`before/after/around`); avoid `preference` overrides (breaks upgrade-safety) unless no alternative.
- **Observers**: react to domain events via `events.xml`; keep observers idempotent + fast.
- **Dependency Injection**: define in `di.xml`; no `new ObjectManager` in code (except factories).
- **XML conventions**: valid `*.xml` (module/routes/events/di); follow XSD; no invalid handles.
- **Cache**: tag cache correctly; cache keys include customer-group/store/website; respect FPC + indexers.
- **Upgrade-safe**: code in `app/code/<Vendor>/<Module>`; never edit Magento core; respect `*_catalog`/`*_product` deprecation across versions.

## Recommended Practices
- Declare `composer.json` + `module.xml` + `registration.php`; proper sequence/dependency.
- Use factories for transient objects; proxies for heavy constructor dependencies (lazy load).
- Keep modules single-purpose; prefer composition over monolith modules.

## Anti-patterns
Core file edits; `preference` for what a plugin can do; `ObjectManager::getInstance()` in logic; untagged cache; non-idempotent observer; invalid layout handle.

## Validation Checklist
- [ ] Service contracts + repository used; no direct model save outside repo
- [ ] Plugins preferred over preferences; observers idempotent
- [ ] DI via `di.xml`; no `ObjectManager` in logic
- [ ] Cache tagged + keyed; no core edits; upgrade-safe

## Related
**Agents**: magento-reviewer, hyva-migration, legacy-code-auditor · **Skills**: magento-module-analysis, magento-checkout-impact, magento-upgrade-review · **Functions**: implement-task, review-code, audit-code-quality, refactor-code · **Rules**: backward-compatibility, project-conventions-first · **Audits**: Magento, Hyva · **Enforcement**: `coding-rules/magento-rules.md`
