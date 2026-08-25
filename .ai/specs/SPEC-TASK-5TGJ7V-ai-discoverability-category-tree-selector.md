Specification ID: SPEC-TASK-5TGJ7V

# SPEC-TASK-5TGJ7V — Hierarchical Magento-native Category Tree Selector (AI Discoverability)

- Classification: MINI (Mode C) — bounded admin-UI replacement, no data-contract change
- Module: `Secomm_AiDiscoverability`
- Field: Stores → Configuration → Secomm → AI Discoverability → Curated URLs → Categories / Collections

## 1. Goal

Replace the flat category multiselect with a hierarchical, Magento-native category
tree selector (Product-Edit-like UX: expand/collapse, checkboxes, hierarchy context,
duplicate names disambiguated by structure). Saved values REMAIN comma-separated
category entity IDs — llms.txt generation and stored config stay 100% compatible.

## 2. Magento core evidence (inspected, this repo's vendor tree)

1. **Product Edit selector** — `Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Categories`
   (`customizeCategoriesField`, lines 244–342): field `category_ids` is a
   `Magento_Ui/js/form/element/ui-select` (via `Magento_Catalog/js/components/new-category`
   which only adds new-category modal handling) with
   `elementTmpl: ui/grid/filters/elements/ui-select`, `filterOptions: true`,
   `chipsEnabled: true`, `options = getCategoriesTree()`.
2. **Tree data shape** — same class `retrieveCategoriesTree()` (lines 429–473):
   ONE collection (`entity_id IN shownIds`, `name/is_active/parent_id`, sorted by
   level then position, `setStoreId`) folded into nested
   `{value, label, is_active, optgraph[]}` via `parent_id` references.
3. **Standalone checkbox tree** — `Magento\Catalog\Block\Adminhtml\Category\Checkboxes\Tree`
   + `Magento_Catalog::catalog/category/checkboxes/tree.phtml` +
   `Magento_Catalog/js/category-checkbox-tree` (requirejs alias
   `categoryCheckboxTree`, module-catalog adminhtml requirejs-config.js:15): jstree
   with `plugins: ['checkbox']`, `checkbox.three_state: false`, full client-side
   render from `treeJson`, writes checked ids (comma-joined, sorted, deduped) into
   `window[jsFormObject].updateElement`. Used by core promo-rule category chooser
   (`Magento\CatalogRule\Controller\Adminhtml\Promo\Widget\CategoriesJson`). jstree
   styles ship in the admin theme (theme-adminhtml-backend Magento_Catalog
   _module.less).
4. **Config scope semantics** — `Magento\Config\Block\System\Config\Form`
   `getWebsiteCode()/getStoreCode()` read `website`/`store` request params (proved
   in SPEC-TASK-QQMVY4).
5. **Can the ui-select be reused in system.xml?** NO — ui-select is a UI-component
   requiring a uiForm/uiRegistry context; system.xml renders via block
   `frontend_model`. The core-native equivalent for non-UI-form admin pages is the
   jstree checkbox tree (item 3) — reused directly, unchanged.

## 3. Approach (smallest correct, core-preferred)

- `system.xml` field: `type="hidden"` + `frontend_model`
  `Secomm\AiDiscoverability\Block\Adminhtml\System\Config\CategoryTree` extending
  `Magento\Config\Block\System\Config\Form\Field`. `_getElementHtml()` renders:
  1. the element's own hidden input (native name/value → native config save/load,
     Use Default/Use Website inheritance untouched);
  2. a tree `<div>` + `text/x-magento-init` invoking core
     `categoryCheckboxTree` with `treeJson` built server-side.
- New `Model\Config\CategoryTreeProvider`: resolves scope (store/website/default
  per core request-param semantics), resolves root stored paths via ONE bounded
  `entity_id IN` collection (SPEC-TASK-QQMVY4 core-proven invariant: LIKE prefix =
  root's stored path `1/<rootId>`, never bare id), then ONE category collection
  (`setStoreId`, `name/is_active`, `is_active=1`, roots+global-root excluded,
  `path LIKE <rootPath>/%` per root) folded into jstree nodes (core
  `retrieveCategoriesTree` fold pattern; children ordered by position).
- Root category NEVER a node (Product-Edit `rootVisible:false` semantics) →
  not selectable by construction. Foreign trees absent by the path filter.
  Multi-tree scopes (website with distinct roots / default) render each tree under
  a non-selectable label header node (`group_<rootId>`, never a numeric id).
- A tiny inline object `window.seocommAiCategoriesForm = {updateElement: <input>}`
  satisfies the core JS `jsFormObject.updateElement` contract — core JS unchanged.
- No new JS dependency; no ObjectManager; no direct SQL; no N+1; no hardcoded ids.

## 4. Data contract

`seocomm_ai_discoverability/urls/categories` value: comma-separated category
entity IDs (unchanged). Existing saved values load as checked nodes
(`initialSelection` from the hidden input value). Save persists IDs in the same
format via native config form POST. llms.txt generator untouched.

## 5. Acceptance criteria

1. Store scope: only that store group's tree; root not selectable; inactive
   categories absent; descendants selectable.
2. Website scope: own group trees only; distinct roots shown as labeled groups;
   foreign-website categories absent.
3. Default scope: union of all configured trees with group labels; no fake store.
4. Use Default / Use Website inheritance unchanged (native element + save flow).
5. Previously saved IDs appear checked after reload; save/reload round-trips.
6. Selected categories appear in /llms.txt Collections (regression only).
7. Runtime proof against real Magento data (tree JSON, root absence, foreign
   absence, save/reload, llms.txt output).
8. Guards: no OM, no direct SQL, no N+1, no new JS deps, no AiCommerce changes.

## 6. Out of scope

New category creation (Product-Edit "New Category" modal), AJAX lazy loading
(full tree rendered from treeJson; core JS supports but not needed at this
catalog size), jstree search (core checkbox tree has none; ui-select-only
feature), any llms.txt contract change.
