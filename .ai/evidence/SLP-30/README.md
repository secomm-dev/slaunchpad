# SLP-30 evidence

Path: `.ai/evidence/SLP-30/` (PNC-003).

## Environment (2026-08-19)

- Magento 2.4.8-p5 local (`detect_env`: local).
- `Secomm_FulfillmentCore` enabled via `bin/magento module:status`.
- `setup:upgrade` **not** applied here: `configuration for DB connection is absent`.
- Pancake sandbox `shop_id` / `api_key` **not** provided — no live POS calls, no production PII.

## Automated checks

| Check | Result |
|-------|--------|
| Unit tests `Secomm_FulfillmentCore` + `Secomm_Pancake` | Run locally: `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --no-extensions app/code/Secomm/FulfillmentCore/Test/Unit app/code/Secomm/Pancake/Test/Unit` |
| `module.xml` sequence ShippingCore/Ghtk/Ahamove | **Pass** — only Magento_* + `Secomm_FulfillmentCore` on Pancake |
| No `Secomm_PancakeStatusMapper` Magento module | **Pass** — mapper class under `Secomm/Pancake/Model/Mapping/` |
| Logs / client | `PosClient` logs `method`, `path`, `http_status` only — no `api_key`, no phone/email |

## Manual DoD (blocked until sandbox)

| ID | Step | Expected | Status |
|----|------|----------|--------|
| DoD-01 / AC-1 | Place Magento order with Pancake enabled | POS create-order with `custom_id`=increment_id, items, address | **Blocked** — no `api_key` |
| DoD-02 / AC-2 | Change POS status=2 + tracking | Magento comment + timeline SHIPPED active | **Blocked** — no DB/POS |
| DoD-PNC-03 / AC-3 | Inbound payload for unknown POS id | Applier `ignored_no_mapping`; no new `sales_order` | Covered by unit test `testApplyNoopsWithoutMagentoOriginMapping` |
| AC-4 storefront | Order view with state SHIPPED | Timeline points through SHIPPED | Covered by `CustomerFulfillmentTimelineTest` + template; visual QA after `setup:upgrade` + Tailwind build |
| AC-5 | `module.xml` grep | No shipping sequence | Pass (code review) |

## Redacted sample (do not store live keys)

POS create-order request shape:

```json
{
  "shop_id": "<shop_id>",
  "custom_id": "100000123",
  "bill_full_name": "[redacted]",
  "bill_phone_number": "[redacted]",
  "items": [{ "one_time_product": true, "quantity": 1, "variation_info": { "name": "Tee", "retail_price": 150000 } }]
}
```

Webhook: `POST /pancake/webhook/index?secret=<webhook_secret>`

## Gaps

1. ~~Apply `setup:upgrade`~~ — done (local): `secomm_fulfillment_export`, `secomm_fulfillment_state` (+ `current_status` column, `secomm_fulfillment_status_map` 2026-09-10) exist.
2. ~~`module:enable` + config~~ — done (local default scope): `enabled=1`, `poll/enabled=1`, `enable_log=1`, shop_id + api_key (encrypted, không ghi ở đây).
3. `npm run build` in `app/design/frontend/Secomm/launchpad/web/tailwind`.
4. L3 e2e on sandbox POS; attach redacted screenshots here when available.
5. **OS crontab chưa có dòng chạy `bin/magento cron:run --group=secomm_pos`** — mọi job group `secomm_pos` (poll + retry) chỉ chạy khi gọi tay. Xem LL-0013.

## Local e2e với POS sandbox thật (2026-09-09 → 2026-09-10)

Environment: WSL2 local, Magento 2.4.8-p5 developer mode, DB local `slp`; POS sandbox thật (shop_id/api_key trong config default scope — không store ở đây).

| ID | Step | Expected | Result |
|----|------|----------|--------|
| DoD-01 / AC-1 | Place 4 đơn (admin + storefront) với Pancake enabled + warehouse map | POS create-order thành công, mapping row `origin=magento`, `push_status=success` | **Pass 2026-09-09** — 4 row `secomm_fulfillment_export` (increment `000000046-3..6`), `last_pushed_at` 2026-09-09 10:02–10:18 UTC. Fix kèm theo: payload bỏ `country_code`/`post_code` (POS 422 `[country_code]: is invalid`) — Pancake CHANGELOG 0.2.2 |
| DoD-02 / AC-2 | Đổi status trên POS (1→8→9→2) | Magento comment + fulfillment state cập nhật | **Pass 2026-09-09/10** — comments storefront locale ("Pancake: Trạng thái giao hàng hiện tại: …") qua webhook + poll; `secomm_fulfillment_state` rows normalized `waiting_pickup`/`shipped`; `secomm_fulfillment_export.current_status` nhận raw code từ GET order (CHANGELOG 0.3.4) |
| DoD-02b (poll cron) | Chạy poll bằng tay | GET order 200 × 4, applied updates | **Pass 2026-09-10 03:15 UTC** — `bin/magento cron:run --group=secomm_pos`, log `var/log/fulfillment/pancake/fulfillment.log`: `{"scanned":4,"applied":4,"failed":0,"skipped_config":0}`; 2 order skip state update do `same event_id` (idempotent — đúng thiết kế) |
| DoD-PNC-03 / AC-3 | Đơn manual POS | Không tạo Magento order | Pass (origin filter — không có row manual nào xuất hiện; unit test `testApplyNoopsWithoutMagentoOriginMapping`) |
| AC-4 storefront | Timeline render | Mốc active theo state | Chưa QC visual trên storefront (cần Tailwind build + fetch live) |
| AC-5 module.xml | Không sequence shipping | Pass (code review, unchanged) |

### Vấn đề đã bắt & khắc phục trong e2e (2026-09-10)

1. **Cron không tự chạy**: không có OS crontab cho `cron:run`; job 03:00 UTC chết vì DI stale "Too few arguments … 6 passed … exactly 8 expected" sau khi đổi constructor `PollUpdatedOrders` — Type Error (`\Error`) làm row `cron_schedule` kẹt `running`. Fix: `rm -rf generated/code/* generated/metadata/*` + `cache:flush` → job 03:15 chạy OK. Chi tiết: LL-0013, LL-0014.
2. Evidence log `var/log/fulfillment/pancake/fulfillment.log` chỉ ghi `method/path/http_status` — không có `api_key`, không PII (AC-005 TASK-BS91A3).

### Còn chờ (blocking cho PNC-003 signoff)

- TL review working tree (Tier 2: order state transitions qua status map TASK-BS91A3, DB schema, external API) — record `.ai/records/tasks/TASK-BS91A3.md`.
- Admin UI Status Mapping + Warehouse Mapping: QC flow trên backend (Save/Delete/duplicate reject).
- Thêm OS crontab `--group=secomm_pos` rồi verify poll tự chạy 2 chu kỳ liên tiếp.

## Inbound webhook harden (2026-09-11)

Plan: `.cursor/tasks/SLP-30/plans/2026-09-11-pancake-inbound-webhook.md` — **poll giữ nguyên**.

| Check | Result |
|-------|--------|
| Bridge `POST /pancake/webhook/index` unwrap + export resolve + `applyToExport` | Code delivered |
| Parser `ServiceCode` + WebhookOrderResponse carrier/tracking | Code delivered |
| Bridge README webhook ops guide | Done |
| `05_API_CONTRACTS` webhook section | Updated (POS OpenAPI PUT shops) |
| Poll cron/CLI | Unchanged |
| Unit tests Pancake (pre-review AI 2026-09-11) | **7 tests, 1 ERROR** — `PayloadBuilderTest` `UnknownTypeException: Secomm\Pancake\Model\Config\PancakeConfig does not exist` (class chưa tồn tại; 6/7 pass gồm 2 test parser mới) |
| Pre-review findings (canonical: `TASK-AEZTTB` §Verification) | **2 BLOCKER**: F1 `PayloadBuilder` inject class không tồn tại (DI gãy export); F2 `findExportByIncrementId` filter `increment_id` thay vì `magento_increment_id` (SQL error nhánh fallback custom_id). Kèm WARN: F3 so sánh `$result === 'error'` không khớp `'error: msg'`; F4 `@source` tailwind lệch 1 cấp (không resolve); F5 31 file CRLF→LF trộn diff |

Ticket process: batch này canonical tại `.ai/records/tasks/TASK-AEZTTB.md` (Mode A, DEC-TASKAEZTTB-001) — **chưa request TL review cho tới khi fix F1/F2**.

## Bridge / Function migration (2026-09-10)

Convention SLP-30 cập nhật: **Core + `Secomm_PancakeBridge` + `Secomm_PancakeFunction`** (legacy `Secomm_Pancake` stub, disabled).

| Check | Result |
|-------|--------|
| `module:status` Function/Bridge enabled, Pancake disabled | **Pass** |
| `setup:upgrade` after split | **Pass** |
| CLI `secomm:pancake:poll --help` (class trên Bridge) | **Pass** |
| Poll smoke `--force --limit=2` (orders `000000046-6/7`) | **Pass 2026-09-10** — GET ok, status map FOUND, `ok=2 fail=0` (skipped_same_event idempotent) |
| Unit `PancakeFunction` StatusMapperTest | **Pass** — 2 tests |
| Function không reference `Secomm\PancakeBridge` | **Pass** (code review / grep) |
| `module.xml` Bridge/Function không sequence ShippingCore/Ghtk/Ahamove | **Pass** |
| Behavior relocate only (export/poll/webhook/admin maps) | Parity expected; re-run full e2e DoD-01/02 after TL review if needed |

Plan: `.cursor/tasks/SLP-30/plans/2026-09-10-bridge-function-convention.md`

## Pancake + Bridge collapse (2026-09-11)

Current convention: **Core + `Secomm_Pancake` + `Secomm_PancakeBridge`**. `Secomm_PancakeFunction` is a disabled deprecated stub.

| Check | Result |
|-------|--------|
| Vendor logic and unit tests moved to `Secomm_Pancake` namespace | Pass |
| Bridge dependency, PHP references, and DI mapper target use `Secomm_Pancake` | Pass |
| `Secomm_Pancake` has no PHP `use Secomm\PancakeBridge\...` | Pass |
| Pancake/Bridge `module.xml` exclude ShippingCore/Ghtk/Ahamove | Pass |
| Function business code removed | Pass |
| Unit tests (`Pancake` + `PancakeBridge`) | Pass — 7 tests, 23 assertions |
| PHP lint across three module trees | Pass |
| Composer validation across three modules | Pass with existing-style warnings for explicit versions and wildcard constraints |
| `setup:upgrade` + cache flush | Pass |
| Module status | Pancake/Bridge/Core enabled; Function disabled |
| Poll smoke `--force --limit=1` | Pass — `ok=1 fail=0`, idempotent `skipped_same_event` |

Current plan: `.cursor/tasks/SLP-30/plans/2026-09-11-pancake-plus-bridge-only.md`
