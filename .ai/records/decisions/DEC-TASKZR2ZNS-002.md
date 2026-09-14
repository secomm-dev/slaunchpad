---
id: DEC-TASKZR2ZNS-002
title: POS adapter convention (final 2026-09-11) — Core + Pancake (vendor logic) + PancakeBridge (wiring); không tách module Function riêng
status: proposed             # chờ TL approve (Tier 2: architecture module, order lifecycle)
owners: [tl, sa]
decision_type: architecture
approval_date:
created: 2026-09-11
last_verified: 2026-09-11
verified_against_commit:
supersedes: [DEC-TASKZR2ZNS-001]
superseded_by:
work_items: [TASK-ZR2ZNS]    # ticket mirror SLP-30/MIG-002 (collapse) + CONV/FNC/BRG/MIG-001
---

# Decision Record: POS adapter convention (final 2026-09-11) — Core + Pancake (vendor logic) + PancakeBridge (wiring)

<!-- AI draft 2026-09-11 từ MIG-002 (ticket .cursor/tasks/SLP-30/[SLP-30][MIG-002]-Collapse-function-into-pancake.md + plan 2026-09-11-pancake-plus-bridge-only.md) — chờ TL approve. -->

## Context

DEC-TASKZR2ZNS-001 (2026-09-10, chưa approve) chốt 3 lớp: Core + `Secomm_PancakeBridge` (wiring) + `Secomm_PancakeFunction` (vendor logic), kèm deprecate `Secomm_Pancake`. Sau 1 ngày vận hành layout này, MIG-002 (2026-09-11) collapse lại: 3 module riêng biệt buộc duy trì 3 bộ CHANGELOG/README/version composer + DI preference bắc cầu (`PosApiConfigInterface` do Bridge implement cho Function), trong khi `Secomm_PancakeFunction` không có consumer nào ngoài chính adapter Pancake — chi phí tách lớn hơn lợi ích. Ticket MIG-002 không ghi lý do tường minh; quan sát trên là từ diff + evidence.

## Decision

1. **Convention POS adapter = Core + vendor module + Bridge**: `Secomm_FulfillmentCore` (platform) + **`Secomm_Pancake`** (vendor logic: PosClient, PayloadBuilder, OrderPayloadParser, StatusMapper/Catalog, PosWarehouseCatalog, `PosApiConfigInterface`, `ServiceCode`) + **`Secomm_PancakeBridge`** (Magento wiring: config/admin UI, exporter, cron/CLI/webhook, data patches, DI pool registration).
2. **Dependency một chiều**: `PancakeBridge → Pancake → FulfillmentCore`; Pancake **không reference Bridge** (grep-verified trong evidence) — boundary wiring↔vendor giữ nguyên từ DEC-001, chỉ bỏ lớp module thứ ba.
3. `Secomm_PancakeFunction` giữ lại làm **disabled deprecated stub** (0.1.1) tránh fatal cho env từng enable; không chứa business code.
4. **BC toàn bộ** như DEC-001: config path `pancake/*`, `service_code=pancake`, CLI `secomm:pancake:poll`, webhook route, admin menu/ACL, DB mapping rows.
5. Adapter POS thứ hai theo template 2 lớp này (vendor module + bridge module trên Core).

## Alternatives

- **3 lớp Core + Bridge + Function** (DEC-TASKZR2ZNS-001): reject — overhead housekeeping 3 module cho một consumer; Function vẫn phải phụ thuộc Magento interface nên "độc lập vendor" không trọn vẹn.
- **Monolith 1 module** (epic SLP-30 gốc): reject — trộn wiring với vendor logic, đúng lý do đã tách.
- **Xóa hẳn `Secomm_PancakeFunction` không để stub**: reject tạm — env đã enable Function trên local/sandbox sẽ fatal nếu module biến mất; xóa file thật có thể làm ở cleanup sau khi chắc chắn không env nào còn (đề xuất TL).

## Consequences

- **Tích cực**: 2 bộ CHANGELOG/README thay 3; boundary chính (wiring↔vendor) vẫn rõ; Function stub vô hại.
- **Tiêu cực**: tên `Secomm_Pancake`曾 bị deprecate rồi revive trong 2 ngày — git history có đoạn zic-zac, cần note trong README để dev khác không nhầm (README Pancake đã viết lại theo vai trò vendor). Version Pancake nhảy 0.1.0 (FNC history) → 0.4.0 để tiếp nối dòng version cũ của monolith.
- **Follow-up**: (1) TL approve DEC này + revoke formal DEC-001 (đã đánh rejected); (2) cân nhắc xóa hẳn stub Function ở cleanup sau; (3) template adapter thứ 2 tham chiếu DEC này.
