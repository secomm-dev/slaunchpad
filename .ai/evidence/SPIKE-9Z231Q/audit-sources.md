# Evidence — SPIKE-9Z231Q (Secomm_Ghn canonical architecture — audit + design, 0 production code)

Ngày: 2026-09-08 · Mode A · pre-review evidence cho TL/SA review.
**v2 (clarification TL/SA cùng ngày)**: PRE_2025 confirmed là intermediate scheme carrier-required;
thêm reverse-mapping audit (section dưới).
**v3 (TL/SA verified từ GHN data/runtime)**: GHN cần **exact old ward** — same-district
optimization + district-granular REJECTED; recommendation `A — KEEP FULL PRE_2025 RESOLUTION`;
KPI = `FULL_PRE_2025_ADDRESS_RESOLVED`; thêm NO_MATCH classification (section cuối).

## Reverse mapping audit — VN_ADMIN_2025 → VN_ADMIN_PRE_2025 (v2)

- Script: `audit_reverse_mapping.php` (read-only PHP CLI, parse 3 CSV dataset trong
  `app/code/Secomm/VietNamAddress/Files/`) · Raw: `reverse-mapping-stats.json`
- Input: 3.321 current wards · 11.294 legacy units · 10.064 edges (63 region-level + 10.001 ward-level)
- Kết quả classification chiều ngược:

```
ONE_TO_ONE  (deterministic)      :   186 (5.60%)
ONE_TO_MANY (AMBIGUOUS reverse)  : 3.097 (93.26%)
  - same legacy district only    : 2.832 (85.27%) — district deterministic
  - cross legacy district        :   265 (7.98%) — district info lost
NO_MATCH    (fail closed)        :    38 (1.14%) — former-district elevations (Phú Quốc, Kiên Hải, Hoàng Sa…)
INVALID_MAPPING edges            :     0 (63 region edges classified riêng, hợp lệ)

district-granular deterministic  : 3.018/3.321 (90.88%)
cross-province ward merges       : 0
region-level edges reverse-ambiguous (>1 incoming): 23/34
relation types: MERGED_INTO 9250 · SPLIT_INTO 627 · SAME_AS 146 · RENAMED_TO 41
```

- Đọc kiến trúc: N→1 (`MERGED_INTO`) đảo chiều thành 1→N ambiguity — KHÔNG đảo trực tiếp;
  fail-closed thuần ⇒ GHN quote available 5.60% ward-level → E-B v2 (external resolver pool
  khi AMBIGUOUS) + empirical validation (district-granular) là 2 đường thoát duy nhất không
  phá determinism.

## Nguồn GHN API (docs chính thức, captured 2026-09-08)

| Operation | URL | Fact then chốt đã verify |
|---|---|---|
| Calculate Fee | developer.ghn.vn/en/docs/order/calculate-fee | POST `/v2/shipping-order/fee`; Token+ShopId headers; bắt buộc `weight`,`to_district_id`,`to_ward_code`,`service_type_id`; `from_district_id/from_ward_code` optional (default shop) |
| Create Order | developer.ghn.vn/en/docs/order/create.md | POST `/v2/shipping-order/create`; NAME-based (`to_ward_name/to_district_name/to_province_name`) + `is_new_to_address` (docs ghi nhận cải cách 01/07/2025); KHÔNG có ID fields; `client_order_code` idempotency |
| Get Service | developer.ghn.vn/en/docs/master-data/get-service.md | POST `/v2/shipping-order/available-services`; `shop_id`+`from_district`+`to_district` bắt buộc; `service_id` "reference only"; empty-200-body = shop not found; error `WARD_IS_INVALID` |
| Master data legacy | …/get-province.md · get-district.md · get-ward.md | `/shiip/public-api/master-data/*`; WardCode String leading-zero; SupportType 0-3; docs mark "Legacy address model" |
| Master data mới | …/get-province-new.md · get-ward-new.md | `/v3/master-data/province/all` + `/v3/master-data/ward/all-by-province-id`; `_id`, `name` verbatim, `extension_names[]`, 34 tỉnh |
| Cancel | developer.ghn.vn/en/docs/order/cancel.md | POST `/v2/switch-status/cancel`; `order_codes[]` all-or-nothing; reason_code enum |
| Update | developer.ghn.vn/en/docs/order/update.md | POST `/v2/shipping-order/update`; partial; **cod_amount cần OTP**; ma trận field-per-status |
| Order Info | developer.ghn.vn/en/docs/order/info.md | GET `/v2/shipping-order/detail`; ~120 fields; có biến thể by-client-code |
| Webhook | …/webhook/callback-order-status.md | POST GHN→server; PascalCase; dedup `OrderCode+Type+Time`; FIFO per order; 2xx done / 4xx trừ 408,429 drop / 5xx+timeout retry 11 lần; portal tự đăng ký + custom headers; staging≠production account |
| Status codes | …/master-data/order-status.md | 23 status, final: delivered/returned/cancel/exception/lost/damage/scrap |

## Legacy audit — method

Explore agent đọc trực tiếp 2 module (28 câu hỏi audit, mọi claim có file:line trong report §1/§1.4).
Các fact chính đã đối chiếu với code thật (không dựa README):

- `AbstractDataBuilder::resolveGhnLocation()` — `AbstractDataBuilder.php:156-201` (đã fail-closed BUG-JBX3H9)
- First-match mapping — `GhnAddressMapper/Model/ResourceModel/LocationMapping.php:23-35` (`priority DESC, entity_id DESC LIMIT 1`)
- Name-based service selection — `GiaoHangNhanh/Model/Carrier/{Standard,Express}.php` SERVICE_NAME_SHORT + `GHN.php:239`
- GhnStatusMapper đã implements `CarrierStatusMapperInterface` — `GiaoHangNhanh/Model/Tracking/GhnStatusMapper.php:10,16` (inject concrete `di.xml:313-317`)
- Webhook không auth + mutate order state — `Controller/Webhook/ShippingUpdate.php:192-195, 344-361`
- Circular dependency — `GhnAddressMapper/etc/module.xml:11` + `GiaoHangNhanh AbstractDataBuilder` type-hint
- Table schema — `GiaoHangNhanh/etc/db_schema.xml:4-95` (ward_code INT), `GhnAddressMapper/etc/db_schema.xml:4-72` (no unique)

## Deliverables

| File | Nội dung |
|---|---|
| `.ai/records/spikes/SPIKE-9Z231Q.md` | Record Mode A + Mini-Spec + findings v2 |
| `.ai/research/SPIKE-9Z231Q-secomm-ghn-canonical-architecture.md` | Report v2 (14 sections + Confirmed Architecture + §15 audit + §16 rejected + §17 empirical questions) |
| `.ai/evidence/SPIKE-9Z231Q/audit_reverse_mapping.php` | Script audit read-only (evidence scope, không phải production code) |
| `.ai/evidence/SPIKE-9Z231Q/reverse-mapping-stats.json` | Raw stats output |

## Verification AC

- [x] AC-1 — 14 sections đủ; API claims có URL (bảng trên); legacy claims có file:line.
- [x] AC-2 — §6: capability ĐỦ + 2 điểm TL/SA chốt + blocker E-B tường minh.
- [x] AC-3 — §7: keyed scheme_code+unit_code(+representation); resolution order direct→translate→fail-closed; 0 banned pattern trong thiết kế (name-match chỉ import CLI).
- [x] AC-4 — §9: create dựa Create Order docs (NAME + flag); gap Update-docs ghi nhận KHÔNG infer (§14 context + §9 note).
- [x] AC-5 — §12: 9 bề mặt coexistence (carrier codes/config/DI/events/topics/webhook URL/DB/frontend/circular) + §7.3 migration qua operational resolver + audit report.
- [x] AC-6 — record + report + evidence; validator chạy; CURRENT_STATE cập nhật; completion report 11 điểm (chat).

## Phạm vi

0 production code. Không sửa file nào trong app/code cho task này. Legacy modules untouched.
