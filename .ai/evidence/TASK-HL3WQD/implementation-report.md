# TASK-HL3WQD — Implementation Report

Spec: SPEC-TASK-HL3WQD · Branch: `task/ai-discoverability-configurable-endpoints-and-cache`
(base 39f88530 = dev/development/thanhle 8441688b + origin/development) · Date: 2026-08-26

## Implemented

1. **Cache type** `secomm_ai_discoverability`: `etc/cache.xml` +
   `Model/Cache/Type` (TagScope/FrontendPool, pattern of
   `Magento\Integration\Model\Cache\Type`, verified in vendor).
   `LlmsTxtProvider` + `Model/InvalidateCache` run through the type.
2. **Section titles**: `titles` config group (11 fields, store-view scoped,
   defaults = previous constants); `Config::getSectionTitle()`;
   generator + commerce source consume.
3. **Endpoint base path**: `seocomm_ai_commerce/general/endpoint_path`
   (default `ai`), single normalization authority
   `AiCommerce\Model\Config::normalizeEndpointPath()` + save-time backend
   model; `Controller/Router` config-driven; `etc/frontend/routes.xml`
   removed (single routing authority — old path fully retires).
4. **Enable/disable**: pre-existing runtime enforcement verified in all four
   controllers; llms.txt advertises nothing when disabled/absent.
5. **BUG provenance**: SPEC-BUG-AIDL-CINV1 family → SPEC-CHANGE-/CHANGE-
   (git mv; provenance notes; references updated in spec/plan/evidence/
   CHANGELOG/InvalidateCache docblock); rule encoded in `.ai/AGENTS.md` §8.7.

## Evidence

- **Unit tests**: AiDiscoverability 72 tests / 193 assertions OK; AiCommerce
  88 tests / 131 assertions OK (incl. new normalization matrix, backend
  canonicalization, router custom-path/boundary/405/400/sku, source URL
  advertisement default+custom path with no `/ai/` residue, title fallbacks,
  TagScope invalidation contract).
- **PHPCS (Magento2)**: new/changed classes — 0 errors.
- **php -l**: all changed files clean.
- **Runtime proof** (docker dev env):
  - `bin/magento setup:upgrade` → `cache:status` lists
    `secomm_ai_discoverability`; `cache:enable` → 1;
    `cache:clean secomm_ai_discoverability` → "Cleaned cache types".
  - Redis bounded proof: warm key `zc:k:798_SEOCOMM_LLMS_TXT_STORE_1` EXISTS=1
    → `cache:clean secomm_ai_discoverability` → EXISTS=0 → one GET /llms.txt
    → EXISTS=1 (lazy regen through the new type).
  - `GET /llms.txt` via tunnel: default headings unchanged, sections intact.
  - `GET /ai/store?store=default` via tunnel: 200 JSON after router rewrite
    and routes.xml removal (default path still resolves).
- **Hardcoded `/ai` grep review**: remaining hits are comments/docs/labels
  describing the default value `ai` (no code constructs URLs from a literal).
  Admin label made path-neutral ("Enable AI Read Endpoints").
- **`project-ai-validate --check-specs`**: only 2 pre-existing failures for
  SPEC/plan N1VBSM (untouched by this task, verified via git status/log).

## BUG Artifact Audit — mapping table

| OLD | NEW / ACTION |
|---|---|
| `.ai/specs/SPEC-BUG-AIDL-CINV1-llms-cache-clean-contract.md` | `git mv` → `.ai/specs/SPEC-CHANGE-AIDL-CINV1-llms-cache-clean-contract.md`; header retitled + provenance note appended; content unchanged |
| `.ai/plans/BUG-AIDL-CINV1-implementation-plan.md` | `git mv` → `.ai/plans/CHANGE-AIDL-CINV1-implementation-plan.md`; spec ref updated |
| `.ai/evidence/BUG-AIDL-CINV1/implementation-report.md` | `git mv` → `.ai/evidence/CHANGE-AIDL-CINV1/implementation-report.md`; provenance rename section appended (incl. note that the PASSING review-gate run was independent) |
| `CHANGELOG.md` 1.4.1 entry | Spec reference updated with rename note (history preserved) |
| Commit messages fb7b4d40 / 8441688b | Immutable — recorded in this mapping (per "don't destroy history") |
| Legit QA-originated BUG artifacts | None existed for these modules — nothing else to reclassify |

## Known behaviors / risks

- New cache types default to disabled after `setup:upgrade`; enabled via
  `cache:enable secomm_ai_discoverability` (done in the dev env; note for
  deployment/TL).
- `AiCommerce general/enabled` config default 0→1 (task §3/§9). This env has
  a saved value (1) — unchanged. Fresh installs expose the read-only surface
  by default.
- Two sections configured with identical titles merge (documented).
- `app/etc/config.php` drift (cache_states row) — standing env drift, never
  committed.
