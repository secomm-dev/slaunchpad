---
id: DEC-TASKZR2ZNS-001
title: POS adapter convention — Core + Bridge (Magento wiring) + Function (vendor logic)
status: rejected             # chưa từng được approve; bị MIG-002 (2026-09-11) thay bằng convention 2 lớp — xem DEC-TASKZR2ZNS-002
owners: [tl, sa]
decision_type: architecture
approval_date:
created: 2026-09-10
last_verified: 2026-09-10
verified_against_commit:
supersedes: []
superseded_by: DEC-TASKZR2ZNS-002   # 2026-09-11: Function collapse vào Pancake (MIG-002)
work_items: [TASK-ZR2ZNS]    # epic mirror SLP-30; thay thế constraint "một module Secomm_Pancake" của epic ban đầu
---

# Decision Record: POS adapter convention — Core + Bridge (Magento wiring) + Function (vendor logic)

<!-- AI draft 2026-09-10 từ plan .cursor/tasks/SLP-30/plans/2026-09-10-bridge-function-convention.md ("Quyết định đã chốt") — chờ TL approve. -->

## Context

Epic SLP-30 ban đầu chốt **một** module adapter `Secomm_Pancake` (constraint epic: "Adapter: một module Secomm_Pancake — PancakeStatusMapper nằm trong module đó, không tách module mapper"). Sau khi chạy thật (export + inbound + admin status/warehouse map, 2026-09-09/10), module monolith trộn 2 mối quan tâm: vendor logic (HTTP POS, payload, parse, status codes) và Magento wiring (config/admin/cron/CLI/webhook/DI pools). Dự kiến thêm adapter POS thứ hai → cần convention tái dùng được.

## Decision

1. **Convention POS adapter = Core + Bridge + Function**: `Secomm_FulfillmentCore` (platform) + `Secomm_PancakeBridge` (Magento wiring: config, exporter, cron/CLI/webhook, admin maps UI, data patches, DI pool registration) + `Secomm_PancakeFunction` (vendor logic: PosClient, PayloadBuilder, OrderPayloadParser, StatusMapper/Catalog, PosWarehouseCatalog, `PosApiConfigInterface`).
2. **Dependency một chiều**: `Bridge → Function → FulfillmentCore(Api)` và `Bridge → FulfillmentCore`. **Function không depend/reference Bridge** (Function test được độc lập, thay Bridge không đụng vendor logic).
3. **BC toàn bộ**: config path `pancake/*`, `service_code=pancake`, tên CLI `secomm:pancake:poll`, webhook route, menu/ACL admin, DB mapping rows — giữ nguyên qua split; legacy `Secomm_Pancake` thành stub disabled (`0.9.0-deprecated`).
4. Supersede **một phần** epic SLP-30 constraint "một module Secomm_Pancake" (Expected Behavior/Constraints epic đã rewrite 2026-09-10 theo convention này).

## Alternatives

- **Giữ monolith `Secomm_Pancake`** (constraint epic cũ): reject — vendor logic dính Magento wiring, mỗi adapter sau lại lặp pattern trộn; khó test PosClient/payload độc lập.
- **Tách module mapper riêng** (option epic cũ nêu rồi loại): reject — mapper chỉ là 1 class; mức tách đúng là theo mối quan tâm wiring vs vendor logic, không phải theo class.
- **Function là microservice ngoài Magento**: reject — out of scope epic, vận hành phức tạp không tương xứng.

## Consequences

- **Tích cực**: boundary rõ cho adapter POS thứ hai (chỉ cần Bridge+Function mới theo template); Function unit test không cần boot Magento area admin; deploy tách biệt được thay đổi vendor logic (đổi API Pancake) khỏi wiring.
- **Tiêu cực**: 3 module thay 2 (nhiều repo housekeeping: CHANGELOG/README/version từng module); DI chain dài hơn (Bridge phải đăng ký pools thay vì tự implement); rủi ro migrate enabled-module state trên các env đã bật legacy (README stub có hướng dẫn disable/enable + `setup:upgrade`).
- **Follow-up**: (1) TL approve DEC + record TASK-ZR2ZNS; (2) cân nhắc đưa convention vào `project-context/04_CUSTOM_MODULES_AND_CODE_AREAS.md` khi consolidate; (3) adapter POS thứ hai phải theo đúng template này (mở work item mới).
