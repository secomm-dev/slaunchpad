Specification ID: SPEC-TASK-5TGJ7V

# SPEC-TASK-5TGJ7V — Hierarchical Magento-native Category Tree Selector (AI Discoverability)

> **Correction (2026-08-25)**: the first implementation rendered a standalone
> jstree folder widget — NOT the requested UX. The corrected UX is the exact
> Product Edit Categories interaction: `Magento_Ui/js/form/element/ui-select`
> (chips in the input, dropdown arrow opens a searchable checkbox tree, Done
> closes). §3 is rewritten accordingly; the jstree widget (old §2.3) is
> REJECTED for this field. No "New Category" support (selector only picks
> existing categories).



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

## 3. Approach (corrected: Product-Edit ui-select pattern)

Reuse the CORE component chain Product Edit uses, verbatim:

- Component: `Magento_Ui/js/form/element/ui-select` with template
  `ui/grid/filters/elements/ui-select` (core defaults: `selectType: 'tree'`,
  `showCheckbox: true`, `closeBtn: true` + label "Done", `filterOptions: true`
  search, `chipsEnabled: true` chips — ui-select.js defaults lines 137–168;
  chips markup, search input, checkbox rows and nested `optgroup` recursion in
  ui-select.html lines 63–198 + ui-select-optgroup.html).
- Instantiation: standalone UI component via the core
  `Magento_Ui/js/core/app` bootstrap in `text/x-magento-init` (the same
  mechanism core .phtml templates use outside UI forms), bound to a scope div.
- Option data: `Model\Config\CategoryTreeProvider::getOptions()` — nested
  `{value, label, optgroup[]}` exactly as core
  `Categories::retrieveCategoriesTree()` produces for Product Edit
  (Categories.php:429–473); scope resolution + root stored-path filtering per
  SPEC-TASK-QQMVY4. The tree root is NOT an option (Product Edit shows it as
  context; here it must never be curatable → omitted server-side, so it can
  never be selected or saved). Multi-tree scopes wrap each tree in a
  `group_<rootId>` label header (non-numeric value, filtered from the saved
  value client-side and ignored by the generator).
- Value bridge: the field's native hidden input keeps the config
  name/value (save/load + Use Default/Use Website inheritance untouched); a
  subscribe on the component's `value` observable writes the numeric ids
  comma-joined into that input (uiRegistry glue, ~10 lines, no custom
  component/template/CSS).
- No "New Category" button (out of scope by request).

`system.xml`: field `type="text"` + `frontend_model`
`Secomm\AiDiscoverability\Block\Adminhtml\System\Config\CategoryTree` (extends
core `Magento\Config\Block\System\Config\Form\Field`). No OM, no direct SQL,
no N+1, no hardcoded ids, no third-party JS.

## 4. Data contract

`seocomm_ai_discoverability/urls/categories` value: comma-separated category
entity IDs (unchanged). Existing saved values load as selected chips
(component `value` from the hidden input). Save persists IDs in the same
format via the native config form POST. llms.txt generator untouched.

## 5. Acceptance criteria

1. Closed state: normal form field with removable chips + dropdown arrow.
2. Open state: dropdown with search input, checkbox hierarchy, expand/collapse,
   Done close action — all core ui-select behaviors.
3. Store scope: only that store group's tree; root not selectable; inactive
   categories absent; descendants selectable.
4. Website scope: own group trees only; distinct roots shown as labeled groups;
   foreign-website categories absent.
5. Default scope: union of all configured trees with group labels; no fake store.
6. Use Default / Use Website inheritance unchanged (native element + save flow).
7. Previously saved IDs render as chips after reload; save/reload round-trips.
8. Selected categories appear in /llms.txt Collections (regression only).
9. Runtime UAT against the real admin runtime (render, save POST, reload,
   llms.txt output).
10. Guards: no OM, no direct SQL, no N+1, no new JS deps, no AiCommerce changes.

## 6. Out of scope

New category creation (Product-Edit "New Category" modal — explicitly not
wanted), AJAX lazy loading (full option tree rendered inline), any llms.txt
contract change.
