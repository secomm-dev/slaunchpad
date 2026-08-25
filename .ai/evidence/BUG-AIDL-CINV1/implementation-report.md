# BUG-AIDL-CINV1 — AiDiscoverability invalidation clean() contract fix (evidence)

- Spec: `.ai/specs/SPEC-BUG-AIDL-CINV1-llms-cache-clean-contract.md` (MINI, Mode C)
- Plan: `.ai/plans/BUG-AIDL-CINV1-implementation-plan.md`
- Branch: `fix/ai-discoverability-cache-invalidation` (base `9bd6cae9`)
- Date: 2026-08-25 · Discovered in: TASK-AIC-PDC1 §3.3 (P1 follow-up)

## 1. Root cause

`Secomm\AiDiscoverability\Model\InvalidateCache` called
`CacheInterface::clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, [tag])`. Runtime
`CacheInterface` is `Magento\Framework\App\Cache\Proxy` whose contract is
`clean(array $tags)` (MATCHING_ANY_TAG semantics) — the mode string became a tag, so
every invalidation was a silent no-op (same mechanism proven via redis MONITOR in
TASK-AIC-PDC1). Impact bounded by the configured TTL (~3600s): stale llms.txt after
CMS/category/config changes; no security/data-loss.

## 2. Observers audited (all already correct — no new observers)

| Observer | Event | Call |
|---|---|---|
| ConfigInvalidation | `admin_system_config_changed_section_seocomm_ai_discoverability` | cleanAll |
| SeoConfigInvalidation | `admin_system_config_changed_section_seo` | cleanAll |
| AiCommerceConfigInvalidation | `admin_system_config_changed_section_seocomm_ai_commerce` | cleanAll |
| CmsInvalidation | `cms_page_save_commit_after` / `cms_page_delete_commit_after` | cleanStores/cleanAll |
| CategoryInvalidation | `catalog_category_save_commit_after` / `..._delete_...` | cleanStores/cleanAll |
| SitemapInvalidation | `core_abstract_save_commit_after` / `..._delete_...` | cleanStore/cleanAll |

All route through `InvalidateCache` — only the `clean()` signature was broken.

## 3. Change

`Model/InvalidateCache.php`: `clean([\Secomm\AiDiscoverability\Service\LlmsTxtProvider::CACHE_TAG])`
(`seocomm_llms`) for cleanAll; `clean([$provider->storeTag($storeId)])`
(`seocomm_llms_store_<id>`) for cleanStore. Docblocks document the Proxy contract.
Tags derive from the module's own `LlmsTxtProvider` constants (not copied from AiCommerce).

## 4. Regression test

`Test/Unit/Model/InvalidateCacheTest.php` (new, 4 tests):
- cleanAll pins `clean(['seocomm_llms'])` (single array argument);
- explicit no-Zend-mode assertion (exactly 1 argument, array, no CLEANING_MODE string);
- cleanStore uses real `provider->storeTag()` value; cleanStores dedupes (['2',5,5,2] → 2 calls).

## 5. Runtime proof (bounded, no merchant mutation)

1. Warm: `GET /llms.txt` (tunnel domain) → 200; entry `zc:k:798_SEOCOMM_LLMS_TXT_STORE_1`
   present in redis with tags `zc:ti:798_SEOCOMM_LLMS` + `..._STORE_1`.
2. Trigger REAL invalidation path: dispatched
   `admin_system_config_changed_section_seocomm_ai_discoverability` via bootstrapped
   EventManager (fires the module's own ConfigInvalidation observer; writes no data).
3. Entry `zc:k:798_SEOCOMM_LLMS_TXT_STORE_1` **removed** (only zc:ti tag-index remnants
   remain) — invalidation works end-to-end at runtime.
4. Regenerate: `GET /llms.txt` → 200, entry recreated.
5. Cleanup: proof script removed from container + host. No merchant data touched.

## 6. Validation

| Check | Result |
|---|---|
| AiDiscoverability suite | 66 tests / 162 assertions, OK (pre-existing allure warning) |
| AiCommerce regression | 63 tests / 104 assertions, OK |
| php -l (changed files) | clean |
| PHPCS Magento2 (warning-severity 6) | exit 0 — 0 errors / 0 warnings |
| project-ai-validate --check-specs | VALID (0 FAIL, 0 WARN) |
| git diff --check | clean |
| app/etc/config.php | not committed (working-tree env drift only) |
