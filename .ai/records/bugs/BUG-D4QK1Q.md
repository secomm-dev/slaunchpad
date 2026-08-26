---
id: BUG-D4QK1Q
type: bug
title: 'Store View overridden AI Read Endpoint Path fails with 404 in custom router'
project_code: SLP
parent: null
external_refs: {}
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: null
risk: high
status: fix_delivered_pending_qa
created: '2026-08-26'
updated: '2026-08-26'
decisions: []
decision_assessment: non-material
decision_approval_summary:
  total: 0
  pending_approval: []
  approved: []
  rejected: []
  superseded: []
  last_synced: '2026-08-26'
verified_against_commit: null
components:
  - CMP-AICOMMERCE
  - CMP-AIDISCOVERABILITY
source_areas:
  - app/code/Secomm/AiCommerce/Controller/Router.php
  - app/code/Secomm/AiCommerce/Model/Config.php
  - app/code/Secomm/AiCommerce/Model/StoreContext/Resolver.php
  - app/code/Secomm/AiDiscoverability/Service/Source/CommerceEndpointsSource.php
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
last_verified: '2026-08-26'
supersedes: []
---

# [SLP][BUG-D4QK1Q] Store View overridden AI Read Endpoint Path fails with 404 in custom router

## Overview & Bug Classification
- **Classification**: **BUG CONFIRMED**
- **Origin**: Independent QA Review (Post-Implementation Audit)
- **Component**: `Secomm_AiCommerce` & `Secomm_AiDiscoverability`
- **Severity**: High (Store View configuration override produces broken 404 endpoints)

## Symptom & Reproduction Evidence
- **Default Config**: `seocomm_ai_commerce/general/endpoint_path = ai`
- **Store View Override (`vi_vn` / store ID 3)**: `seocomm_ai_commerce/general/endpoint_path = agent`

### Actual HTTP Results Recorded (Runtime Execution):
1. `GET /ai/store` -> **200 OK** (Default Store context)
2. `GET /ai/store?store=vi_vn` -> **200 OK** (**DEFECT**: Serves Store View `vi_vn` data under the wrong base path `/ai`)
3. `GET /agent/store?store=vi_vn` -> **404 Not Found** (**DEFECT**: Standard Magento 404 HTML Page; custom router fails to match configured Store View path)
4. `GET /agent/store` -> **404 Not Found** (**DEFECT**: Standard Magento 404 HTML Page)
5. `GET /llms.txt?store=vi_vn` -> **DEFECT**: Advertises `https://webhook.thanhaloha.io.vn/ai/store?store=default` instead of configured store endpoint path `/agent/...`

## Root Cause Analysis
1. **Unscoped Endpoint Path Resolution in Custom Router**:
   In `Secomm\AiCommerce\Controller\Router::match(RequestInterface $request)` (line 61):
   ```php
   $basePath = $this->config->getEndpointPath();
   ```
   `$this->config->getEndpointPath()` is invoked without a `$storeId` parameter. `Config::getEndpointPath()` delegates to `$this->scopeConfig->getValue(..., ScopeInterface::SCOPE_STORE, null)`. When `$storeId` is `null`, `ScopeConfigInterface` falls back to `StoreManagerInterface::getStore()`. At the router matching phase inside `Magento\Framework\App\FrontController`, Magento's `StoreManager` has not resolved store scope from parameters or request headers yet, returning the Default Store View.
   Therefore, `$basePath` ALWAYS evaluates to the Default Store's endpoint path (`"ai"`), ignoring any Store View override.

2. **Router Match Failure for Overridden Path**:
   When a request arrives for `/agent/store` (or `/agent/store?store=vi_vn`), `Router::match()` compares `"agent/store"` against `$basePath` (`"ai"`). Because `"agent/store"` does not start with `"ai/"`, `Router::match()` returns `null`. Magento's standard router takes over, finds no route, and returns a 404 Not Found HTML page.

3. **Wrong Path Contract Bypass**:
   When a request arrives for `/ai/store?store=vi_vn`, `Router::match()` compares `"ai/store"` against `$basePath` (`"ai"`). It matches! The request is routed to `Secomm\AiCommerce\Controller\Store\View`. Inside the controller, `StoreContext\Resolver` resolves `?store=vi_vn` and sets the store context to `vi_vn`. The controller executes and returns `vi_vn` catalog data without verifying whether the requested base path (`"ai"`) actually matches `vi_vn`'s configured endpoint path (`"agent"`).

4. **`llms.txt` Store Resolution Discrepancy**:
   `Secomm\AiDiscoverability\Controller\Index\Index` relies on `$storeManager->getStore()->getId()` without resolving `?store=...` query parameters, and `CommerceEndpointsSource` builds endpoint URLs using default store context if store resolution fails during discovery generation.

## Required Fix Contract (Acceptance Criteria for Implementation AI)
1. **Store View Aware Router Matching**:
   The custom router (`Secomm\AiCommerce\Controller\Router`) MUST resolve candidate store view contexts (from request URL, store code, host, or `store` query parameter) OR iterate active store view base paths to match the incoming request against the target store's configured `endpoint_path`.
2. **Strict Base Path Enforcement**:
   - For Store View A (`endpoint_path = agent`): `/agent/store`, `/agent/catalog/search`, `/agent/categories`, `/agent/products/{sku}` MUST return 200 OK with Store View A data.
   - Accessing `/ai/...` for Store View A MUST return 404 (must NOT fall back or serve Store View A data under an unconfigured path).
3. **Disabled State Contract**:
   When `seocomm_ai_commerce/general/enabled` is `0` for the resolved Store View, all endpoints under that store's base path MUST return 404 / be inaccessible.
4. **`llms.txt` Discovery Synchronization**:
   `llms.txt?store=<code` MUST advertise the exact configured base path for that specific Store View.
5. **No Static Route Compromises**:
   Do NOT use hardcoded redirects, 301/302 rewrites, generated `routes.xml`, or fake aliases. Fix the custom router implementation natively in PHP.

## Fix Evidence (Implementation AI — 2026-08-26)

**Status: FIX DELIVERED — PENDING INDEPENDENT QA.** Final verification belongs to QA; this section records the delivered fix only.

### Root Cause Fixed
- `Router::match()` now resolves the request's TARGET store view FIRST — as data, via the shared `StoreContext\Resolver::resolveAsData()` (`?store=<code>`, absent → installation default; no `setCurrentStore` mutation during router matching) — then runs ALL config checks with that explicit store id.
- New `Secomm\AiCommerce\Model\StoreContext\PathGuard` is the single strict base-path matcher (`path === basePath || str_starts_with(path, basePath.'/')`), shared by the router (admission) and all four controllers (defense-in-depth), so the two layers cannot disagree about the boundary.
- `llms.txt` (`AiDiscoverability\Controller\Index\Index`) now resolves the same `?store=` selector before any generation/config lookup; `CommerceEndpointsSource` already reads `endpoint_path` store-scoped, so the resolved store id flows through to the advertised URLs.
- Invalid `?store` code: router falls back to the default store for path admission only, preserving the documented 400 `invalid_store` envelope.

### Runtime Acceptance (all 5 QA cases, environment restored afterward)
| Case | Result |
|---|---|
| `GET /ai/store` (no param) | **200** (Default store) |
| `GET /agent/store?store=vi_vn` | **200** (vi_vn data) |
| `GET /ai/store?store=vi_vn` | **404** (strict boundary) |
| vi_vn `enabled=0` → `GET /agent/store?store=vi_vn` | **404** (target-store scoped disabled state; Default store remained 200; config restored) |
| `GET /llms.txt?store=vi_vn` | **200**, advertises `/agent/store?store=vi_vn`, `/agent/catalog/search?store=vi_vn`, `/agent/categories?store=vi_vn` — no `/ai/` URLs |

### Automated Tests
AiCommerce suite 108/108, AiDiscoverability suite 74/74 green (router matrix exercising the real Resolver + PathGuard + store-scoped Config collaboration: default match, store-override match, wrong-path rejection, disabled-target rejection, cross-store isolation, invalid-code fallback; `resolveAsData` purity; PathGuard boundary cases incl. `/ai-extra` non-match; llms.txt store-param resolution; Products/View defense-in-depth). PHPCS Magento2: clean (1 intentional commented empty catch).

### Files Changed
- `app/code/Secomm/AiCommerce/Model/StoreContext/Resolver.php` — added pure `resolveAsData()`
- `app/code/Secomm/AiCommerce/Model/StoreContext/PathGuard.php` — new shared strict matcher
- `app/code/Secomm/AiCommerce/Controller/Router.php` — target-store-first, scoped enabled/path, strict admission
- `app/code/Secomm/AiCommerce/Controller/{Store/View,Catalog/Search,Categories/Index,Products/View}.php` — PathGuard defense-in-depth
- `app/code/Secomm/AiDiscoverability/Controller/Index/Index.php` — `?store=` target resolution for llms.txt
- Tests: RouterTest (rewritten), PathGuardTest (new), ResolverTest, Products/ViewTest, IndexTest, CommerceEndpointsSourceTest
