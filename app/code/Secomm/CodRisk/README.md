# Secomm_CodRisk

COD Blacklist & Basic Risk Control (TASK-YPWH9B / external: LC-26).

## Purpose

Giảm rủi ro **bom hàng COD** theo **normalized shipping phone**: Blacklist, Spam Order, Allowlist, Historical Risk → quyết định `ALLOW | WARNING | BLOCK` chỉ ảnh hưởng khả năng dùng **COD**, không đụng payment khác, không chặn checkout.

> Một module duy nhất (quyết định 2026-09-21): availability hook cho Magento default CashOnDelivery nằm trong module này qua plugin — risk logic không nằm trong payment module, core payment logic không đổi.

## Features (P1)

- **Phone risk identity** — chuẩn hóa E.164 VN (`0901 234 567` → `+84901234567`); guest = registered; invalid phone không bao giờ tạo BLOCK.
- **Rule engine** — RulePool đăng ký qua DI, precedence cố định 10 Blacklist → 20 Spam → 30 Allowlist → 40 Historical (business precedence, không phải config). Allowlist chỉ bypass Historical.
- **Risk events** — normalized theo Reason catalog, có snapshot `include_in_historical_count` lúc ghi; nguồn: ADMIN/CUSTOMER (record tay từ Order View) + seam `RiskEventRecorderInterface` cho carrier sau này.
- **Spam Order** — burst event customer-attributable trong window (đếm trên event store đã index, không scan `sales_order`); match không tự blacklist.
- **Order View section** — luôn hiển thị trên mọi order COD: decision live + nút Record Risk Event / Add to Blacklist / Add to Allowlist / Deactivate Blacklist / Override per-order (BLOCK → ALLOW, bắt buộc reason, có audit).
- **Admin** — Phone Inspector, Risk Lists (1 grid chung BLOCK/ALLOW), Risk Events, Evaluations (log WARNING/BLOCK), Audit Log; ACL 1 resource `Secomm_CodRisk::manage` full quyền.
- **Config** `Stores → Configuration → Secomm → COD Risk` — kill-switch (default **disabled**), lookback 180 / warning 2 / block 3, spam window/threshold, reason include flags theo website.

## How it works

```text
COD availability (plugin CashOnDeliveryAvailabilityPlugin — trong module này)
→ CodRiskEvaluatorInterface.evaluate(context: phone + website)
→ RulePool (sorted DI) → base decision
→ Order View chỉ thêm per-order override (checkout không đụng override)
→ ALLOW/WARNING → COD available · BLOCK → COD hidden
```

## Installation / rollback

```bash
bin/magento module:enable Secomm_CodRisk
bin/magento setup:upgrade && bin/magento cache:flush
```

Rollback schema: `DROP TABLE secomm_cod_risk_list, secomm_cod_risk_event, secomm_cod_risk_evaluation, secomm_cod_risk_override, secomm_cod_risk_audit;`

## Notes

- Tab config nằm dưới tab `secomm` (định nghĩa bởi `Secomm_Base`).
- Message "COD unavailable" cho customer đã cấu hình được text nhưng **chưa wire vào checkout** (chạm Mageplaza OSC → ticket Tier-2 riêng).
- Chi tiết spec: `.ai/specs/SPEC-TASK-YPWH9B-cod-risk-control.md`.
