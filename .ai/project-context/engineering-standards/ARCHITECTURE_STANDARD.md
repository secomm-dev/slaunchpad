# ARCHITECTURE_STANDARD

> Engineering standard — Architecture. English (technical). References `project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md`.

## Purpose
Define how the system is structured so changes are localized, dependencies are explicit, and the design matches the problem.

## Scope / Applicability
Mode A (architecture impact), new modules, integration design, major refactor, headless/API projects. Lighter touch in Mode B/C.

## Architecture patterns (use the right one — don't force one)

| Pattern | When | Anti-pattern (don't use when) |
|---|---|---|
| **Layered** | Clear presentation/business/data separation; default for monolith backends | Logic leaks across layers (controllers with business logic) |
| **DDD** | Complex business domain with rich rules (B2B commerce, pricing, order) | Anemic models + ubiquitous-language ignored |
| **Hexagonal (Ports/Adapters)** | Multiple integration channels; want core independent of infra | Ports coupled to a specific adapter |
| **Event-Driven** | Async reactions (order placed → ERP, inventory); loose coupling | Hidden event chains / eventual-consistency bugs |
| **Modular** | Large product split by bounded context (Magento modules, Laravel modules) | Cross-module direct DB/object access |
| **Headless** | Decoupled frontend + API backend; multi-channel | Frontend embedding business logic / contract drift |
| **API-First** | Contract consumed by multiple clients; integration-heavy | Backend changes without contract versioning |
| **Microservices** | Independent scale/deploy needs (rare for agency builds) | Distributed monolith / premature split |
| **Monolith** | Single-team, standard build (the common case) | Untangled spaghetti / no module boundaries |
| **Caching** | Read-heavy hot paths (catalog, price, config) | Stale data / cache key missing affecting params |
| **Scalability** | Traffic growth, peak events | Premature optimization / unmeasured scaling |
| **Integration** | Third-party ERP/PIM/WMS/payment/shipping | Brittle point-to-point without contract/idempotency |

## Mandatory Rules
- Architecture decisions are recorded (ADR in `DECISIONS.md`) — not ad-hoc.
- Integration contracts are documented (`project-context/05`) and confirmed — not assumed.
- DB schema/API changes get migration + backward-compat + Tier 2 escalation.
- Boundaries respected: business logic stays out of controllers/templates; integrations behind a port/adapter or service.
- Multi-layer overlays merge explicitly (Hyva = base + Magento + Hyva; documented).

## Recommended Practices
- Favor modular + layered for agency builds; reach for DDD/Hexagonal only when the domain warrants it.
- Prefer plugins/events over preferences/rewrites (Magento); hooks/services over framework edits.
- Keep external calls off the checkout critical path.

## Anti-patterns
- God classes; circular module dependencies; hidden cross-cutting changes; "just this once" architecture deviations; contract drift between frontend/backend.

## Validation Checklist
- [ ] Decision recorded as ADR; integration contracts confirmed
- [ ] Layers/boundaries respected; no logic in controllers/templates
- [ ] Schema/API changes have migration + backward-compat
- [ ] Caching keys include all affecting params; no stale risk
- [ ] No circular dependencies; coupling documented

## Related
- **Agents**: sa, project-auditor, api-integration, legacy-code-auditor
- **Skills**: headless-api-contract-review, magento-module-analysis
- **Functions**: create-solution-design, audit-architecture, research-implementation
- **Rules**: backward-compatibility, no-duplicate-knowledge
- **Audits**: Architecture, API, Code Quality
- **Memory**: DECISIONS, RESEARCH_NOTES, project-context/03
