---
id: TASK-AEZTTB
type: task
title: Harden Pancake inbound webhook receiver and keep poll fallback
project_code: SLP
parent:                       # null — epic SLP-30 chưa có canonical FEAT record (không tự tạo Feature chỉ để có parent, §3)
mode: A
specification_level: MINI
spec_status: DRAFT            # plan Cursor có "Phản hồi review (đã chốt)" nhưng chưa qua TL approve canonical; chờ TL để VALID
specification_ref: ".cursor/tasks/SLP-30/plans/2026-09-11-pancake-inbound-webhook.md + Embedded Mini-Spec (record này)"
risk: high                    # inbound ngoài đổi order status/comment (order lifecycle + PII + external API)
status: in_progress           # code xong (uncommitted); pre-review tìm ra 2 BLOCKER chưa fix — không request TL review cho tới khi fix
created: 2026-09-11
updated: 2026-09-11
external_refs:
  cursor: SLP-30              # epic mirror .cursor/tasks/SLP-30/; batch này có plan riêng, không có ticket mirror con
legacy_ids: []
ticket_ref:
decisions: [DEC-TASKAEZTTB-001]  # webhook receiver-only + poll song song — proposed, chờ TL
decision_assessment: material
components:
  - Secomm_Pancake            # 0.4.1 — parser ServiceCode + WebhookOrderResponse carrier/tracking
  - Secomm_PancakeBridge      # 0.2.1 — webhook harden + README ops guide
source_areas:
  - app/code/Secomm/PancakeBridge/Controller/Webhook/Index.php          # unwrap + resolve export + applyToExport + gated log
  - app/code/Secomm/Pancake/Model/Inbound/OrderPayloadParser.php        # ưu tiên POS numeric id; carrier partner.*; legacy flat fallback
  - app/code/Secomm/Pancake/Model/Order/PayloadBuilder.php              # [BLOCKER F1] import class không tồn tại
  - app/code/Secomm/Pancake/Test/Unit/                                  # +2 test parser WebhookOrderResponse shape
  - app/code/Secomm/PancakeBridge/README.md                             # runbook cấu hình webhook POS → Magento
  - app/code/Secomm/PancakeBridge/etc/adminhtml/system.xml              # comment secret + webhook type orders
  - app/code/Secomm/PancakeBridge/i18n/{en_US,vi_VN}.csv                # đồng bộ comment mới (BR-001)
  - .ai/project-context/05_API_CONTRACTS.md                             # bỏ [ASSUMPTION] "no webhook", thêm contract PUT /shops
changes_project_state: true    # thêm đường inbound áp update lên order
changes_architecture: false    # đúng kiến trúc 2 lớp hiện hành (DEC-TASKZR2ZNS-002), không đổi boundary
changes_integration: true      # contract inbound Pancake: thêm webhook orders (05_API_CONTRACTS)
changes_known_limitations: false
verified_against_commit:       # working tree dev/development/baole, chưa commit (HEAD 832fd5c8)
last_verified: 2026-09-11
supersedes: []
---

# [SLP][TASK-AEZTTB] Harden Pancake inbound webhook receiver and keep poll fallback

<!-- Record do AI draft sau khi dev session 2026-09-11 thực hiện (retroactive — spec-first debt: code trước spec canonical VALID; flag cho TL). -->
<!-- Raw evidence → .ai/evidence/SLP-30/README.md §"Inbound webhook harden (2026-09-11)". -->

## Summary

Bổ sung đường inbound **webhook** cho Pancake POS (type `orders`, `WebhookOrderResponse`) song song với poll hiện có: Bridge `Controller/Webhook/Index` unwrap body → `OrderPayloadParser` → resolve export Magento-origin (POS `id` → fallback `custom_id`/increment) → `InboundUpdateApplier::applyToExport`. Poll cron/CLI giữ nguyên làm fallback. Kèm runbook ops cấu hình webhook trong README Bridge + cập nhật `05_API_CONTRACTS.md` (bỏ assumption "POS không có webhook").

## Mini Spec

### Goal

Trạng thái / carrier / mã vận đơn Pancake đổi → Magento nhận POST webhook gần real-time và áp cùng pipeline Core như poll; không đợi chu kỳ poll 15 phút.

### Expected Behavior

- Webhook nhận 3 shape body: `WebhookOrderResponse` trực tiếp, bọc trong `data`, hoặc `order`.
- Secret: `hash_equals` query `secret` với config `pancake/webhook/secret`; secret rỗng = tắt check (không dùng trên store public).
- Chỉ apply cho export `origin=magento`; match POS `id` → `external_order_id`, fallback `custom_id` → Magento increment id.
- Parser ưu tiên POS numeric `id`; carrier lấy từ `partner.partner_name` → `partner.delivery_name` → legacy flat `partner_name`/`delivery_name`; tracking từ `partner.extend_update[].tracking_id` + `tracking_link`.
- Response: `applied` | `ignored` | `invalid_secret` | `invalid_json` | `internal_error`.
- Poll cron/CLI behavior không đổi.

### Constraints / Rules

- Boundary 2 lớp giữ nguyên (DEC-TASKZR2ZNS-002): Pancake không reference Bridge.
- Duplicate inbound webhook+poll chống bởi event-id dedup trong applier.
- Log gated, không chứa `api_key`/PII thô (AC-005 TASK-BS91A3).
- Mode A + Tier 2 (order lifecycle + PII + external API).

### Out of Scope

- Magento tự đăng ký webhook qua `PUT /shops/{SHOP_ID}` (receiver-only, DEC-TASKAEZTTB-001).
- Bỏ/đổi lịch poll.
- Webhook cho entity khác ngoài `orders`.

### Acceptance Criteria

- AC-001: Given webhook POST body `WebhookOrderResponse` (mọi shape), Then parser trả InboundUpdate đủ trường (status, carrier, tracking, event id) và applier apply lên đúng export
- AC-002: Given sai secret / body không phải JSON, Then response `invalid_secret` / `invalid_json`, không crash, có log gated
- AC-003: Given webhook cho đơn không có export Magento-origin (POS-only order), Then `ignored`, không tạo dữ liệu
- AC-004: Poll cron + CLI giữ nguyên behavior (diff không đụng poll path ngoài import `ServiceCode`)
- AC-005: Log/evidence không chứa `api_key`/PII thô
- AC-006: Runbook README Bridge (cấu hình POS UI + URL + type orders) + system.xml comment + i18n vi/en nhất quán

## Approach

Plan: [2026-09-11-pancake-inbound-webhook.md](../../../.cursor/tasks/SLP-30/plans/2026-09-11-pancake-inbound-webhook.md) — "chỉ viết thêm receiver", poll không đụng.

## Implementation Notes

- Bridge `Webhook/Index`: thêm `unwrapOrder()` (data/order), `resolveExport()` + `findExportByExternalId/IncrementId` (collection filter service_code + origin), gọi `applyToExport()` thay `apply()`; log info các nhánh ignored/processed; đổi `PancakeOrderExporter::SERVICE_CODE` → `ServiceCode::CODE`.
- Pancake `OrderPayloadParser`: refactor thứ tự lấy id (`id`/`order_id` trước, `custom_id` sau); carrier ưu tiên `partner.*`; +2 unit test (WebhookOrderResponse shape, partner_name>delivery_name). CHANGELOG 0.4.1 + composer 0.4.1.
- Bridge: CHANGELOG 0.2.1 + composer 0.2.1; README thêm bảng "Inbound paths (keep both)" + hướng dẫn cấu hình Pancake POS Webhook/API; system.xml comment rút gọn; i18n vi/en đồng bộ.
- `05_API_CONTRACTS.md`: thay [ASSUMPTION] bằng contract webhook thực (PUT /shops/{SHOP_ID}, type orders).
- **Bám kèm working tree (không thuộc item này)**: (1) `app/design/.../tailwind-source.css` +1 dòng `@source` FulfillmentCore phtml — **sai path** (xem F4); (2) 31 file chỉ đổi line-ending CRLF→LF (F5); (3) Ahamove `db_schema.xml` bỏ UNIQUE (Tier 2 DB schema, scope khác — đã flag ở TASK-ZR2ZNS).

## Verification

Pre-review (AI, 2026-09-11) — **2 BLOCKER, chưa request TL review**:

- **F1 [BLOCK]** `PayloadBuilder` + `PayloadBuilderTest` import/mocked class `Secomm\Pancake\Model\Config\PancakeConfig` **không tồn tại** (config implementation thật là `Secomm\PancakeBridge\Model\Config\PancakeConfig` — nhưng Pancake không được depend Bridge). Evidence: `vendor/bin/phpunit --no-coverage app/code/Secomm/Pancake/Test/Unit` → 7 tests, 1 error (`UnknownTypeException: Class "Secomm\Pancake\Model\Config\PancakeConfig" does not exist`). Runtime: DI fatal khi instantiate PayloadBuilder → **export đơn sang POS gãy**. Fix đề xuất: revert 2 file về `Secomm\Pancake\Api\PosApiConfigInterface` (boundary giữ nguyên, mock interface như trước).
- **F2 [BLOCK]** `findExportByIncrementId()` filter field `increment_id` nhưng cột DB là `magento_increment_id` (`FulfillmentCore/etc/db_schema.xml`) → SQL unknown column trên nhánh fallback `custom_id` → webhook trả `internal_error`. Fix: filter `magento_increment_id`.
- **F3 [WARN]** `$result === 'error'` không bao giờ true (applier trả `'error: <msg>'`) → response báo `applied` dù applier lỗi. Fix: `str_starts_with($result, 'error')`.
- **F4 [WARN]** `@source "../../../../../../../code/Secomm/FulfillmentCore/view/frontend/**/*.phtml"` lệch 1 cấp (7×`../` = repo root; cần 6× = `app/code`) — realpath không resolve; template timeline của Core có thể bị purge styles.
- **F5 [WARN]** 31 file (FulfillmentCore + PosClient + PancakeStatusMapper) chỉ đổi CRLF→LF, ~2.9k dòng nhiễu trong diff — nên tách commit normalization riêng hoặc revert.
- **F6 [nit]** CHANGELOG Pancake 0.4.1 ghi "PancakeStatusMapper uses ServiceCode::CODE" nhưng thay đổi đó đã nằm trong HEAD 832fd5c8.

Kết quả theo AC:

- [ ] AC-001 — bị chặn F1/F2; sau fix cần QC e2e: POST webhook 3 shape lên đơn đã export
- [ ] AC-002 — code review ok (`hash_equals`, catch `\Throwable`), chưa QC live
- [ ] AC-003 — logic resolve + filter origin đúng (F2 fix xong mới chạy được nhánh fallback); chưa QC
- [x] AC-004 — diff review: poll path không đổi (chỉ import constant); cron/CLI untouched
- [x] AC-005 — code review: log chỉ chứa `export_id`/`result`/`external_order_id` (không PII/api_key); lưu ý F3 log result kèm exception message — kiểm lại sau fix
- [x] AC-006 — README/system.xml/i18n vi+en đã delivered (kiểm diff)

Unit tests (2026-09-11): 7 tests / 18 assertions, **1 error** — `PayloadBuilderTest` (F1). 6/7 pass gồm 2 test parser mới.

## Related records

- DEC-TASKAEZTTB-001 (webhook receiver-only + poll song song) — proposed
- TASK-ZR2ZNS (kiến trúc 2 lớp mà batch này bám theo; DEC-TASKZR2ZNS-002)
- TASK-BS91A3 (webhook đi qua cùng status map applier — AC-001/002 của ticket đó cần QC thêm nhánh webhook)
- Evidence: `.ai/evidence/SLP-30/README.md` §"Inbound webhook harden (2026-09-11)"
- Plan mirror: `.cursor/tasks/SLP-30/plans/2026-09-11-pancake-inbound-webhook.md`
