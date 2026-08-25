# TASK-5TGJ7V — Product-Edit-style Category Selector Evidence (correction round)

- Spec: `.ai/specs/SPEC-TASK-5TGJ7V-ai-discoverability-category-tree-selector.md` (Mode C MINI, corrected)
- Branch: `task/ai-discoverability-category-tree-selector` (base `ec8fd0e1`)
- Date: 2026-08-25

## 1. Why the previous UI did not match

Round 1 rendered the core jstree checkbox widget
(`Magento_Catalog/js/category-checkbox-tree`) as a permanently visible
folder tree. That widget is the promo-rule chooser pattern, NOT the Product
Edit Categories field. The requested UX is the Product Edit selector:
normal form field, chips for selected categories, dropdown with searchable
checkbox hierarchy, Done action.

## 2. Magento Product Edit files inspected (end-to-end trace)

| Step | Core file | Finding |
|---|---|---|
| 1. Form field | `vendor/magento/module-catalog/Ui/DataProvider/Product/Form/Modifier/Categories.php` (`customizeCategoriesField`, lines 244–342) | `category_ids` field: `formElement select`, `component Magento_Catalog/js/components/new-category`, `elementTmpl ui/grid/filters/elements/ui-select`, `filterOptions: true`, `chipsEnabled: true`, `disableLabel: true`, `levelsVisibility: '1'`, `options = getCategoriesTree()` |
| 2. JS component | `Magento_Catalog/js/components/new-category` | `define(['Magento_Ui/js/form/element/ui-select'], ...)` — pure extension of ui-select adding only the new-category modal plumbing (not needed here) |
| 3. Base implementation | `vendor/magento/module-ui/view/base/web/js/form/element/ui-select.js` (defaults 137–168) | `selectType: 'tree'`, `multiple: true`, `showCheckbox: true`, `closeBtn: true` + `closeBtnLabel: 'Done'`, `filterOptions` search with rate-limited filtering, chips via `getSelected()` |
| 4. Chips | `.../templates/grid/filters/elements/ui-select.html` (lines 63–98) | `chipsEnabled` branch renders `admin__action-multiselect-crumb` per selected option with per-chip remove button |
| 5. Checkbox tree | same template (lines 152–199) + `ui-select-optgroup.html` | recursive `optgroup` rendering, checkbox per option, `_expended` expand/collapse, `toggleOptionSelected` |
| 6. Search | same template (lines 126–150) + `filterOptionsList` | search input + filtered quantity; parent context via `getPath` (`showPath: true`) |
| 7. Option data | `Categories.php` `retrieveCategoriesTree()` (lines 429–473) | nested `{value, label, is_active, optgroup[]}` folded from ONE collection by parent refs, level+position sort |
| 8. Selected IDs | same class | array of category entity ids on the component `value`; multiselect comma semantics on persist |
| 9. Catalog wrapper | `new-category.js` | only new-category modal wiring — NOT reused (explicitly out of scope) |
| 10. "New Category" | separate modal (`create_category_modal`) | not part of the selector itself — omitted |

## 3. Implementation (correction)

- REUSED: `Magento_Ui/js/form/element/ui-select` + template
  `ui/grid/filters/elements/ui-select`, instantiated standalone via the core
  `Magento_Ui/js/core/app` bootstrap in `text/x-magento-init` (the mechanism
  core .phtml files use for UI components outside forms). Product-Edit field
  config reused verbatim (`filterOptions`, `chipsEnabled`, `disableLabel`,
  `levelsVisibility: '1'`).
- `Model/Config/CategoryTreeProvider::getOptions()` — options in the exact
  Product-Edit shape (nested `optgroup`), scope resolution + root stored-path
  filtering unchanged from the accepted round (SPEC-TASK-QQMVY4 invariant).
  The tree root is NOT an option → cannot be selected or saved (adapted at
  the provider, native selector untouched). Multi-tree scopes use
  `group_<rootId>` label headers (non-numeric values, filtered from the saved
  value client-side, ignored by the generator).
- `Block/Adminhtml/System/Config/CategoryTree` — native hidden input
  (element name/value → config save/load + Use Default/Use Website
  inheritance untouched) + scope div + app init + a ~10-line uiRegistry
  subscribe writing numeric ids comma-joined to the hidden input.
- Custom JS component: NO. Custom CSS: NO. Custom tree: NO.
  Standalone folder tree removed: YES (jstree block code deleted).
- No "New Category".

## 4. Runtime UAT (local admin, https://webhook.thanhaloha.io.vn/admin, 2026-08-25)

Full admin round-trip with a real admin session (curl, native login +
form_key + secret-key URLs; dedicated temporary admin user, removed after):

1. Opened Configuration → Secomm → AI Discoverability (store `default`):
   HTTP 200, field renders as the ui-select control (component config in
   page: `Magento_Ui/js/form/element/ui-select`, template
   `ui/grid/filters/elements/ui-select`, `filterOptions: true`,
   `chipsEnabled: true`, `levelsVisibility: '1'`).
2. Hierarchy: 48 options, nested `optgroup` (`3 => Gear`,
   `4 => Gear > Fitness Equipment`, `5 => Living Room`,
   `6 => Living Room > Seating`, ...).
3. Root non-selectable: option values contain NO root id (2 absent) and NO
   global root (1 absent) — verified in the rendered page JSON.
4. Foreign tree absent: 0 options outside root 2 (single shared tree in this
   install; DB cross-check in round-1 evidence).
5. Saved via the REAL admin form POST (exact form action
   `system_config/save/key/…/section/seocomm_ai_discoverability/store/default/`):
   `groups[urls][fields][categories][value]=4,3`.
6. DB: `core_config_data` row `stores/1 = '4,3'` created.
7. Reloaded the config page: hidden input `value="4,3"` AND component
   `value: [4, 3]` → chips for Gear + Fitness Equipment render on load.
8. Chips/search/Done/expand: core ui-select template behaviors (template
   citations §2) — no browser automation available in this environment, so
   verified from the rendered markup + core template code rather than
   screenshots; everything on the page is the unmodified core component.
9. llms.txt (store 1, fresh generation):
   ```
   ## Collections
   - [Fitness Equipment](https://webhook.thanhaloha.io.vn/gear/fitness-equipment.html)
   - [Gear](https://webhook.thanhaloha.io.vn/gear.html)
   ```
10. Store scoping preserved: provider tests cover store/website/default
    scopes with stored-path filters (unchanged from accepted round).

UAT cleanup: temporary admin user + ACL change reverted, test config rows
deleted, caches flushed.

## 5. Validation

| Check | Result |
|---|---|
| `CategoryTreeProviderTest` (7 tests, ui-select option shape) | 7 tests / 25 assertions, OK |
| Full AiDiscoverability suite | 54 tests / 117 assertions, OK (pre-existing allure warning) |
| AiCommerce regression suite | 51 tests / 83 assertions, OK |
| php -l / PHPCS Magento2 | clean / 0-0 |
| `setup:di:compile` | success |
| `project-ai-validate --check-specs` | VALID (0 FAIL, 0 WARN) |
| `git diff --check` / `app/etc/config.php` vs base | clean / empty diff |
