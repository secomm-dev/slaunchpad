# Implementation Plan — TASK-5TGJ7V Category Tree Selector

| Field | Value |
|-------|-------|
| Ticket / Spec | TASK-5TGJ7V |
| Specification | `.ai/specs/SPEC-TASK-5TGJ7V-ai-discoverability-category-tree-selector.md` (status: VALID — Mode C MINI) |

Branch: `task/ai-discoverability-category-tree-selector` (base `ec8fd0e1`)

## Steps

1. `Model/Config/CategoryTreeProvider.php`: scope resolution (store/website/default
   request params), root stored-path lookup (bounded id-list collection), ONE
   options collection (stored-path LIKE per root, is_active=1, roots excluded),
   jstree node fold (core `retrieveCategoriesTree` pattern, position order,
   `group_<rootId>` header nodes for multi-tree scopes only).
2. `Block/Adminhtml/System/Config/CategoryTree.php`: frontend_model extending
   core `Magento\Config\Block\System\Config\Form\Field`; renders hidden input
   (native name/value) + tree div + x-magento-init for core `categoryCheckboxTree`
   + `window.seocommAiCategoriesForm.updateElement` bridge.
3. `system.xml`: categories field → `type="hidden"` + `frontend_model`; keep id,
   labels, sortOrder, showIn* flags. Remove now-unused multiselect source model
   usage; keep `Categories` source class only if still referenced elsewhere,
   otherwise delete with its test (replaced by provider tests).
4. Tests: new `CategoryTreeProviderTest` (store scope/tree shape/root+inactive+
   foreign exclusion/website single+multi/default/nested selection ids
   round-trip), generator regression untouched-but-run; remove/replace
   `CategoriesTest`.
5. Runtime proof on local docker: store vi_vn — tree JSON (descendants, no root,
   no foreign), select 2 nested categories via CLI config save, reload admin
   config form render with value → checked nodes, generate /llms.txt, confirm
   Collections entries.
6. Validation: targeted + full AiDiscoverability + AiCommerce suites, php -l,
   PHPCS, `setup:di:compile`, `project-ai-validate --check-specs`,
   `git diff --check`, config.php clean.
7. Evidence `.ai/evidence/TASK-5TGJ7V/implementation-report.md`; README +
   CHANGELOG 1.3.0.
8. Commit + push. NO merge.
