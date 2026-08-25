# Secomm_AiDiscoverability

Baseline AI Discoverability (AIEO) for the Secomm Launchpad storefront: serves a curated,
deterministic [`/llms.txt`](https://llmstxt.org/) at the site root so AI agents and LLM-based
crawlers can discover the store's key public pages.

Spec: `.ai/specs/SPEC-TASK-0X552E-ai-discoverability-llms-txt.md` (LC-30).

## What it does

- `GET /llms.txt` → `200 text/plain; charset=UTF-8` with a deterministic, curated document
  (`# Site`, `> summary`, `Locale:`, `Currency:`, `## Sections`, Markdown links
  `- [Label](url)` with optional `: Description` from existing `meta_description` only).
- `HEAD /llms.txt` → same headers, empty body. `If-None-Match` → `304`.
- Feature disabled for the store view → explicit `404` (GET and HEAD).
- Non-GET/HEAD requests never reach generation (standard Magento routing).
- `<link rel="describedby" href=".../llms.txt">` injected into `head.additional` on frontend
  pages when enabled.
- Admin preview (`Secomm_AiDiscoverability::preview` ACL) renders the **same bytes**
  as runtime via the provider's fresh (cache-bypassing) seam.

No robots.txt changes, no new DB tables, no cron, no new dependencies.

## Configuration

Stores → Configuration → **Secomm → AI Discoverability** (`seocomm_ai_discoverability`),
all values store-view scoped:

| Field | Path | Default | Notes |
|---|---|---|---|
| Enabled | `general/enabled` | `0` | 404 when off |
| Site / Brand Title | `general/site_title` | — | H1 title; falls back to store information name, then store view name |
| Brand Summary | `general/brand_summary` | — | one-line `>` summary; falls back to Site / Brand Title, never the internal store view name |
| Priority Paths | `general/priority_paths` | — | one internal path per line (e.g. `sales/guest/form`) |
| CMS Pages | `urls/cms_pages` | — | multiselect, max 20 rendered |
| Categories | `urls/categories` | — | multiselect, max 20 rendered; scoped to the edited store view's category tree (website/default scope: that scope's trees with disambiguated breadcrumb labels) |
| Include Sitemap References | `urls/include_sitemap_refs` | `1` | link existing sitemap files |
| Cache Lifetime | `cache/lifetime` | `86400` | seconds |
| Max URLs | `cache/max_urls` | `100` | global bound across all sections |

## Generation behavior

1. **Sources** (fixed section order): Priority Pages (home + configured paths) → Collections
   (configured categories) → Pages (configured CMS pages) → Sitemap references →
   Machine-readable Commerce (only when `Secomm_AiCommerce` is present AND its
   `seocomm_ai_commerce/general/enabled` flag is set for the store view; discovery
   metadata only — no endpoint execution, no catalog load; SPEC-TASK-7FBHHC).
2. **Eligibility filter** (`EligibilityChecker`): rejects admin/api/rest/graphql, checkout,
   cart, customer, account, wishlist, search, review, oauth, `llms*`, `robots.txt`, and any
   URL carrying a query string or fragment.
3. **Indexability reuse** (`SeoPolicy`): reads the *site's existing* SEO policy — Mirasvit
   `seo/general/noindex_pages2` noindex rules (wildcard patterns; options 1/2 = excluded) —
   no meta-robots rules are invented here.
4. **Canonical policy** (`CanonicalPolicy`): bounded, offline-resolvable rules only — query
   strings stripped, duplicate slashes collapsed, site-wide trailing-slash policy applied,
   base URL from the store.
5. **Collector**: cross-section dedupe by URL, natural-case-insensitive label sort, global
   `max_urls` bound (section order preserved; a notice is logged when truncated).

### Mirasvit SEO — soft integration (no hard dependency)

`SeoPolicy` reads raw config paths (`seo/general/noindex_pages2`, `seo/url/trailing_slash`) and
checks `Mirasvit_Seo` enablement via `Module\Manager`. It never references a Mirasvit class, so
`setup:di:compile` and the endpoint keep working when the SEO suite is absent or disabled —
with Mirasvit absent the policy fails open to "indexable / path as-is".

`CanonicalPolicy::getCategoryUrl()` reproduces the proven Mirasvit category-canonical rule:
among non-redirect `url_rewrite` rows for the category in the store, the row with the **lowest
`url_rewrite_id`** (oldest rewrite) wins.

### Known canonical limitations (by design)

- Conditional/regex canonical rewrite rules and cross-domain canonicals cannot be resolved
  offline; the bounded policy above is used instead.
- CMS page URLs are the page identifier path (CMS pages are self-canonical on this install).

## Cache & invalidation

- Cache ID `seocomm_llms_txt_store_{storeId}`, tags `seocomm_llms` + `seocomm_llms_store_{id}`,
  lazy regeneration on request, configurable lifetime. No cron.
- Targeted invalidation observers:
  - module config section saved, or Mirasvit `seo` section saved → all stores;
  - CMS page/category save/delete commit → affected store views (all when global);
  - sitemap model save/delete commit (there is no dedicated `clean_sitemap` event in this
    installation — a `core_abstract_save_commit_after` observer filters by `instanceof Sitemap`)
    → affected store.
- Admin preview bypasses the cache entirely (fresh generation).

## HTTP contract

| Aspect | Behavior |
|---|---|
| Methods | `GET` 200 body, `HEAD` 200 empty body; disabled → 404 both |
| Headers | `Content-Type: text/plain; charset=UTF-8`, `Cache-Control: public, max-age=3600`, `ETag: sha1(body)` |
| Conditional | matching `If-None-Match` → `304` |
| Session | endpoint code starts no session and sets no cookies (see README note below) |

> Environment note: on the local docker stack a `PHPSESSID` cookie is set on **every** frontend
> response, including core `robots.txt` — a third-party module starts the session at the
> front-controller level. Not caused by or fixable in this module.

## Tests

37 unit tests / 76 assertions covering the llms.txt v2 Markdown link format (exact bytes,
optional description, sanitization/bounds), config title fallback chain + deterministic
currency read, formatter determinism & sanitization, eligibility,
collector bound/dedupe/sort, SEO policy (JSON + legacy serialize + wildcard + trailing slash),
canonical policy (oldest-rewrite, query strip, home URL):

```
docker exec slaunchpad-phpfpm-1 bash -c \
  "cd /var/www/html && vendor/bin/phpunit -c dev/tests/unit/phpunit.xml app/code/Secomm/AiDiscoverability/Test/Unit"
```

## Explicit non-goals (scope guard, LC-30)

No `llms-full.txt`, no MCP/UCP/ACP endpoints, no chatbot or agent cart/checkout, no public
commerce API, no embeddings/vector DB, no AI-generated content, no new Schema.org engine,
no Cloudflare/AI-crawler gating, no full catalog dump.

## Change log

See [CHANGELOG.md](CHANGELOG.md).
