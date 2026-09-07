# Category URL Rewrite Integrity Audit — 2026-08-25

- Repository: `thanhle74/slaunchpad` · Integration base: `dev/development/thanhle` @ `0719e4f2`
- Branch: `audit/category-url-rewrite-integrity` (audit artifact only — no code change)
- Trigger: TASK-5TGJ7V runtime investigation — vi_vn `/llms.txt` emitted no `## Collections` because store 3 had ZERO category `url_rewrite` rows.
- Mode: READ-ONLY. No data mutation was performed during this audit. (The two store-3 rows from the earlier TASK-5TGJ7V runtime proof remain and are called out below.)

## 1. Magento core behavior (2.4.8-p5, inspected in this repo's vendor tree)

| Question | Finding (file evidence) |
|---|---|
| 1. Normal lifecycle of category `url_rewrite` rows | Event-driven on category save, NOT an indexer. `module-catalog-url-rewrite/etc/events.xml`: `catalog_category_prepare_save`, `catalog_category_save_before`, `catalog_category_save_after`, `catalog_category_move_after` → `CategoryProcessUrlRewriteSavingObserver::execute()` regenerates via `CategoryUrlRewriteGenerator` + `UrlRewriteBunchReplacer`. |
| 2a. New store view created AFTER categories exist | **NO hook exists.** No observer/plugin on store or store-group creation anywhere in `module-url-rewrite` / `module-catalog-url-rewrite` / `module-store`. Rows for the new store are backfilled ONLY when a category is subsequently saved with a qualifying change. This is a long-standing core gap (products partially cover it via `UpdateProductWebsiteUrlRewrites` plugin on website updates — categories have no equivalent). |
| 2b. Store assigned to existing root category | Same as 2a — no generation; group root assignment alone never touches `url_rewrite`. |
| 2c. URL keys unchanged | `CategoryProcessUrlRewriteSavingObserver::isCategoryHasChanged()` requires `dataHasChangedFor('url_key')` or `is_anchor` or changed product assignments. **Unchanged re-save is a no-op for rewrites** — this is why `CategoryRepository::save()` in the TASK-5TGJ7V investigation created no rows. Global-scope saves (`generateForGlobalScope`) iterate `$category->getStoreIds()` and generate per store only when a qualifying change fires. |
| 3. Responsible process | The `catalog_category_save_after` observer chain above. There is NO mview/indexer for `url_rewrite` (`catalog_url_rewrite_product_category` is a product-category association index, not the rewrite table). |
| 4. Official/indexer/CLI regeneration | **None in core 2.4.8.** No `catalog:url_rewrites:*` command exists; `indexer:reindex` does not rebuild `url_rewrite`. Community modules add such CLIs; the repo does not include one. |
| 5. `CategoryUrlRewriteGenerator` + `UrlPersistInterface` as repair tooling | Appropriate. This IS the same public API the core observer uses (`generate()` → bunch-replace). `UrlPersistInterface::replace()` maintains consistency (deletes+reinserts per entity/store). Not raw SQL. Appropriate for a bounded, per-category repair loop. |
| 6. Should rows exist per store view? | Yes — for every store that can render the category, core generates one canonical row per store (plus redirect/history rows). A store with an active shared catalog and ZERO rows is incomplete data, not an alternative architecture. |
| 7. Mirasvit interaction (this repo: `app/code/Mirasvit/Seo*`, enabled) | Audited `app/code/Mirasvit/Seo`: it regenerates **product** URL templates (`ProductUrlRegenerateService`), applies trailing-slash/suffix config (`TrailingSlashService`, `ApplyTrailingSlashPlugin`), and plugins the frontend `UrlRewriteRouter` (request-time only). Nothing intercepts, deletes, or suppresses **category** rewrite generation. Not the cause. Note: `core_config_data` `catalog/seo/{product,category}_url_suffix` were both set to NULL (empty) on 2026-08-25 07:42:45 — consistent with a trailing-slash/suffix config action — which is why the two proof rows and the regenerated store-1 rows are suffix-less (`gear`, not `gear.html`). That config change was NOT made by this audit. |

`magento-spec` MCP: no stored reference covers url-rewrite lifecycle (`search_standards` "url rewrite category store view generation" → no matches); findings above come from direct inspection of the installed core, per the "do not guess" requirement.

## 2. Runtime data audit (read-only, docker db)

Stores (all active, both groups use root_category_id 2; 48 active categories under root `1/2`):

| store_id | code | website_id | root_category_id | active cats under root | category url_rewrite | product url_rewrite | missing category rewrite | coverage |
|---|---|---|---|---|---|---|---|---|
| 1 | default | 1 | 2 | 48 | 48 | 144 | 0 | 100% |
| 2 | us_en | 1 | 2 | 48 | 0 | 0 | 48 | 0% |
| 3 | vi_vn | 1 | 2 | 48 | 2 (**runtime-proof artifacts only**: entities 3, 4) | 0 | 46 | 4% |
| 4 | eu_en | 2 | 2 | 48 | 0 | 0 | 48 | 0% |
| 5 | fr_fr | 2 | 2 | 48 | 0 | 0 | 48 | 0% |

- Store 3's 2 rows: `gear` → `catalog/category/view/id/3`, `gear/fitness-equipment` → `catalog/category/view/id/4`, `redirect_type=0`, `is_autogenerated=1` — generated in the TASK-5TGJ7V investigation via the core generator (see §1.5). Without them store 3 would also be 0%.
- Sample missing categories (canonical non-redirect row absent for that store): every category except {3,4} on store 3 (e.g. 5 `Training`, 6 `Fitness Equipment`-tree under 5, 12–50 …); all 48 on stores 2/4/5.
- Store-scoped `url_key` values exist ONLY for entities 3, 4 at store 1 (created during earlier admin/UAT testing) — i.e. no store view has ever had a category url_key edit that would have triggered per-store regeneration.

## 3. Storefront HTTP proof (cache-busted `?nc=…`, base https://webhook.thanhaloha.io.vn)

| Store | `/gear` | `/gear/fitness-equipment` | Note |
|---|---|---|---|
| default | 200 | 200 | rows exist |
| vi_vn | **200** (`<title>Gear`, canonical `…/gear`) | 200 | proof rows exist |
| us_en | **404** | 404 | no rows |
| eu_en | **404** | 404 | no rows (an earlier non-busted 200 was FPC residue) |
| fr_fr | **404** | 404 | no rows |

Magento's frontend `UrlRewriteRouter` resolves strictly from `url_rewrite` rows; no fallback mechanism produces category URLs without them. us_en homepage exposes no category links, so the gap is currently latent on storefront navigation but breaks any direct/deep category URL and all programmatic URL generation for stores 2/4/5.

## 4. Repository customization search

- `app/code/Secomm/*`: no plugin/observer/CLI touching `url_rewrite` (AiDiscoverability only READS rewrites in `CanonicalPolicy::getCategoryUrl`).
- `app/code/Mirasvit/Seo*`: §1.7 — product-side regeneration + request-time routing only; not causal.
- No importer/catalog-sync custom code in the repo creates store views or categories.

## 5. Root cause verdict

**Stores 2, 4, 5 (and 3 until the proof rows) never received category `url_rewrite` rows because they were created after the catalog data existed, and Magento core has NO store-creation backfill for category rewrites (§1.2a). The categories were never subsequently saved with a qualifying url_key/is_anchor change (§1.2c), so the only event-driven path never fired.**

Classification:
- **F — expected Magento core behavior / no defect in this repo's code** (core gap), with
- **B — deploy/index process gap**: the environment's store-view provisioning step lacks the follow-up category-rewrite backfill for newly created store views.

Explicitly ruled out: custom code (C), Magento configuration (D — suffix change affects format only), Mirasvit (E), index corruption.

## 6. Impact

- **Storefront**: direct category URLs 404 on us_en/eu_en/fr_fr (stores 2/4/5); vi_vn has exactly 2 working category URLs (proof rows).
- **llms.txt**: `## Collections` correctly empty for stores 2/4/5 (`CanonicalPolicy::getCategoryUrl` → null → entries omitted; never fabricated). vi_vn emits only categories 3, 4.
- **Products**: stores 2/4/5 also have ZERO product rewrites — same latent gap, same repair path; flagged for scope.

## 7. Repair recommendation (NOT executed — TL authorization required for mutation)

**Repair required: YES** — data-only, one-time (class A), using the core API proven in §1.5:

- Per target store S, for each active category under the group root: load via `CategoryRepository::get($id, S)`, `CategoryUrlRewriteGenerator::generate()`, `UrlPersistInterface::replace($urls, $entityId, $storeId)` — i.e. exactly the mechanism core uses on save, without touching category data itself. Consider also product rewrites (larger job — separate decision).
- No raw SQL, no truncate, no broad delete. Run per store after a DB backup, then verify per-store coverage == 48 and storefront 200s.
- **Process change (B)**: when provisioning a new store view on an existing catalog, add a backfill step (same script) to the runbook/deploy tooling — otherwise every future store view inherits the gap.
- **Code change needed: NO.** No `Secomm_AiDiscoverability`/AiCommerce change is required; generator behavior (omit when no canonical rewrite) is correct and now regression-covered by `CategoriesSourceTest`.

## 8. Audit-phase safety confirmation

- No `url_rewrite` rows were added, changed, or deleted during this audit.
- No reindex/rewrite script executed; no catalog data, Mirasvit config, llms code, or AiCommerce code touched.
- The 2 pre-existing proof rows (store 3, entities 3, 4) are documented in §2 and counted in coverage.
