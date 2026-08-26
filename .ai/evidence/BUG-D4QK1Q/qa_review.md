# QA Independent Review Receipt: BUG-D4QK1Q

**Date**: 2026-08-26
**Reviewer Role**: Independent Magento 2 Architecture Reviewer / QA
**Verdict**: **BUG CONFIRMED**

---

## Executive Summary
The feature for configurable AI Read Endpoint Path (`seocomm_ai_commerce/general/endpoint_path`) claims to support Magento Store View scope (`showInStore="1"` in `system.xml`). However, runtime testing and code analysis reveal that overriding the endpoint path for a specific Store View (e.g. `endpoint_path = agent` for Store View `vi_vn`) fails:
- `/agent/store?store=vi_vn` returns a **404 Not Found** error.
- `/ai/store?store=vi_vn` returns **200 OK** with `vi_vn` store data under the unconfigured path `/ai`.

This is a structural defect in `Secomm\AiCommerce\Controller\Router` and store resolution architecture.

---

## 1. Files & Code Locations Reviewed
- `app/code/Secomm/AiCommerce/etc/adminhtml/system.xml` (Lines 25-37: config field `endpoint_path` defined with `showInStore="1"`)
- `app/code/Secomm/AiCommerce/Model/Config.php` (Lines 60-67: `getEndpointPath(?int $storeId = null)` method)
- `app/code/Secomm/AiCommerce/Controller/Router.php` (Lines 61-65: `$basePath = $this->config->getEndpointPath();` without passing `$storeId`)
- `app/code/Secomm/AiCommerce/Model/StoreContext/Resolver.php` (Lines 45-93: Store parameter resolution inside controller execution phase)
- `app/code/Secomm/AiCommerce/etc/frontend/di.xml` (Lines 4-14: Router list registration with `sortOrder` 22)
- `app/code/Secomm/AiCommerce/etc/frontend/routes.xml` (No static `frontName` route declared)
- `app/code/Secomm/AiDiscoverability/Service/Source/CommerceEndpointsSource.php` (Lines 58-116: Commerce endpoints discovery generator)
- `app/code/Secomm/AiDiscoverability/Controller/Index/Index.php` (Lines 56-62: `llms.txt` controller store resolution)

---

## 2. Configuration & Scope Review
- **`system.xml` Scope Support**:
  ```xml
  <field id="endpoint_path" translate="label comment" type="text"
         sortOrder="15" showInDefault="1" showInWebsite="1"
         showInStore="1" canRestore="1">
  ```
  `showInDefault="1"`, `showInWebsite="1"`, `showInStore="1"`. Store View scope configuration IS enabled in Admin XML.
- **Config Model Scope Resolution**:
  ```php
  public function getEndpointPath(?int $storeId = null): string
  {
      return self::normalizeEndpointPath((string) $this->scopeConfig->getValue(
          self::XML_PATH_ENDPOINT_PATH,
          ScopeInterface::SCOPE_STORE,
          $storeId
      ));
  }
  ```
  When `$storeId` is passed explicitly (e.g. `$storeId = 3`), `Config::getEndpointPath(3)` correctly returns `'agent'`.
  However, `Router::match()` calls `$this->config->getEndpointPath()` with NO argument (`$storeId = null`). At the router matching phase, Magento's `StoreManager` has not resolved the request's target store, defaulting to store ID 1. Thus, `Router::match()` ALWAYS retrieves the DEFAULT store's path (`'ai'`).

---

## 3. Reproduction & Runtime Evidence
- **Environment**: Docker container `slaunchpad-phpfpm-1` & `slaunchpad-app-1`, Database `magento`
- **Setup**:
  - `default` scope: `seocomm_ai_commerce/general/endpoint_path` = `ai`
  - `stores/3` (vi_vn) scope: `seocomm_ai_commerce/general/endpoint_path` = `agent`
- **Runtime HTTP Responses**:

| Request URL | HTTP Status | Response Payload Summary / Behavior | Notes |
|---|---|---|---|
| `GET /ai/store` | `200 OK` | `{"store_code":"default", ...}` | Works for default store |
| `GET /ai/store?store=vi_vn` | `200 OK` | `{"store_code":"vi_vn", ...}` | **BUG**: Serves `vi_vn` data under default path `/ai` |
| `GET /agent/store?store=vi_vn` | `404 Not Found` | Magento Standard 404 HTML Page | **BUG**: Custom router fails to match `/agent/` |
| `GET /agent/store` | `404 Not Found` | Magento Standard 404 HTML Page | **BUG**: Custom router fails to match `/agent/` |
| `GET /llms.txt?store=vi_vn` | `200 OK` | Advertises `/ai/store?store=default` | **BUG**: Does not reflect `agent` base path |

---

## 4. Root Cause Summary
1. `Secomm\AiCommerce\Controller\Router::match()` resolves the base path using `$this->config->getEndpointPath()` without resolving the request's Store View context or checking candidate store endpoint paths.
2. At the router match phase, `StoreManager` is bound to default store scope, forcing `$basePath` to evaluate as `"ai"`. Requests to `/agent/*` fail path matching in the custom router and drop through to standard router 404.
3. Requests to `/ai/store?store=vi_vn` match the default store base path `"ai"`, pass routing, and execute the controller. The controller invokes `StoreContext\Resolver`, which sets `StoreManager` to `vi_vn` (store 3), serving `vi_vn` catalog data under the wrong base path `/ai`.
4. `llms.txt` discovery generator does not synchronize with target store endpoint path when requested across store scopes.

---

## 5. QA Classification & Next Steps
- **Classification**: **BUG CONFIRMED** (`BUG-D4QK1Q`)
- **Action**: Logged canonical bug record in `.ai/records/bugs/BUG-D4QK1Q.md`.
- **Implementation AI Task**: Fix `Secomm\AiCommerce\Controller\Router` and store resolution logic without using static route hacks or HTTP redirects.
