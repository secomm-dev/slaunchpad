# [SLP][TASK-7FBHHC] AI Discovery → Commerce Endpoint Integration

Specification ID: SPEC-TASK-7FBHHC

> **Mode**: C (Mini-Spec) · **Type**: follow-up integration · **Status**: VALID (TL-approved ticket scope; bounded, no risk category)

## 1. Problem & Scope

`Secomm_AiCommerce` (LA-22, accepted) serves anonymous read-only commerce
endpoints under `/ai/*`. Its README explicitly defers `/llms.txt` integration to
a follow-up ticket. `Secomm_AiDiscoverability` (LC-30, accepted) serves
`/llms.txt` but does not advertise the commerce surface.

This task connects the two: **discovery metadata only**.

**Explicit out-of-scope (hard constraints):**
- Discovery integration ONLY — no redesign of either module
- No cart/checkout/payment/order APIs
- No MCP/UCP/ACP
- No GraphQL exposure
- No raw catalog dump
- No new DB tables
- No new dependencies (composer or module)
- No AiCommerce public API behavior change

## 2. Requirements

### 2.1 Machine-readable Commerce section (Change 1)

`/llms.txt` gains a deterministic final section `## Machine-readable Commerce`,
rendered ONLY when, for the current store view:
1. module `Secomm_AiCommerce` is present (`ModuleListInterface::has()`), AND
2. config `seocomm_ai_commerce/general/enabled` is set (store scope read).

Advertised entries (existing bounded read-only surface, current store base URL
and current store code):

```
## Machine-readable Commerce
- [Store Information](<base>/ai/store?store=<code>)
- [Product Search](<base>/ai/catalog/search?store=<code>)
- [Categories](<base>/ai/categories?store=<code>)
- Product Detail: <base>/ai/products/{sku}?store=<code>
```

The Product Detail entry is a **route template** (`{sku}` placeholder), emitted
as a plain-text entry — never a Markdown link (it is not a resolvable URL).

Constraints:
- AiCommerce endpoints are NEVER executed while generating llms.txt
- No catalog data is loaded for this section (pure config + module presence)
- Store-view isolation preserved (base URL + `store` code from the generated
  store, same as every other section)
- No AiCommerce PHP class is referenced — the seam is soft
  (ModuleList + ScopeConfig path constants), so DI compile cannot break when
  `Secomm_AiCommerce` is absent/disabled
- Section participates in the existing global dedup + `max_urls` bound like
  every other section (deterministic ordering: last section)

### 2.2 System CMS page hardening (Change 2)

`EligibilityChecker` gains a bounded CMS-identifier denylist evaluated at the
CMS-source level: the Magento utility system pages `enable-cookies` and
`no-route` are never emitted, even if an admin selects them. Exact-identifier
match only — no pattern/prefix matching, so legitimate merchant CMS pages with
other identifiers are unaffected.

### 2.3 README / config path correction (Change 3)

Audit of `Secomm_AiDiscoverability/README.md` against `system.xml` found three
mismatches (runtime code is correct — docs only):

| Field | README (wrong) | Runtime (correct) |
|---|---|---|
| CMS Pages | `general/cms_pages` | `urls/cms_pages` |
| Categories | `general/categories` | `urls/categories` |
| Include Sitemap References | `general/include_sitemap_refs` | `urls/include_sitemap_refs` |

All other table rows match. Runtime config paths unchanged (no code bug).
Stale follow-up note in `Secomm_AiCommerce/README.md` updated to point at this
integration.

### 2.4 Cache invalidation

When `seocomm_ai_commerce` config changes (event
`admin_system_config_changed_section_seocomm_ai_commerce`), AiDiscoverability
drops its llms.txt cache (same `cleanAll()` policy as its own section event —
the payload carries no reliable scope). Module presence changes
(`module:enable/disable`) are deployment operations that already flush cache;
no extra behavior.

## 3. Acceptance Criteria

1. AiCommerce enabled (module present + flag set): llms.txt ends with the
   Machine-readable Commerce section, exact entry shapes above.
2. AiCommerce disabled (flag off) OR module absent: section completely absent.
3. URLs use the current store base URL and `store=<code>` of the generated
   store view.
4. Generating the section requires no AiCommerce service execution and no
   catalog access (by construction: only ModuleList + ScopeConfig reads).
5. `enable-cookies` / `no-route` CMS pages never appear even if selected.
6. Existing llms.txt formatting is byte-stable when the section is absent
   (existing tests unchanged in expectation).
7. Existing LC-30 and LA-22 test suites still pass.
8. No hard dependency: DI compile succeeds with Secomm_AiCommerce disabled.
9. `app/etc/config.php` unchanged on the branch.

## 4. Approach

- New `Service/Source/CommerceEndpointsSource` in AiDiscoverability (only new
  class); injected into `LlmsTxtGenerator` as the last section.
- `EligibilityChecker::isEligibleCmsIdentifier()` + call in `CmsPagesSource`.
- New observer `AiCommerceConfigInvalidation` + one `events.xml` entry.
- README (both modules) + CHANGELOG doc updates.
