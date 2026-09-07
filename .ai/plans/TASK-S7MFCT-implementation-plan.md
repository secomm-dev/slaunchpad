# Implementation Plan — TASK-S7MFCT Category Selector Scope Fix

| Field | Value |
|-------|-------|
| Ticket / Spec | TASK-S7MFCT |
| Specification | `.ai/specs/SPEC-TASK-S7MFCT-ai-discoverability-category-scope-fix.md` (status: VALID — Mode C Mini-Spec) |

Branch: `task/ai-discoverability-category-scope-fix` (base `origin/dev/development/thanhle` = `3ac91a0b`)

## Steps

1. Rewrite `Model/Config/Source/Categories`:
   - DI: `CategoryCollectionFactory` (existing), `StoreManagerInterface`,
     `RequestInterface`
   - Scope resolution: request `store` param → store tree; else `website`
     param → distinct group roots (one → tree; many → labeled union);
     else default → labeled union of all trees; group roots + entity 1 always
     excluded from options
   - Collection: `setStoreId` (store scope only), `addAttributeToSelect('name')`,
     `is_active=1`, `path` LIKE per resolved roots, one query
   - Labels: breadcrumb from `path` + same-collection id→name map;
     root-name prefix only in multi-root/default scopes; `[ID: n]` fallback;
     sort by label asc; cached in property (existing behavior)
2. Unit tests `Test/Unit/Model/Config/Source/CategoriesTest.php`
   (mocked collection/store manager/request) covering the 10 ACs.
3. Docs: README note + CHANGELOG entry.
4. Validation: targeted + full AiDiscoverability suite, AiCommerce suite
   (regression), php -l, PHPCS, `project-ai-validate --check-specs`,
   `git diff --check`; config.php diff must be empty; DI wiring changes only
   via constructor → run `setup:di:compile`.
5. Evidence: `.ai/evidence/TASK-S7MFCT/implementation-report.md`.
6. Commit + push branch. NO merge.
