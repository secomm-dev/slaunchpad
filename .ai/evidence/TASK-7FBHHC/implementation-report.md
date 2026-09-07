# TASK-7FBHHC — AI Discovery → Commerce Endpoint Integration Evidence

- Spec: `.ai/specs/SPEC-TASK-7FBHHC-ai-discovery-commerce-integration.md` (MINI / Mode C)
- Plan: `.ai/plans/TASK-7FBHHC-implementation-plan.md`
- Branch: `task/ai-discovery-commerce-integration` (base `d141f1e5` = origin/dev/development/thanhle)
- Date: 2026-08-25

## 1. Static validation

| Check | Result |
|---|---|
| php -l (all changed PHP) | ALL_LINT_OK |
| PHPCS Magento2 (changed files) | 0 errors / 0 warnings (exit 0) |
| Unit tests AiDiscoverability | 47 tests, 92 assertions, OK (1 pre-existing allure runner warning) |
| Unit tests AiCommerce (untouched, regression) | 51 tests, 83 assertions, OK |
| events.xml well-formed | XML_OK |
| `setup:di:compile` | success |
| `project-ai-validate --check-specs` | VALID (0 FAIL, 0 WARN) |
| `git diff --check` | clean |
| `app/etc/config.php` vs base | empty diff |

## 2. Runtime proof (local docker, 2026-08-25)

AiCommerce module enabled locally + `seocomm_ai_commerce/general/enabled=1`
→ `GET /llms.txt` ends with:

```
## Machine-readable Commerce
- [Store Information](https://webhook.thanhaloha.io.vn/ai/store?store=default)
- [Product Search](https://webhook.thanhaloha.io.vn/ai/catalog/search?store=default)
- [Categories](https://webhook.thanhaloha.io.vn/ai/categories?store=default)
- Product Detail: https://webhook.thanhaloha.io.vn/ai/products/{sku}?store=default
```

Flag set to `0` + cache flush → section completely absent (grep count 0);
every earlier section byte-identical. Note: CLI `config:set` does not dispatch
the admin section-save event — invalidation on real toggling is covered by the
new `admin_system_config_changed_section_seocomm_ai_commerce` observer
(same cleanAll policy as the module's own section observer, spec §2.4).
Module-presence changes are deployment operations that flush cache anyway.

Local runtime state fully restored afterwards (flag back to 1, config.php
reverted to branch-base version).

## 3. Unit-test coverage of the ACs

| AC | Test |
|---|---|
| 1 enabled → section rendered | `LlmsTxtGeneratorTest::testCommerceSectionAppendedWhenAiCommerceAdvertised` (exact byte assertion incl. plain Product Detail form) |
| 2 disabled/absent → no section | `CommerceEndpointsSourceTest::testDisabledFlagYieldsNoEntries`, `testAbsentModuleYieldsNoEntriesAndIgnoresConfig` (never reads config), `LlmsTxtGeneratorTest::testCommerceSectionAbsentWhenSourceEmpty` |
| 3 store code/base URL | `CommerceEndpointsSourceTest::testEnabledAdvertisesFullReadOnlySurface` (base URL + `?store=vietnam`) |
| 4 no commerce execution/catalog load | by construction — source DI is only `ModuleListInterface` + `ScopeConfigInterface`; verified by the never()-config test |
| 5 enable-cookies/no-route excluded | `EligibilityCheckerTest::testRejectsSystemCmsIdentifiers` (+ `testAllowsMerchantCmsIdentifiers` bounding guard) |
| 6 formatting stable | all pre-existing formatter/generator tests unchanged and green; absent-section body identical to prior expected strings |
| 7 LC-30 + LA-22 suites pass | 47/92 + 51/83 green |

## 4. Scope check

No AiCommerce PHP code touched (README note only). No new DB tables,
dependencies, cron, sessions, cookies, ObjectManager. GET/HEAD llms contract,
ETag, cache tags unchanged. Hard constraints (no cart/checkout/payment/order,
no MCP/UCP/ACP, no GraphQL, no raw catalog dump) respected.
