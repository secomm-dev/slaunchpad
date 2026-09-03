# TASK-S7MFCT — Store-Scoped Category Selector Fix Evidence

- Spec: `.ai/specs/SPEC-TASK-S7MFCT-ai-discoverability-category-scope-fix.md` (MINI / Mode C)
- Plan: `.ai/plans/TASK-S7MFCT-implementation-plan.md`
- Branch: `task/ai-discoverability-category-scope-fix` (base `3ac91a0b` = origin/dev/development/thanhle)
- Date: 2026-08-25

## 1. Magento-core research (vendor-verified; magento-spec multi-store reference cross-checked)

| Finding | Source |
|---|---|
| Config scope resolution = `website`/`store` request params of the config-section URL — the exact mechanism `Magento\Config\Block\System\Config\Form` uses (`getWebsiteCode()`/`getStoreCode()`, Form.php:731/743) | `vendor/magento/module-config` |
| Store-group root: `Magento\Store\Model\Group::getRootCategoryId()` (Group.php:406); `StoreManagerInterface::getStore/getWebsite/getGroups` for resolution | `vendor/magento/module-store` |
| Tree restriction: `path LIKE "root/%"` on the category collection (core `Category\Collection::addPathFilter` + Flat.php:274 `path like parentPath/%`); `path` on main table (no attribute join) | `vendor/magento/module-catalog` |
| Scope-aware source-model precedent: `Magento\Store\Model\System\Store` (OptionSourceInterface over StoreManager data) | `vendor/magento/module-store` |

No DB-direct access needed; no ObjectManager; no hardcoded ids.

## 2. Behavior delivered

- **Store scope** (`store` param): collection `setStoreId(store)`, options =
  active descendants of `group.root_category_id` only; root + global root
  excluded (`path LIKE` + explicit `entity_id nin`).
- **Website scope**: distinct group roots of that website — 1 root → tree
  behavior; >1 root → UNION of that website's trees with store-group-name
  label prefix (never silent mixing, never a foreign website's tree).
- **Default scope**: union of all group trees, group-name prefixes, no fake
  store invented (`setStoreId(0)`).
- **Labels**: breadcrumb `Parent > Child` from `path` + id→name map of the
  SAME collection — one bounded query total; fallback `[ID: n]`; sort by
  label asc; option values remain entity ids (saved values fully compatible).

## 3. Validation

| Check | Result |
|---|---|
| Targeted `CategoriesTest` | 7 tests / 17 assertions, OK |
| Full AiDiscoverability suite | 54 tests / 109 assertions, OK (1 pre-existing allure runner warning) |
| AiCommerce suite (regression, untouched) | 51 tests / 83 assertions, OK |
| php -l | clean |
| PHPCS Magento2 (both changed PHP files) | 0 errors / 0 warnings |
| `setup:di:compile` (constructor args changed) | success |
| `project-ai-validate --check-specs` | VALID (0 FAIL, 0 WARN) |
| `git diff --check` | clean |
| `app/etc/config.php` vs base | empty diff |

## 4. Query shape

ONE category collection per option render (cached in-property per request):
`is_active=1` + `entity_id nin [roots,1]` + OR-of-`path LIKE root/%` +
`setStoreId` (store scope). No per-category loads; breadcrumbs resolved
in-memory from the same result set. `/llms.txt` generation untouched.

## 5. AC matrix

| AC | Test |
|---|---|
| 1 store tree + root excluded | `testStoreScopeResolvesStoreTreeAndExcludesRoots` (filters + values) |
| 2 foreign tree excluded | path filter assertion (`2/%` only for resolved roots) |
| 3 inactive excluded | `testInactiveCategoriesFiltered` |
| 4 no root selectable at any scope | `nin` filter incl. all resolved roots in every scope test |
| 5 duplicate names disambiguated | `testDuplicateNamesDisambiguatedAndSorted` |
| 6 no duplicate values | same test |
| 7 website single/multi root | `testWebsiteScopeSingleRootBehavesLikeTreeWithoutPrefix`, `testWebsiteScopeMultipleRootsLabeledUnionOfOwnTreesOnly` |
| 8 default scope | `testDefaultScopeIsLabeledUnionOfAllTrees` |
| 9 one bounded query | by construction (single collection, in-memory labels) |
| 10 suites green | §3 |
| fallback label | `testUnnamedCategoryFallsBackToIdLabel` |
