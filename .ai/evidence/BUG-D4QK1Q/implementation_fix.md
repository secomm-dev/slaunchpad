# BUG-D4QK1Q — Implementation Fix Evidence

**Date:** 2026-08-26 · **Branch:** `fix/bug-d4qk1q-store-scoped-endpoint-routing` (from `10c6cbc3`)
**Status:** FIX DELIVERED — PENDING INDEPENDENT QA (final verification belongs to QA)

## Architecture (delivered)

```
target store resolution (Resolver::resolveAsData — data only, no global mutation)
    ↓
store-scoped enabled check (Config::isEnabled($storeId))
    ↓
strict store-scoped base-path validation (PathGuard::matches)
    ↓
controller routing (PathGuard re-checked as defense-in-depth)
```

- One authoritative resolution per request (`?store=<code>`, absent → installation default, invalid → documented 400 `invalid_store`); no store iteration, no global mutable state at router phase.
- Strict boundary: wrong path + valid `?store` is 404, never admitted; base path `ai` never matches `ai-extra`.
- llms.txt resolves the same `?store=` selector before generation; advertised URLs carry the store's own base path and store code.
- No redirects, no URL rewrites, no routes.xml, no hardcoded "agent"/store IDs, no dual-path aliasing.

## Runtime acceptance (DDEV-local, tunnel https://webhook.thanhaloha.io.vn; fixture default `ai` + stores/3 `agent`; config restored after)

| # | Case | Result |
|---|---|---|
| 1 | `GET /ai/store` | 200 (Default store) |
| 2 | `GET /agent/store?store=vi_vn` | 200 (vi_vn) |
| 3 | `GET /ai/store?store=vi_vn` | 404 |
| 4 | vi_vn `enabled=0`: `GET /agent/store?store=vi_vn` | 404 (Default store still 200 during same state; fixture restored & re-verified 200) |
| 5 | `GET /llms.txt?store=vi_vn` | 200; advertises `/agent/store?store=vi_vn`, `/agent/catalog/search?store=vi_vn`, `/agent/categories?store=vi_vn`; no `/ai/` URLs |

Post-restore DB state: only the original fixture rows remain (`default/0` enabled=1 + endpoint_path=ai; `stores/3` endpoint_path=agent).

## Automated tests

- `Secomm\AiCommerce` suite: **108 tests, 160 assertions — OK**
- `Secomm\AiDiscoverability` suite: **74 tests, 200 assertions — OK**
- PHPCS (`Magento2` standard) on all changed production files: 0 errors (1 intentional commented empty-catch warning).

## Key files

| File | Change |
|---|---|
| `AiCommerce/Model/StoreContext/Resolver.php` | pure `resolveAsData()` (router-phase contract) |
| `AiCommerce/Model/StoreContext/PathGuard.php` | NEW shared strict base-path/store boundary matcher |
| `AiCommerce/Controller/Router.php` | target-store-first scoped admission |
| `AiCommerce/Controller/Store/View.php`, `Catalog/Search.php`, `Categories/Index.php`, `Products/View.php` | PathGuard defense-in-depth |
| `AiDiscoverability/Controller/Index/Index.php` | `?store=` target resolution for llms.txt |
| Tests | RouterTest (rewritten, real collaboration), PathGuardTest (new), ResolverTest, Products/ViewTest, IndexTest, CommerceEndpointsSourceTest |

## Cache isolation & invalidation (verified)

- `LlmsTxtProvider` cache id `seocomm_llms_txt_store_{storeId}`; `ResponseCache` key `seomm_aic_s{storeId}_{route}_{hash}` — both keyed by the resolved store id (no cross-store leakage).
- `endpoint_path` changes flow through the existing `admin_system_config_changed_section_seocomm_ai_commerce` observer → dedicated cache type clean; no unrelated invalidation.
