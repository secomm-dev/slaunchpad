# SPEC-BUG-AIDL-CINV1 — AiDiscoverability llms.txt cache invalidation no-op (MINI, Mode C)

Date: 2026-08-25 · Module: `Secomm_AiDiscoverability` · Ref: TASK-AIC-PDC1 §3.3 follow-up (P1)

## 1. Problem

`Secomm\AiDiscoverability\Model\InvalidateCache` calls
`CacheInterface::clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, [tag])`. On this stack the
runtime `Magento\Framework\App\CacheInterface` is `App\Cache\Proxy`, whose contract is
`clean(array $tags)` — the Zend-style mode string is swallowed as the first tag, so every
clean is a silent runtime no-op (same defect proven via redis MONITOR in TASK-AIC-PDC1).
Impact bounded by cache TTL (~3600s): stale llms.txt after CMS/category/config changes.
No security/data-loss.

## 2. Fix

Pass the plain tags array (Proxy contract, MATCHING_ANY_TAG semantics):

- `cleanStore($storeId)` → `$this->cache->clean([$this->provider->storeTag($storeId)])`
- `cleanAll()` → `$this->cache->clean([LlmsTxtProvider::CACHE_TAG])` (`seocomm_llms`)

Observers audited (config ×3, cms_page save/delete, category save/delete, sitemap): all
already route through `InvalidateCache` and fire on the correct Magento-native events —
NO new observers, no scope change. Only the `clean()` signature is broken.

## 3. Acceptance

1. No two-argument Zend-style `clean()` call remains in the module.
2. Unit test pins `clean([LlmsTxtProvider::CACHE_TAG])` for `cleanAll()` and
   `clean([$provider->storeTag($id)])` for `cleanStore()` — old form proven gone
   (asserts single array argument containing exactly the expected tag).
3. `cleanStores` dedupes/intvals then delegates.
4. Bounded runtime proof: warm llms.txt cache → entry exists in redis → trigger real
   invalidation path → entry removed → regenerate → full cleanup of any temp data.
5. Full AiDiscoverability suite + AiCommerce regression green; php -l, PHPCS,
   `project-ai-validate --check-specs`, `git diff --check` clean.

## 4. Out of scope

Any behavior change beyond the `clean()` contract correction; new observers; AiCommerce.
