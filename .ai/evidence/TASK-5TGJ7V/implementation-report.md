# TASK-5TGJ7V — Hierarchical Category Tree Selector Evidence

- Spec: `.ai/specs/SPEC-TASK-5TGJ7V-ai-discoverability-category-tree-selector.md` (Mode C MINI)
- Plan: `.ai/plans/TASK-5TGJ7V-implementation-plan.md`
- Branch: `task/ai-discoverability-category-tree-selector` (base `ec8fd0e1`)
- Date: 2026-08-25

## 1. Magento core implementation inspected (this repo's vendor tree)

- **Product Edit selector**: `Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Categories::customizeCategoriesField()`
  — `category_ids` = `Magento_Ui/js/form/element/ui-select` (`Magento_Catalog/js/components/new-category`
  only adds the new-category modal), `options` = nested tree from
  `retrieveCategoriesTree()` (ONE collection folded by parent refs, level+position sort).
- **Standalone tree widget**: `Magento\Catalog\Block\Adminhtml\Category\Checkboxes\Tree`
  + `Magento_Catalog::catalog/category/checkboxes/tree.phtml` +
  `Magento_Catalog/js/category-checkbox-tree` (alias `categoryCheckboxTree`,
  module-catalog adminhtml requirejs-config.js:15). jstree `plugins:['checkbox']`,
  `three_state:false`, full client-side render from `treeJson`, writes sorted
  comma-joined checked ids to `window[jsFormObject].updateElement`. Rendered by
  core promo-rule chooser (`Magento\CatalogRule\...\Promo\Widget\CategoriesJson`).
  jstree CSS ships in theme-adminhtml-backend.
- **ui-select in system.xml? Not possible** — ui-select needs uiForm/uiRegistry
  context; system config renders block `frontend_model`. => REUSED the core
  jstree checkbox widget instead; NO custom tree library, NO core JS changes.
  Small wrapper required and written: frontend_model block exposing the hidden
  input as `window.seocommAiCategoriesForm.updateElement` (the core JS contract).

## 2. Implementation

- `Model/Config/CategoryTreeProvider.php` — scope resolution (store/website
  request params, core Form semantics), root stored-path lookup (bounded id-list
  collection), ONE scoped collection (`is_active=1`, path = root stored path +
  descendants — SPEC-TASK-QQMVY4 invariant), fold into jstree nodes. Root never
  a node (Product-Edit `rootVisible:false`); multi-tree scopes wrap each tree in
  a `group_<rootId>` header node (non-numeric id → can never enter saved value).
- `Block/Adminhtml/System/Config/CategoryTree.php` — frontend_model: native
  hidden input under the element's real name/value (config save/load + Use
  Default/Use Website inheritance untouched) + tree div + x-magento-init for
  core `categoryCheckboxTree`.
- `system.xml` — categories field → `type="text"` + `frontend_model`.
- Removed: `Model/Config/Source/Categories` + its test (replaced).
- Data contract unchanged: comma-separated category entity IDs.

## 3. Runtime proof (local docker, real data, 2026-08-25)

Bootstrap script with real DI (real request carrying `store=default`, real
collections, real DB):

```
Store: default (id 1)   [vi_vn (id 3) verified identically: 48 nodes]
Root category ID: 2 (stored path 1/2)
Nodes: 48 (hierarchical)
  3 => Gear
  4 => Gear > Fitness Equipment
  5 => Living Room
  6 => Living Room > Seating
  ...
Root node present: NO        Global root 1 present: NO
Foreign-tree nodes: 0        Inactive nodes in tree: 0   (DB cross-check vs catalog_category_entity)
Selected nested: 4 (Gear > Fitness Equipment) and 3 (Gear)
Saved value (reloaded from core_config_data): '4,3'
Saved ids present in tree: YES
llms.txt (getFresh, store 1) ## Collections:
- [Fitness Equipment](https://webhook.thanhaloha.io.vn/gear/fitness-equipment.html)
- [Gear](https://webhook.thanhaloha.io.vn/gear.html)
```

Note: store vi_vn has NO category url_rewrite rows in this install (sample data
generated rewrites only for store 1), so llms.txt Collections for vi_vn is empty
by design (canonical policy drops unproven URLs) — proof therefore used store
`default`. The tree itself was proven on vi_vn too (identical 48-node result).

Proof scripts + test config rows removed after capture; caches flushed.

## 4. Validation

| Check | Result |
|---|---|
| `CategoryTreeProviderTest` (7 tests: store scope/hierarchy/root exclusion, stored-path filter + bare-id guard, inactive exclusion, website single-root, website multi-root groups, default union, nested id round-trip) | 7 tests / 25 assertions, OK |
| Full AiDiscoverability suite | 54 tests / 117 assertions, OK (pre-existing allure warning) |
| AiCommerce regression suite | 51 tests / 83 assertions, OK |
| php -l / PHPCS Magento2 (new + changed files) | clean / 0-0 |
| `setup:di:compile` | success |
| `project-ai-validate --check-specs` | VALID (0 FAIL, 0 WARN) |
| `git diff --check` / `app/etc/config.php` vs base | clean / empty diff |
