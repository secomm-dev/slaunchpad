---
id: DEC-TASKAEZTTB-001
title: Pancake inbound = webhook receiver-only (type orders) + poll song song; chỉ apply export Magento-origin
status: proposed             # chờ TL approve (Tier 2: order lifecycle + external API inbound)
owners: [tl, sa]
decision_type: architecture
approval_date:
created: 2026-09-11
last_verified: 2026-09-11
verified_against_commit:    # working tree dev/development/baole, chưa commit
supersedes: []
superseded_by:
work_items: [TASK-AEZTTB]
---

# Decision Record: Pancake inbound = webhook receiver-only (type orders) + poll song song

<!-- AI draft 2026-09-11 từ plan .cursor/tasks/SLP-30/plans/2026-09-11-pancake-inbound-webhook.md ("Phản hồi review (đã chốt)" — poll giữ nguyên, chỉ thêm receiver). Chờ TL approve. -->

## Context

Pancake POS Open API có **Webhook configuration** (`PUT /shops/{SHOP_ID}` — `webhook_enable`, `webhook_url`, `webhook_types`); khi type gồm `orders`, POS POST body `WebhookOrderResponse` (có `id`, `custom_id`, `status`, `tracking_link`, `partner.*`). Trước đó inbound chỉ có poll cron 15 phút + CLI `secomm:pancake:poll` (đã ổn định, TASK-ZR2ZNS smoke ok). `05_API_CONTRACTS.md` cũ ghi "[ASSUMPTION] POS OpenAPI does not document a Magento webhook" — đã bỏ sau khi đối chiếu OpenAPI `.ai/research/api-1.json`.

## Decision

1. **Magento = receiver only**: ops cấu hình webhook trên POS UI (Setting → Advance → Third-party connection → Webhook/API) hoặc OpenAPI `PUT /shops/{SHOP_ID}`. Magento **không** tự gọi `PUT /shops` để đăng ký webhook trong slice này.
2. **Webhook type `orders`**, endpoint `POST /pancake/webhook/index?secret=<pancake/webhook/secret>` (`Secomm_PancakeBridge`), auth bằng `hash_equals` query secret.
3. **Poll giữ nguyên** làm đường inbound song song (fallback khi webhook miss) — không disable poll khi bật webhook.
4. Webhook chỉ apply cho row `secomm_fulfillment_export` **origin=magento**; match POS `id` → `external_order_id`, fallback `custom_id` → `magento_increment_id`.
5. `OrderPayloadParser` dùng chung cho poll + webhook; cặp duplicate-inbound do 2 đường chạy được chống bởi event-id dedup sẵn có trong `InboundUpdateApplier`.

## Alternatives

- **Webhook-only (bỏ poll)**: reject — webhook miss (POS retry policy không kiểm soát được) sẽ mất update vĩnh viễn; poll là cơ chế tự phục hồi.
- **Magento tự đăng ký webhook qua `PUT /shops/{SHOP_ID}`**: reject cho slice này — cần API key write-scope + ops kiểm soát; để ops làm qua UI.
- **Poll-only (giữ hiện trạng)**: reject — độ trễ tới 15 phút cho trạng thái khách hỏi đơn.

## Consequences

- **Tích cực**: update trạng thái/carrier/tracking về gần real-time; pipeline Core (comment, status map, timeline) dùng chung nên hành vi nhất quán 2 đường.
- **Tiêu cực/rủi ro**: (1) QC phải cover **cả hai** đường inbound (webhook + poll) — kể cả status map (TASK-BS91A3 AC-001/002); (2) secret webhook để trống = tắt check — phải đặt trên store public (đã ghi system.xml comment + README); (3) response `ok:true, result:ignored` cho các case bỏ qua/đẻ tránh POS retry loop — ops phải đọc log để phân biệt.
- **Follow-up**: TL approve DEC + TASK-AEZTTB; fix 2 blocker pre-review (xem TASK-AEZTTB §Verification); cân nhắc QC e2e PNC-03 mở rộng thêm webhook path.
