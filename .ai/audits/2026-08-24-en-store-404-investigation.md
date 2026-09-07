# Investigation — English store view `/en/` returns 404 (multi-store routing)

- **Date:** 2026-08-24
- **Branch:** `task/en-store-404-investigation` (from `dev/development/thanhle` @ `22997372`)
- **Scope:** LOCAL-ONLY diagnosis. No demo/staging/prod touched, no code changes, no speculative fixes.
- **Type:** Investigation artifact (no implementation — awaiting owner review)

---

## A. Local environment used

- Docker stack `slaunchpad-*` (markoshust images: nginx 1.24 app container, php 8.3-fpm, MariaDB 10.4)
- Local storefront base URL: `https://webhook.thanhaloha.io.vn/` (Cloudflare tunnel → local nginx)
- Web root `/var/www/html` = repo `compose/src`; nginx vhost = `compose/nginx/default.conf` (bind-mounted to `/etc/nginx/conf.d/default.conf`)

## B. Local store config (differs from demo remote — do not assume IDs)

`bin/magento store:list`:

| ID | Website | Group | Name | Code |
|----|---------|-------|------|------|
| 1  | 1 | 1 | Default Store View | `default` |
| 2  | 1 | 1 | US English View | `us_en` |
| 3  | 1 | 1 | Vietnam Store View | `vi_vn` |
| 4  | 2 | 2 | EU English View | `eu_en` |
| 5  | 2 | 2 | French Store View | `fr_fr` |

DB `core_config_data` (relevant rows):

- `web/secure/base_url` = `web/unsecure/base_url` = `https://webhook.thanhaloha.io.vn/` — **only at `default` scope; no store/website-scoped base URL rows**
- `web/url/use_store` — no row (effective default `0`); after investigation reverted to explicit `0` (same behavior)
- `web/default/cms_home_page` — no row (effective default `home`); CMS page `home` (page_id 2) active, assigned to store 0 (all)
- `general/locale/code` default scope = `vi_VN`

Infra routing (compose/nginx/default.conf):

```nginx
map $request_uri $detected_mage_code { default default; }
...
fastcgi_param MAGE_RUN_CODE $detected_mage_code;   # always "default"
fastcgi_param MAGE_RUN_TYPE store;
```

The `/au/`, `/nz/` location blocks + the `map` are **stock markoshust docker-magento sample leftovers** — no store-specific mapping exists. No `MAGE_RUN_*` usage anywhere in repo code (only the bind-mounted nginx conf). No custom store-routing plugins/observers/routers in `app/code` (the only custom router is LC-30 `Secomm_AiDiscoverability\Controller\Router`, root `/llms.txt` only). Store switcher = Magento core `Magento_Store` switch block (links `.../stores/store/redirect/___store/<code>/...`).

## C. Reproduction matrix (local, use_store=0 — current state)

Resolved store probed via `StoreResolver::getCurrentStoreId()` in a diagnostic script (evidence: `/tmp/store-resolve.php` inside the container):

| URL | HTTP | Resolved store code | What Magento does with the path |
|---|---|---|---|
| `/` | 200 | `default` | CMS `home` |
| `/en/` | 404 | `default` | `en` stays in path info → no route, no CMS page identifier `en` → no-route 404 |
| `/us_en/` | 404 | `default` | same |
| `/vi_vn/` | 404 | `default` | same |
| `/launchpad_en/` | 404 | `default` | same (and no such store locally) |

Canonical for `/` = `https://webhook.thanhaloha.io.vn/` (no prefix).

## D. Strategy A test — `web/url/use_store = 1` (temporarily enabled, then reverted)

After `config:set web/url/use_store 1` + cache flush:

| URL | HTTP | Notes |
|---|---|---|
| `/` | 200 | still resolves `default` |
| `/us_en/` | 200 | title "Home page"; canonical `https://.../us_en/` |
| `/vi_vn/` | 200 | |
| `/eu_en/` | 200 | (website 2 shares same base URL here) |
| `/en/` | **404** | no store with code `en` exists |
| `/default/` | 200 | **caveat:** default store canonical became `https://.../default/` |

Verified under A:

- Store switcher emits native `___store` redirect links targeting `/us_en/`, `/vi_vn/` — works natively.
- Static/media URLs unaffected (no store prefix) — `.../static/version.../frontend/Hyva/default/vi_VN/...`.
- LC-30 `/llms.txt` works at BOTH `/llms.txt` (default store) and `/us_en/llms.txt` (English store) — 200 both; store-code prefix is stripped by core `PathInfoProcessor` before the LC-30 router matches, and `StoreResolver` picks the right store, so per-store llms.txt resolution stays correct.
- All generated links inside `/us_en/` pages carry the `/us_en/` prefix (native `Store::getBaseUrl()` behavior) — forms, checkout URLs, category/product URLs consistent.

Reverted to `use_store 0`; re-verified `/` 200, `/us_en/` 404 (baseline restored).

## E. Root cause (Phase 4 answers)

1. **Why `/en/` 404s:** `web/url/use_store = 0` means core `Magento\Store\App\Request\PathInfoProcessor` never interprets the first path segment as a store code — it is left in the path. `en` matches no module front name, no CMS page identifier, no URL rewrite → no-route 404. Additionally nginx always injects `MAGE_RUN_CODE=default`, so no request can ever start in another store.
2. **What Magento interprets `en` as:** nothing — an unroutable path segment on store `default` (not a store code, not a CMS identifier, not a custom route, not a webserver mapping).
3. **With `use_store=0`, how would EN be selected?** Only by (a) separate domain/base URL per website/store, or (b) `MAGE_RUN_CODE` env mapping (webserver-level), or (c) the `___store` cookie/switcher (`/stores/store/switch`) — all path-less approaches. The current setup has none of (a)/(b) wired for EN.
4. **Intended architecture:** none is actually implemented. The repo ships the untouched markoshust sample (`/au/`, `/nz/`, `map → default`). There is no partial `/en/` implementation — nothing is broken; **the feature was never configured**.
5. **Partial implementation broken?** No. Absent, not broken. The earlier remote attempt "set EN base URL to root domain" cannot work: both store views share one base URL and `use_store=0`, so the base URL never selects the store.
6. Safest solution: see H.

## F. Existing routing architecture (as-is)

- Single domain, single vhost, `MAGE_RUN_CODE` hardcoded to `default` for every request.
- `use_store = 0`; no store-scoped base URLs; no per-store nginx locations (only sample `/au/`, `/nz/`).
- 2 websites / 5 store views locally; demo remote: 1 website, stores `default` + `launchpad_en`.
- No custom store routing code in `app/code`; no Hyva store-switcher override (core switch block).

## G. Options

### A. Native store-code URLs (`use_store = 1`, store code stays `launchpad_en`)
- ✅ Zero code, pure config; core-supported; switcher/canonical/sitemap all consistent.
- ❌ Public URL becomes `/launchpad_en/`, NOT the required `/en/`. **Rejected as-is.**

### B. `use_store = 1` + store code renamed `launchpad_en` → `en`
- ✅ Produces exactly `/en/` natively; URL generation, canonical, switcher, checkout POST URLs, sitemap all correct by construction (everything derives from store code).
- ✅ Store code is admin-editable; all Magento data keys on `store_id` (config `core_config_data.scope=stores` uses scope_id=store_id, url rewrites, CMS assignments, LC-30 config) — a code rename does not touch them.
- ⚠️ Risks (manageable): (1) any hardcoded `launchpad_en` references in deploy scripts/seeders/integrations must be grepped (none found in repo today); (2) with `use_store=1` the **default store also gets its code in generated URLs** — canonical of `/` becomes `/default/` (verified locally). Mitigation: nginx 301 `/default/...` → `/...`, or accept `/default/` canonical; (3) a CMS page/route literally named `en` would be shadowed (none exists); (4) cookie path stays `/` (same domain) — store cookie handles selection, verified switcher works.

### C. Keep code `launchpad_en`, expose `/en/` via prefix mapping
- nginx `location /en/` + `map` → `MAGE_RUN_CODE=launchpad_en` + path rewrite: request routing works, **but every generated link** (`Store::getBaseUrl()`) still points at `/` (no prefix) → clicking any link drops back to VI store; forms/checkout/canonical/switcher inconsistent. Fixing that requires overriding URL generation anyway.
- Custom `PathInfoProcessor` plugin aliasing `en` → `launchpad_en` + a `getUrl()` rewrite so generation emits `/en/`: that is re-implementing option B with a custom plugin — permanent maintenance surface, PHPCS/upgrade risk, and it shadows a future real store code `en`.
- ❌ **Rejected**: "Nginx hack" fails the task's own criterion (URL generation must remain correct), and the plugin variant is strictly worse than renaming the code.

## H. Recommended fix (owner decision required — NOT implemented)

**Option B**: on the demo environment,
1. Rename store view code `launchpad_en` → `en` (admin: Stores → All Store Views → English → Code). `store_id` unchanged → no data migration.
2. Set `web/url/use_store = 1` (default scope).
3. Decide default-store URL policy: either accept `/default/`-prefixed canonicals, or add an nginx 301 `location /default/ { return 301 /...; }` (and keep `MAGE_RUN_CODE` map as-is; the store cookie resolves returning visitors).
4. Flush config + FPC caches.

Result: `/` = VI (`default`), `/en/` = English — native, zero code.

## I. Exact files/config that would change

| Item | Change |
|---|---|
| Magento admin (DB) | store view code `launchpad_en` → `en`; `web/url/use_store` → 1 |
| `compose/nginx/default.conf` (optional, both local + demo vhost) | 301 `/default/*` → `/*` if the team wants unprefixed VI canonicals; remove dead `/au/`/`/nz/` sample blocks while touching the file |
| No PHP code | none |

## J. Risks / regression surface

- `/default/` canonical + duplicate-home (`/` vs `/default/`) — needs the 301 decision (SEO).
- Sitemap (Magento_Sitemap per store) will emit prefixed URLs for EN — correct, but re-generate/re-submit.
- Any external system referencing `/launchpad_en/` URLs (none in repo; demo logs/GA should be checked by owner).
- LC-30 `/llms.txt`: verified compatible (both `/llms.txt` and `/en/llms.txt` resolve per store; `describedby` link uses current store base URL → will correctly emit `/en/llms.txt` on EN pages).
- Cookies/session: same-domain cookie path `/`; store selection via core store cookie — switcher verified working; cart is website-scoped (EN shares website 1 with VI locally; on demo EN is also website 1 → shared cart/checkout session, by design).
- Cache: full-page cache varies by store — no change needed.

## K. Implementation type

Magento config + admin change only; optional single nginx location; **no code**.

---

## Evidence commands (re-run-able)

```bash
docker exec slaunchpad-phpfpm-1 bash -c 'cd /var/www/html && php bin/magento store:list'
docker exec slaunchpad-phpfpm-1 mysql -hdb -umagento -pmagento magento \
  -e 'SELECT scope,scope_id,path,value FROM core_config_data WHERE path LIKE "web/%base_url" OR path="web/url/use_store" OR path="web/default/cms_home_page"'
# HTTP matrix
for p in / /en/ /us_en/ /vi_vn/; do curl -sk -o /dev/null -w "$p %{http_code}\n" https://webhook.thanhaloha.io.vn$p; done
```

Temporary state used during testing (all reverted): `web/url/use_store` set 1 → tests → set back 0; caches flushed.
