# TASK-NJ77PG — Scaffold Secomm_PaymentCore (module skeleton + config + db_schema + adapter contract)

**Type:** Task (slice của FEAT-CSWYEJ — foundation)
**Mode:** C (registration + config + declarative schema + contract interfaces; logic = 0; nhưng db_schema chạm Tier-2 DB → TL review bắt buộc)
**Placement:** `app/code/Secomm/PaymentCore/` (NEW)
**Risk tier:** Tier 2 (DB schema — table `secomm_paymentcore_payment` mới)
**Author:** AI draft · **Date:** 2026-08-25 · **Status:** Dev complete (static checks xanh — 34 PHP brace/paren + XML parse pass; runtime verify chờ user: enable + upgrade + compile)
**Specification:** MINI — embedded dưới đây (ID: TASK-NJ77PG) · canonical parent: [SPEC-FEAT-CSWYEJ](../specs/SPEC-FEAT-CSWYEJ-payment-core.md) (FULL, VALID) §4.2/§4.5/§4.6 · Plan: plans/TASK-NJ77PG-implementation-plan.md

## Mini Spec

### Goal

Module `Secomm_PaymentCore` load được: config admin đầy đủ (default-off), table schema + index tạo đúng, adapter contract (`PaymentProviderAdapterInterface` + `VerifyResult` + `AdapterPool`) tồn tại — **zero lifecycle behavior** (observer/cron là shell không hành động).

### Expected Behavior

- Enable + `setup:upgrade` + `setup:di:compile` xanh.
- Table `secomm_paymentcore_payment` với unique `order_id`, composite index `(status, expires_at)`.
- Admin config UI: section Secomm → Payment Core (enable, managed methods, default expiry 120', expiry overrides textarea, continue-disabled multiselect, cron batch size, force-close days 7).
- Logger channel riêng `secomm_paymentcore.log` (virtualType Monolog).

### Constraints / Rules

- Declarative schema only; whitelist json kèm theo. PHP 8.2+ `strict_types`.
- Managed methods source: `Allmethods`; config paths per spec §4.6.

### Acceptance Criteria

- [ ] AC-1: `module:enable Secomm_PaymentCore` + `setup:upgrade` xanh; table tồn tại với đúng index (SHOW INDEX).
- [ ] AC-2: Config section hiển thị đầy đủ fields; default: enabled=0, expiry=120, force_close=7, batch=50.
- [ ] AC-3: `AdapterPool` DI map rỗng mặc định (providers tự đăng ký) — core không reference provider nào.

### Out of Scope

Mọi lifecycle logic (assign observer TASK-JSQN6P, cron TASK-PMKWS6, adapter VNPAY TASK-KKPDNZ, continue TASK-7MHH19).

## Approach

Registration + module.xml (sequence: Store, Sales, Payment, Customer) + config.xml defaults + system.xml (tab `secomm` của Secomm_Base) + acl.xml + di.xml (virtualType logger) + db_schema/whitelist + Api interfaces + Model/Payment + repo + resource + Config + adapter contract trio + i18n vi/en + README/CHANGELOG.