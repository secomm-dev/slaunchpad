# TASK-HL3WQD — Implementation Plan: configurable endpoints, cache type & titles

| Specification | SPEC-TASK-HL3WQD (`.ai/specs/SPEC-TASK-HL3WQD-ai-discoverability-configurable-endpoints-cache-titles.md`) |
|---|---|
| Branch | `task/ai-discoverability-configurable-endpoints-and-cache` (base 39f88530) |
| Type | MINI+ — two modules, no behavior change at defaults |

1. `Secomm_AiDiscoverability`: `etc/cache.xml` + `Model/Cache/Type` (TagScope,
   id `secomm_ai_discoverability`); `LlmsTxtProvider` + `Model/InvalidateCache`
   rewired to the type (Zend-style TagScope clean contract).
2. Titles: `titles` config group (config.xml + system.xml), `Config::getSectionTitle`
   with shipped defaults, `LlmsTxtGenerator` + `CommerceEndpointsSource` consume.
3. `Secomm_AiCommerce`: `general/endpoint_path` (default `ai`), normalization
   authority `Model\Config::normalizeEndpointPath()` + save-time backend model;
   `Controller/Router` config-driven, standard frontName route removed.
4. BUG-provenance audit: SPEC-BUG-AIDL-CINV1 family → SPEC-CHANGE-/CHANGE-
   (git mv + provenance notes + reference updates); rule in `.ai/AGENTS.md` §8.7.
5. Tests: normalization matrix, backend canonicalization, router matrix,
   source advertisement, title fallbacks, TagScope invalidation contract.
6. Validation: php -l, both unit suites, PHPCS, project-ai-validate,
   git diff --check, hardcoded-/ai grep review, runtime cache-type proof.
7. Evidence report, CHANGELOGs (AIDL 1.5.0 / AIC 1.2.0), spec, merge to
   dev/development/thanhle → PR flow to development.
