# CHANGE-AIDL-CINV1 (renamed from BUG-AIDL-CINV1) — Implementation Plan: llms.txt invalidation clean() contract fix

| Specification | SPEC-CHANGE-AIDL-CINV1 (`.ai/specs/SPEC-CHANGE-AIDL-CINV1-llms-cache-clean-contract.md`) |
|---|---|
| Branch | `fix/ai-discoverability-cache-invalidation` (base `9bd6cae9`) |
| Type | MINI — one model method-signature correction + regression test |

1. `Model/InvalidateCache.php` — replace both Zend-style `clean($mode, [tags])` calls with
   `clean([tag])`; docblock notes the Proxy contract.
2. `Test/Unit/Model/InvalidateCacheTest.php` (new) — cleanAll pins
   `clean(['seocomm_llms'])`; cleanStore pins `clean(['seocomm_llms_store_3'])` with the
   tag coming from the real provider method; cleanStores dedupe/delegate; assert the
   single-array-argument contract (mode-string regression).
3. Validation: AiDiscoverability suite, AiCommerce regression, php -l, PHPCS,
   `project-ai-validate --check-specs`, `git diff --check`.
4. Bounded runtime proof (warm → exists → invalidate → removed → regen → cleanup).
5. Evidence report, CHANGELOG entry, review gate, push branch (no merge).
