# TASK-NJ77PG Implementation Plan — Scaffold Secomm_PaymentCore

| Field | Value |
|---|---|
| Specification | tickets/TASK-NJ77PG-paymentcore-scaffold.md (`## Mini Spec`, embedded) · canonical parent specs/SPEC-FEAT-CSWYEJ-payment-core.md (FULL, VALID) §4.2/§4.5/§4.6 |
| Decisions | DEC-FEATCSWYEJ-001 (D2 table riêng) · DEC-FEATCSWYEJ-003 (default 15') · DEC-FEATCSWYEJ-004 (bỏ querydr) |

> **Mode C** · Tier 2 (DB schema) · Status: **Retro-canonical** — distilled 2026-08-25 từ work đã Dev complete + runtime verified (enable/upgrade/compile xanh qua user QC session).

---

## PART 1 — ANALYSIS

| Câu hỏi | Kết luận | Nguồn |
|---|---|---|
| Lưu expires_at ở đâu? | Table riêng `secomm_paymentcore_payment` (unique order_id, index status+expires_at) — không đụng sales tables | DEC-FEATCSWYEJ-001 D2 |
| Default expiry? | 15' — đổi từ 120' sau DEC-003 (TTL provider session) | DEC-FEATCSWYEJ-003 |
| Adapter contract gồm gì? | `getMethodCode` + `getCheckoutUrl` (đã bỏ `isPaymentCompleted` theo DEC-004) | DEC-FEATCSWYEJ-004 |
| Logger channel? | Handler class riêng (`Logger/Handler.php` → `var/log/secomm_paymentcore.log`) — virtualType System handler không hoạt động (runtime-verified) | QC session 2026-08-25 |
| ACL placement? | Dưới `Magento_Config::config` (pattern Secomm_Tracking) | audit `app/code/Secomm/Tracking/etc/acl.xml` |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | Registration + module.xml (sequence: Store/Sales/Payment/Customer/Directory/Secomm_Base) | registration.php · etc/module.xml | ✅ |
| 2 | Config tree + admin UI | etc/config.xml · etc/adminhtml/system.xml · etc/acl.xml | ✅ |
| 3 | Declarative schema + whitelist | etc/db_schema.xml · db_schema_whitelist.json | ✅ |
| 4 | Api/Data + Model + ResourceModel + Repository | Api/*.php · Model/{Payment,PaymentRepository}.php · Model/ResourceModel/** | ✅ |
| 5 | Config resolver + adapter contract | Model/Config.php · Model/Adapter/{PaymentProviderAdapterInterface,AdapterPool}.php | ✅ |
| 6 | Logger channel | Logger/Handler.php · di.xml virtualType | ✅ (fix runtime: Handler riêng) |
| 7 | i18n + README + CHANGELOG | i18n/{vi_VN,en_US}.csv · README.md · CHANGELOG.md | ✅ |
| 8 | Runtime verify | `module:enable` + `setup:upgrade` + `di:compile` xanh (user QC 2026-08-25) | ✅ |

## Remaining steps (human)

1. TL code review Tier-2 (DB schema + config tree).
2. `SHOW INDEX FROM secomm_paymentcore_payment` xác nhận index composite (AC-1 ticket).

## Verification summary

| Mini-Spec clause | Bằng chứng |
|---|---|
| Table + index đúng | db_schema.xml + whitelist parity (dev-evidence.md) |
| Config fields đầy đủ, default off | system.xml + config.xml (default enabled=0, expiry=15) |
| Core 0 provider dependency | grep sweep — 0 class import Vnpayment (AC-013) |
