# [SLP][TASK-S7MFCT] AI Discoverability — Store-Scoped Category Selector Fix

Specification ID: SPEC-TASK-S7MFCT

> **Mode**: C (Mini-Spec) · **Type**: bug fix / admin UX · **Status**: VALID (bounded fix, no risk category)

## 1. Problem

`Secomm_AiDiscoverability/Model/Config/Source/Categories` (the admin multiselect
for `seocomm_ai_discoverability/urls/categories`) currently:

1. excludes only global entity_id 1 — the store **root category** (e.g.
   "Default Category", id 2) is selectable and would be emitted as a curated
   collection;
2. loads active categories **globally** — categories from other stores' trees
   (other root categories) are selectable even when editing a specific store
   view;
3. labels options by bare `name` — duplicate names ("Accessories" ×3) are
   ambiguous for the merchant.

## 2. Magento-core research findings (vendor-verified, magento-spec cross-checked)

| Question | Native answer (verified in `vendor/magento`) |
|---|---|
| How does core resolve the system-config scope being edited? | `Magento\Config\Block\System\Config\Form::getWebsiteCode()/getStoreCode()` read the admin request params `website` / `store` (`getRequest()->getParam(...)`, Form.php:731/743). There is no injected scope abstraction for source models — the request params ARE the core mechanism. |
| Effective root category for a scope | `Magento\Store\Model\Group::getRootCategoryId()` (Group.php:406) — store view → `getStore($code)->getGroup()->getRootCategoryId()`. Website → its groups' roots. |
| Restrict collection to descendants of a root | `Magento\Catalog\Model\ResourceModel\Category\Collection::addPathFilter()` / `addFieldToFilter('path', ['like' => "$root/%"])` — exactly what core's Flat resource does (`path like parentPath/%`, Flat.php:274). The `path` column lives on the main table (no attribute join). |
| Source-model pattern | `OptionSourceInterface` via `<source_model>`; scope-aware core precedent `Magento\Store\Model\System\Store` builds its options from StoreManager; injecting `RequestInterface` for the section scope is consistent with the Form block's own mechanism. |
| Source model depending on admin request scope? | Acceptable and native — the source model only renders inside the adminhtml config form whose URL carries the scope. Guarded by reading only `store`/`website` params; frontend/CLI callers get the deterministic default-scope behavior. |

## 3. Required behavior

### 3.1 Store-view scope (URL has `store` param)
- Collection `setStoreId($store->getId())` (store-scoped names).
- Root = that store group's `root_category_id`.
- Only descendants of that root (`path LIKE "root/%"` — excludes the root
  itself), `is_active = 1`, entity 1 never included.

### 3.2 Website scope (URL has `website` param, no `store`)
- Roots = distinct `root_category_id` of the website's groups.
- Exactly one distinct root → scope to that tree (same as store scope).
- Multiple distinct roots → do NOT silently pick one: options are the UNION of
  that website's trees with full breadcrumb labels (root name included) so the
  merchant sees which tree each entry belongs to. Category ids from other
  websites remain excluded.

### 3.3 Default scope (no params — global)
- No fake store scope invented. Options = active categories of ALL group-root
  trees, with breadcrumb labels. Every group root (and entity 1) excluded —
  "Default Category" never appears at any scope.

### 3.4 Labels (all scopes)
- Breadcrumb `Parent > Child > Category` built from the `path` column and the
  id→name map of the SAME single collection (no extra queries, no N+1).
- Tree-root name omitted unless disambiguation requires it (multi-root
  website/default scope → root name prepended).
- Fallback when no name: `[ID: n]`.

### 3.5 Compatibility (unchanged)
- Option values remain category entity ids (saved store-scoped values valid).
- `/llms.txt` generation (CategoriesSource) untouched.
- Field `urls/categories`, config paths, multiselect type unchanged.

## 4. Acceptance Criteria

1. Store scope: only that store's tree selectable; its root excluded.
2. Category from another root/tree excluded at store scope.
3. Inactive categories excluded (all scopes).
4. `Default Category`/any group root never selectable at any scope.
5. Duplicate names disambiguated via breadcrumb; deterministic sort (label asc).
6. No duplicate option values.
7. Website single-root behaves like its tree; multi-root = labeled union of
   that website's trees only.
8. Default scope = labeled union of all trees, roots excluded.
9. One bounded collection query per option render (breadcrumb from same
   collection; no per-category loads).
10. Existing LC-30/7FBHHC suites still pass; llms.txt output logic unchanged.

## 5. Approach

Rewrite `Model/Config/Source/Categories` only (no DI wiring change beyond
constructor args): inject `StoreManagerInterface` + `RequestInterface` +
existing `CategoryCollectionFactory`; resolve scope per §3; build labels per
§3.4. Unit tests via mocked collection/store-manager/request.
