# DESIGN_PATTERN_STANDARD

> Engineering standard — Design Patterns. English.

## Purpose / Scope / Applicability
Use proven patterns where they fit; don't force them. Applies to structural design decisions.

## Patterns (use when the problem warrants)
- **Repository / Data Mapper**: data access behind an interface (Magento repositories, Laravel Eloquent + repository for reuse). — *API Integration, Magento Backend*.
- **Factory / Builder**: object construction with many params/variants. — *order/cart construction*.
- **Strategy / Policy**: interchangeable behavior by customer-group/segment/region. — *pricing, shipping, discount*.
- **Observer / Event**: decoupled reactions (Magento observers, Laravel events). — *order placed → side effects*.
- **Adapter / Anti-corruption**: integrate third-party without leaking its model. — *ERP/PIM integration*.
- **Plugin / Decorator**: extend without editing (Magento plugins). — *Open/Closed*.

## Recommended Practices
- Name patterns by their conventional name so readers recognize them.
- Prefer framework-native patterns (Magento DI/plugins; Laravel service container).

## Anti-patterns
Pattern-for-pattern's-sake; pattern without the problem it solves; hidden pattern (unnamed).

## Validation Checklist
- [ ] Pattern matches a real problem (not cargo-cult)
- [ ] Named conventionally; framework-native preferred
- [ ] No pattern complexity where a simple function would do

## Related
**Agents**: sa, developer · **Skills**: magento-module-analysis, headless-api-contract-review · **Functions**: create-solution-design, audit-code-quality · **Audits**: Code Quality, Architecture
