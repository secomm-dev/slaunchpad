# [SLP][TASK-HL3WQD] AI Discoverability — dedicated cache type, configurable titles & endpoint path

Specification ID: SPEC-TASK-HL3WQD

> **Refs**: LC-30 follow-up · **Mode**: A (spec-first) · **Status**: IMPLEMENTED (2026-08-26)
> **Parent specs**: SPEC-TASK-0X552E (baseline), SPEC-TASK-QYZMF1 (v1.1), SPEC-TASK-7FBHHC (commerce seam)
> **Scope guard**: no new endpoints, no capability change — cache type ownership, admin-configurable presentation, and de-hardcoding the `/ai` base path. Read-only surface unchanged.

---

## 1. Dedicated cache type (§1)

- New Magento-native cache type `secomm_ai_discoverability`:
  `etc/cache.xml` + `Model/Cache/Type` (TagScope over `FrontendPool`, pattern of
  `Magento\Integration\Model\Cache\Type`). Tag: `SEOCOMM_AI_DISCOVERABILITY`.
- `LlmsTxtProvider` saves/loads through the type: entries carry the type tag
  automatically; `bin/magento cache:status` lists the type;
  `cache:clean secomm_ai_discoverability` removes exactly the llms.txt entries;
  `cache:flush <type>` flushes the shared backend (core `Cache\Manager::flush`
  accepts type ids) — wider than clean, documented, not required.
- Disabling the type in Cache Management disables llms.txt caching outright
  (AccessProxy: saves skipped, loads miss → regenerate per request).
- Store scoping unchanged: cache id `seocomm_llms_txt_store_{id}`, store tag
  `seocomm_llms_store_{id}`; `InvalidateCache` cleans through the type with
  Zend-style `clean($mode, $tags)` — the TagScope contract, explicitly NOT the
  `App\Cache\Proxy` `clean(array)` contract (see SPEC-CHANGE-AIDL-CINV1).
- Store-view safe: no cross-store leakage (per-store id + tag); invalidation
  never touches unrelated caches (type-tag scoped).

## 2. Configurable section titles (§2)

- All user-visible llms.txt headings configurable per store view under
  Secomm → AI Discoverability → **Section Titles** (`titles` group), read via
  `Model\Config::getSectionTitle()`:
  `store_summary`, `agent_guidance`, `priority_pages`, `featured_collections`,
  `key_pages`, `machine_commerce`, `store_information`, `product_search`,
  `product_categories`, `product_detail`, `commerce_limitations`.
- Defaults (`Config::SECTION_TITLES` + `etc/config.xml`) reproduce the exact
  pre-change output. Empty/whitespace value ⇒ default (structure never
  degrades). Known collision: configuring two sections with identical titles
  merges those sections (documented, accepted).
- Endpoint sub-headings (`### …`) are Discoverability presentation strings —
  sourced from the same titles config in `CommerceEndpointsSource`.

## 3–5. Configurable AI read endpoint base path (§3, §5)

- New config `seocomm_ai_commerce/general/endpoint_path` (default `ai`,
  store-view scoped) + `Enable AI Read Endpoints` stays
  `seocomm_ai_commerce/general/enabled` (default now `1` per task; deployments
  with a saved value — including this one — are unaffected).
- **Single normalization authority**:
  `Secomm\AiCommerce\Model\Config::normalizeEndpointPath()` — trim whitespace
  and surrounding slashes; accept `^[A-Za-z0-9][A-Za-z0-9/_-]*$` ≤64 chars
  (rejects empty, bare `/`, query, fragment, protocol/scheme-relative URLs,
  `.`/`..` traversal, spaces, other chars); invalid/empty ⇒ `ai`.
  Save-time canonicalization via backend model
  `Model/Config/Backend/EndpointPath` — raw admin input never persists.
  `AiDiscoverability\CommerceEndpointsSource` only joins the stored canonical
  value (slash-trim + default guard); it never re-implements validation.
- **De-hardcoded routing**: `AiCommerce\Controller\Router` resolves the base
  path from config each request; the module no longer declares a standard
  frontName route (`etc/frontend/routes.xml` removed), so the router is the
  single routing authority — changing the path cannot leave the old `/ai/*`
  URLs alive behind the standard router. Boundary-safe matching
  (`ai` ≠ `ai-extra`).
- llms.txt advertises the effective URLs (configured path) automatically.

## 4. Enable/disable enforcement (§4)

- Runtime enforcement pre-existed and is retained: every AiCommerce controller
  returns 404 when disabled for the resolved store; `CommerceEndpointsSource`
  advertises nothing when AiCommerce is absent/disabled — no dead URLs.
- Config changes (path, enable, titles) invalidate llms.txt via the existing
  `admin_system_config_changed_section_*` observers.

## 6–8. BUG provenance (§6–§8)

- Rule encoded in `.ai/AGENTS.md` §8.7 (SPEC-BUG discipline: no self-QA BUGs;
  independent origin only; audit reclassification procedure).
- Applied: SPEC-BUG-AIDL-CINV1 family renamed to SPEC-CHANGE-AIDL-CINV1 /
  CHANGE-AIDL-CINV1 (git mv, history preserved, references updated) — mapping
  table in the task receipt.

## 9–10. Compatibility & tests (§9, §10)

- Defaults keep output byte-identical: path `ai`, titles = shipped defaults,
  endpoints usable when enabled. Existing `/ai/*` URLs keep resolving.
- Tests: normalization matrix (`ai`, `/ai`, `ai/`, `/ai/`, ` ai `, `agent`,
  `api/v1` + invalid set), scoped config read, backend save canonicalization,
  router matrix (default, custom path retires `/ai`, boundary no-match, 405,
  400, sku param), source URL advertisement (default + custom path, no `/ai/`
  residue, disabled/absent ⇒ none, custom headings), generator/Config title
  fallbacks, InvalidateCache TagScope contract.

## 11. Verification

php -l, full AiDiscoverability (72) + AiCommerce (88) unit suites, PHPCS,
`project-ai-validate --check_specs`, `git diff --check`, hardcoded-`/ai` grep
review, runtime cache-type proof (`cache:status`, clean, llms regen).
