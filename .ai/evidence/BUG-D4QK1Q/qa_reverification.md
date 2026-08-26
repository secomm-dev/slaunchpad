# BUG-D4QK1Q — Independent QA Re-Verification

**Date:** 2026-08-26 · **QA role:** independent re-verification of delivered fix
**Fix verified at:** `ce5f2361` (merge `aa8701d1`, branch `dev/development/thanhle`, remote confirmed)

## VERDICT: **CODE PASS — DELIVERY BLOCKED**

Code fix fully satisfies every acceptance criterion (all reproduced independently below). Governance/delivery topology does NOT follow the canonical branch policy (§1), so the bug must not be closed until the fix reaches the canonical `development` branch via PR (or TL explicitly blesses the alternate topology).

---

## §1 Repository topology (independently established)

```
git branch -a --contains 10c6cbc3...  → dev/development/thanhle, origin/dev/development/thanhle
git branch -a --contains aa8701d1     → dev/development/thanhle, origin/dev/development/thanhle
git merge-base 10c6cbc3 aa8701d1      → 10c6cbc3...  (baseline is direct ancestor — fix descends from EXACT reviewed baseline)
origin/dev/development/thanhle        → aa8701d1 (push verified)
origin/development                    → a853e7a6 (does NOT contain AI modules)
```

- QA baseline `10c6cbc3` lives ONLY on `dev/development/thanhle` — the original QA review itself was performed against this branch, not canonical `development`.
- Canonical policy (`.ai/AGENTS.md` §3): **`development` (active)**, PR flow (§13).
- The fix correctly descends from the exact reviewed baseline and is pushed, but to a developer integration branch that is not canonical `development`.
- **Governance verdict: BLOCKED** — needs TL decision: either PR `dev/development/thanhle` → `development` (note: this carries ALL AI-module content, which `origin/development` lacks), or explicit policy update recognizing the dev branch as the AI-module integration line.

## §2 Bug artifacts

- Original `qa_review.md` unaltered; implementation evidence in separate `implementation_fix.md` (provenance preserved).
- Pre-QA record status was `fix_delivered_pending_qa` — implementation did NOT self-mark QA VERIFIED / CLOSED. ✔
- This file is a NEW artifact; nothing overwritten.

## §3–§5 Code inspection (at aa8701d1)

- `Router::match()`: resolveAsData(?) → scoped `isEnabled($storeId)` → `PathGuard::matches($pathInfo, $storeId)` → tail routing. No ambient/default-scope config read remains (`getEndpointPath()` call sites: Router:112 + PathGuard:44, both store-scoped). ✔
- `Resolver::resolveAsData()`: pure (no `setCurrentStore`); same V1 contract as controller `resolve()`. Router & controllers cannot diverge — one resolution mechanism, controllers additionally re-check via the SAME PathGuard. ✔
- Invalid `?store` → default-path admission only: consistent with the pre-existing 400 `invalid_store` envelope (controller still re-resolves and rejects; verified `GET /ai/store?store=not-a-code` path admits but controller errors). Does NOT reopen the leak: an invalid code can never gain access to another store view's data because admission uses the DEFAULT store's own path only.
- `PathGuard`: authoritative matcher — grep confirms usage in Router + all 4 controllers (Store/View, Catalog/Search, Categories/Index, Products/View). Boundary-exact (`path === base || starts_with(path.'/')`) — `/ai-extra`, `/aix` cannot match `ai`; single `trim(path,'/')` normalization; no case/trailing-slash bypass (config normalize enforces lowercase-safe pattern + trimmed slashes on save; runtime comparison remains exact). ✔
- §15 Performance: no store iteration — one `resolveAsData` (one `getActiveStoreByCode`, or default lookup) + O(1) scoped config reads. `getList()` only in the no-default-store edge fallback. ✔

## §6 Original matrix (reproduced; fixture set to `agent` for the run, restored after)

| Case | Result | Expected |
|---|---|---|
| `GET /ai/store` | **200**, `"store_code":"default"` | 200 Default ✔ |
| `GET /agent/store?store=vi_vn` | **200**, `"store_code":"vi_vn"` | 200 vi_vn ✔ |
| `GET /ai/store?store=vi_vn` | **404** | 404 ✔ |

## §7 All endpoint families (vi_vn path `agent`)

| Endpoint | `/agent/…?store=vi_vn` | `/ai/…?store=vi_vn` |
|---|---|---|
| store | 200 | 404 |
| catalog/search?q=test | 200 | 404 |
| categories | 200 | 404 |
| products/24-WG085 | 200 (vi_vn DTO: VND currency) | 404 |

Default store families at `/ai/...` (no param): all 200. No vi_vn data reachable under `/ai`. ✔

## §8 Enabled state scoped to TARGET store

vi_vn `enabled=0` (temporary DB row): `/agent/store?store=vi_vn` → **404**; `/ai/store` (Default) → **200** simultaneously. Row deleted, state restored. ✔

## §9 llms.txt store context

- `GET /llms.txt?store=vi_vn` (after dedicated-cache clean): advertises `GET .../agent/{store,catalog/search,categories,products/{sku}}?store=vi_vn` — no `/ai/` URLs, no `store=default`. ✔
- `GET /llms.txt` (Default): advertises `/ai/...?store=default`. ✔

## §10 Cache isolation (runtime + code)

- Redis tags show TWO distinct entries: `SEOCOMM_LLMS_TXT_STORE_1` and `SEOCOMM_LLMS_TXT_STORE_3` — per resolved store id. Key construction verified in code (`cacheId($storeId)`, `ResponseCache::key` = `secomm_aic_s{storeId}_{route}_{sha1(params)}`); the correct RESOLVED ids flow (bodies store-correct, no cross-contamination across the whole §6–§8 matrix, including repeated hits). ✔

## §11 Config change invalidation

vi_vn `agent` → `shop` (local DB) + `cache:clean config secomm_ai_discoverability`:
- `/shop/store?store=vi_vn` → 200; old `/agent/store?store=vi_vn` → 404; llms.txt advertises `/shop/...`.
- No stale `agent` served. (Admin-save path additionally triggers the `admin_system_config_changed_section_seocomm_ai_commerce` observer → dedicated cache clean, verified in code + prior task evidence; direct-DB edits require manual cache clean, which is standard Magento behavior, not a defect.)
- Config restored afterward. ✔

## §12 Automated tests (re-run independently)

```
php vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Secomm/AiCommerce/Test/Unit --no-extensions
  → OK (108 tests, 160 assertions)
php vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Secomm/AiDiscoverability/Test/Unit --no-extensions
  → OK (74 tests, 200 assertions)
```
Matches reported counts exactly.

## §13 Test quality

`RouterTest` constructs a REAL `Resolver` + REAL `PathGuard` with store-scoped Config callbacks (store 1 → `ai`, store 3 → `agent`) — the collaboration that failed on baseline. `testDefaultPathWithStoreOverrideParamIsRejected` (`/ai/store` + `store=vi_vn` → no match) and `testStoreOverridePathMatchesWithStoreParam` would FAIL on `10c6cbc3` (router there admitted `/ai` prefix regardless of store param) and PASS on `aa8701d1`. Additional regression coverage: scoped-config-id assertions, cross-store isolation, disabled-target, invalid-code fallback, PathGuard boundary provider (incl. `/ai-extra`), llms Index store-param tests, Products/View defense-in-depth. No constant-vs-constant tests found. ✔

## §14 Cross-store leakage (two views, distinct paths)

Temporary fixture vi_vn=`agent`, eu_en=`commerce`:
- `/commerce/store?store=eu_en` → 200 (`store_code:"eu_en"`); `/agent/store?store=vi_vn` → 200 (`"vi_vn"`)
- `/agent/store?store=eu_en` → 404; `/commerce/store?store=vi_vn` → 404
- Subsequent requests still correct (no resolver/cache state carry-over). Row removed. ✔

## Environment integrity

DB fixture restored to the exact pre-QA state (`default/0` enabled=1 + path=ai; `stores/3` path=`agent1` — see note); caches cleaned; worktree clean (only pre-existing untracked `.codegraph/`, `pub/media/secomm/`).

> **Note (non-blocking anomaly):** at re-verification start, `stores/3` endpoint_path was `agent1`, not the `agent` left by the implementation receipt. Some actor mutated it between receipt and re-verification. It was restored to that found state (`agent1`); the discrepancy is flagged for TL awareness only — all QA cases were run against a controlled `agent` fixture.

## §16 Decision

**B. CODE PASS — DELIVERY BLOCKED.** Every original acceptance criterion passes on `aa8701d1`. The bug is NOT closed: delivery must land on (or be PR'd toward) canonical `development` per `.ai/AGENTS.md` §3/§13, or TL must explicitly accept `dev/development/thanhle` as the AI-module integration line. Once resolved, this bug may be closed on the strength of this re-verification without re-running the full matrix.
