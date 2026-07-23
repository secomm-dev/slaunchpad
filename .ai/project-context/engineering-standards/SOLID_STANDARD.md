# SOLID_STANDARD

> Engineering standard — SOLID. English. Foundational; applies to all OOP code.

## Purpose / Scope / Applicability
Keep classes maintainable, extensible, and bug-resistant. Applies wherever OOP is used.

## Mandatory Rules
- **S**ingle Responsibility: a class has one reason to change.
- **O**pen/Closed: extend via new code (plugins/services/inheritance), not by editing tested code.
- **L**iskov Substitution: subtypes are substitutable for base types without surprise.
- **I**nterface Segregation: depend on small, focused interfaces (Magento service contracts, Laravel interfaces).
- **D**ependency Inversion: depend on abstractions; inject via DI container.

## Recommended Practices
- Inject collaborators; reach for interfaces at module boundaries.
- Prefer Magento plugins over preferences (Open/Closed); Laravel service/action over statics.

## Anti-patterns
God classes; classes with many reasons to change; type-switching instead of polymorphism; depending on concretions; fat interfaces.

## Validation Checklist
- [ ] Each class has one responsibility
- [ ] Extension via new code, not edits to tested paths
- [ ] Subtypes behave as expected (no surprise overrides)
- [ ] Boundaries depend on interfaces/abstractions

## Related
**Agents**: developer, sa · **Skills**: review-code, magento-module-analysis · **Functions**: review-code, audit-code-quality · **Rules**: project-conventions-first · **Audits**: Code Quality · **Project types**: all (OOP)
