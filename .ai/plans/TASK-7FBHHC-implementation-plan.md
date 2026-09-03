# Implementation Plan — TASK-7FBHHC AI Discovery → Commerce Integration

| Field | Value |
|-------|-------|
| Ticket / Spec | TASK-7FBHHC |
| Specification | `.ai/specs/SPEC-TASK-7FBHHC-ai-discovery-commerce-integration.md` (status: VALID — Mode C Mini-Spec, ticket-scoped) |

Branch: `task/ai-discovery-commerce-integration` (base `origin/dev/development/thanhle` = `d141f1e5`)

## Steps

1. **CommerceEndpointsSource** (`Service/Source/CommerceEndpointsSource.php`)
   - DI: `ModuleListInterface`, `ScopeConfigInterface`
   - `getEntries(StoreInterface $store): array` — empty unless soft checks pass;
     else 4 entries (3 links + 1 Product Detail route-template plain entry),
     built from `rtrim($store->getBaseUrl(), '/')` + `?store=` + `(string) $store->getCode()`
2. **LlmsTxtGenerator** — inject source; add section `Machine-readable Commerce`
   after Sitemap in the ordered `$sections` map (same bound/dedup pipeline).
3. **EligibilityChecker** — `isEligibleCmsIdentifier(string $identifier): bool`
   denying exact `enable-cookies`, `no-route`; call from `CmsPagesSource`
   right after identifier empty-check.
4. **Invalidation** — `Observer/AiCommerceConfigInvalidation` (cleanAll) +
   `events.xml` entry for `admin_system_config_changed_section_seocomm_ai_commerce`.
5. **Docs** — README config-table path fixes (3 rows) + AiCommerce README
   follow-up note + AiDiscoverability CHANGELOG.
6. **Tests**
   - New `CommerceEndpointsSourceTest`: enabled → 4 entries with exact URLs;
     disabled flag → []; module absent → []; store code/base URL used
   - `EligibilityCheckerTest`: add identifier cases (denied 2, allowed others)
   - `LlmsTxtGeneratorTest`: adjust constructor (new dep); add commerce-section
     enabled/disabled formatting tests; existing expectations unchanged
7. **Validation** — unit suites (AiDiscoverability + AiCommerce), php -l,
   PHPCS Magento2, `setup:di:compile`, `project-ai-validate --check-specs`,
   `git diff --check`; config.php diff must be empty
8. **Evidence** — `.ai/evidence/TASK-7FBHHC/implementation-report.md`
9. Commit + push branch. NO merge.
