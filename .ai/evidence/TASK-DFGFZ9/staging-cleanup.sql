-- ============================================================================
-- TASK-DFGFZ9 phase 3 closure note: secomm_cod_collection now also carries
-- active_order_claim (per-order claim) — row deletes below cover the whole table, no
-- separate cleanup needed for the column. NEVER executed automatically.
-- ============================================================================
-- TASK-DFGFZ9 phase 3 — STAGING CLEANUP (manual run ONLY — AI/executables must NOT run this)
-- ============================================================================
-- Purpose : remove development-era COD-related rows so the pre-release staging DB matches a
--           fresh install. Product code has NO dependency on any of these rows.
-- Target  : staging MySQL (the `launchpad` database on staging).
-- Backup  : run BEFORE anything else and keep the dump:
--             mysqldump -h <staging-host> -P <port> -u <user> -p <database> \
--               secomm_ghn_shipment secomm_ghtk_shipment core_config_data patch_list \
--               > cod_phase3_backup_$(date +%F).sql
-- Rule    : SELECT every preview below FIRST, review the entity_id list, then run only the
--           DELETEs whose preview matches expectations. LESSONS_LEARNED: SELECT before mutate.
-- WARNING : do NOT delete anchor rows that an ACTIVE provider flow still references (an
--           in-flight GHN/GHTK order being reconciled). If a previewed row belongs to a
--           shipment that is currently mid-flow, exclude its entity_id from the DELETE.
-- Note    : sandbox orders L8T* referenced by some GHN rows continue to exist on the GHN
--           sandbox side — informational only; they are not touched from here.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. PREVIEW — GHN anchor rows (expect ~7 dev rows: 6 SUBMITTED L8T* sandbox codes
--    + 1 FAILED CANONICAL_UNRESOLVED, all cod_amount = 0.0000, dated 2026-09-15/16)
-- ---------------------------------------------------------------------------
SELECT entity_id, client_order_code, magento_order_id, magento_shipment_id,
       ghn_order_code, provider_status, provider_reason_code, cod_amount, created_at
FROM secomm_ghn_shipment
ORDER BY entity_id;

-- ---------------------------------------------------------------------------
-- 2. PREVIEW — GHTK anchor rows (expect 0 rows on staging; the table was added in phase 2)
-- ---------------------------------------------------------------------------
SELECT entity_id, partner_order_code, magento_order_id, provider_status, cod_amount, created_at
FROM secomm_ghtk_shipment
ORDER BY entity_id;

-- ---------------------------------------------------------------------------
-- 3. PREVIEW — legacy + moved COD config rows (expect 0 rows; nothing to clean if empty)
-- ---------------------------------------------------------------------------
SELECT config_id, scope, scope_id, path, value
FROM core_config_data
WHERE path IN ('secomm_shippingcore/cod/payment_methods',
               'secomm_cod/payment_identification/payment_methods');

-- ---------------------------------------------------------------------------
-- 4. PREVIEW — obsolete migration patch entry (inert once the patch class is removed from
--    the codebase; deleting it keeps patch_list clean on staging)
-- ---------------------------------------------------------------------------
SELECT patch_id, patch_name, created_at
FROM patch_list
WHERE patch_name = 'Secomm\\Cod\\Setup\\Patch\\Data\\MigrateLegacyCodPaymentMethodConfig';

-- ============================================================================
-- DELETEs — run ONLY after reviewing the previews above. Keep this file as the
-- record of what was approved for deletion.
-- ============================================================================

-- 5. GHN dev anchor rows (review entity_ids from preview 1 — adjust the IN list if any row
--    belongs to an active flow, see WARNING above)
-- DELETE FROM secomm_ghn_shipment WHERE entity_id IN (1, 2, 3, 4, 5, 6, 7);

-- 6. GHTK anchor rows (no-op while the table is empty)
-- DELETE FROM secomm_ghtk_shipment;

-- 7. Legacy/moved COD config rows (no-op while preview 3 is empty)
-- DELETE FROM core_config_data
--  WHERE path IN ('secomm_shippingcore/cod/payment_methods',
--                 'secomm_cod/payment_identification/payment_methods');

-- 8. Obsolete migration patch entry (no-op while preview 4 is empty)
-- DELETE FROM patch_list
--  WHERE patch_name = 'Secomm\\Cod\\Setup\\Patch\\Data\\MigrateLegacyCodPaymentMethodConfig';

-- 9. Verify after cleanup — all four queries must return 0 rows:
SELECT COUNT(*) AS ghn_rows FROM secomm_ghn_shipment;
SELECT COUNT(*) AS ghtk_rows FROM secomm_ghtk_shipment;
SELECT COUNT(*) AS cod_config_rows FROM core_config_data
 WHERE path IN ('secomm_shippingcore/cod/payment_methods',
                'secomm_cod/payment_identification/payment_methods');
SELECT COUNT(*) AS legacy_patch_rows FROM patch_list
 WHERE patch_name = 'Secomm\\Cod\\Setup\\Patch\\Data\\MigrateLegacyCodPaymentMethodConfig';
