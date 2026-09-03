---
id: DEC-FEATE2HM1J-001
legacy_ids: [DEC-025]
title: Admin address dropdown architecture — global data-driven multi-level mechanism (AddressDropdown) + country adapter (VietNamAddress); VN 2-level (ward=native city); sub_city is a generic 3rd level (not deprecated)
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-03
created: 2026-08-03
last_verified: 2026-08-03
verified_against_commit:
supersedes: []
superseded_by: DEC-FEAT2PZQKJ-001   # 2026-08-25: point 3 (sub_city fixed 3rd level) thay bằng recursive hierarchy; points 1-2 restated
work_items: [FEAT-E2HM1J, FEAT-JSZQV3, FEAT-YVN39K, TASK-4ZV5NG, TASK-SQY42T, TASK-8WSERX, TASK-2V0AEV]
---

# Decision Record: Admin address dropdown — global mechanism + country adapter; data-driven levels

<!-- CANONICAL DECISION STORE (Phase 1a / RM-01). Accepted via user acting as SA/TL (chat 2026-08-03). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Concretizes DEC-17/DEC-19/DEC-20 for ADMIN address surfaces; pins FEAT-E2HM1J architecture. -->

## Context

Admin address forms (customer address, sales order address create/edit, Store Information, Shipping Origin, MSI Source) chưa có VN ward dropdown — `city` vẫn là text input. Cần chốt architecture để apply一致的 trên mọi admin surface, đối xứng frontend cart (DEC-FEATJSZQV3-001/019, precedent TASK-FD6A9X), tuân 2-level VN (DEC-TASKYJENM2-001).

Ba điểm phải chốt:
1. **Ai owns gì** — generic mechanism vs country logic (DEC-8/019 boundary cho admin).
2. **Bao nhiêu level render** — VN 2 cấp, nhưng country khác có thể 3 cấp (`sub_city`). Cascade phải **data-driven theo country** ("data được lưu mấy tầng sẽ là mấy tầng").
3. **`sub_city`** — có deprecate toàn project không, hay là generic 3rd level dùng cho country khác.

Tier-2 (address + customer/PII + order — §9) → Level-2 architecture decision, SA/TL.

## Decision

1. **`Secomm_AddressDropdown` = generic, country-agnostic, data-driven multi-level mechanism.** Cung cấp cascade `region → city → sub_city` generic + data (collections `CityLocaleCollection`/`CityCollection`, GraphQL `GetListCity`, option/source models) + helper inject dropdown + persist vào **store riêng của form**. **Render đúng số level mà data country đó có** ("mấy tầng sẽ là mấy tầng"). **KHÔNG** chứa `country == 'VN'` hay label/behaviour country-specific (DEC-FEATJSZQV3-003).

2. **`Secomm_VietNamAddress` = country adapter.** Khi `country == 'VN'`: `city` = **ward** (data `directory_region_city`, **2-level** — DEC-TASKYJENM2-001); ward **persist vào native `city`**; **server-side validate** ward ∈ province (mirror `Plugin/Cart/ValidateVietNamWard`). Non-VN → generic mechanism (native).

3. **`sub_city` là generic 3rd level — KHÔNG deprecate.** VN = 2 cấp → adapter **không render `sub_city` cho VN**. Country khác có data 3 cấp → `sub_city` hiện (generic, data-driven). Không xóa field/infra `sub_city`.

4. **Form thiếu `city`** → generic mechanism **thêm field `city`** (select) trước khi country adapter gắn data.

5. **Persist đúng form store** — mỗi surface lưu vào store native của nó (`customer_address_entity` / `sales_order_address`(+quote) / `core_config_data` / `inventory_source`). Không cross-wire.

Approved via user acting as SA/TL authority (chat 2026-08-03). Formal SA/TL name [TBD]. Sub-ticket plans vẫn cần TL approval (Mode A).

## Alternatives

- **VN logic trong generic `Secomm_AddressDropdown`:** rejected — vi phạm DEC-FEATJSZQV3-003 (leak country-specific vào module reusable); đã là vấn đề cleanup (TASK-KCBDDT).
- **Fixed 2-level cascade cho mọi country:** rejected — country khác cần 3 cấp (`sub_city`); cascade phải data-driven theo data country.
- **Deprecate `sub_city` toàn project:** rejected — `sub_city` là 3rd level generic hợp lệ cho country non-VN có data 3 cấp; deprecate sẽ mất capability đó.
- **Persist ward vào field riêng (ward_id/column mới):** rejected (scope này) — ward persist vào native `city` (giống cart TASK-FD6A9X), tránh schema migration. (`ward_id` canonical = path A dài hạn, DEC-TASKYJENM2-001 — ngoài scope.)

## Consequences

- (+) Architecture nhất quán frontend ↔ admin; generic reusable, country-agnostic; VN cô lập trong `VietNamAddress`; multi-level data-driven.
- (+) `sub_city` được bảo tồn generic (không mất data cho country non-VN 3-level).
- (+) Mỗi form persist đúng store native → không xung đột data.
- (−) Admin form mechanics khác nhau per surface (ui_component / block-form `Magento_Sales` / system.xml config / MSI ui_component) → approach injection khác, cần mini-spec mỗi surface (TASK-4ZV5NG…TASK-2V0AEV).
- (−) **TASK-4ZV5NG phải migrate** cascade admin customer-address hiện đang nằm trong `Secomm_AddressDropdown` (`customer_address_form.xml` + `provider-mixin.js` — vi phạm DEC-FEATJSZQV3-003) sang `VietNamAddress`; `sub_city` field giữ generic.
- Follow-up: review `sub_city` chỉ khi một country non-VN cần 3-level (hiện chưa có); VN stays 2-level.

## Affected components

`CMP-ADDR` (Secomm_AddressDropdown — generic mechanism + data), `CMP-VNADDR` (Secomm_VietNamAddress — VN adapter mở rộng sang admin). Source: `app/code/Secomm/AddressDropdown/`, `app/code/Secomm/VietNamAddress/`.

## Related records

- [FEAT-E2HM1J](../features/FEAT-E2HM1J.md) — admin VN 2-level address dropdown (sub-tickets TASK-4ZV5NG…TASK-2V0AEV)
- [FEAT-JSZQV3](../features/FEAT-JSZQV3.md) — cart precedent (TASK-FD6A9X); [FEAT-YVN39K](../features/FEAT-YVN39K.md) — CMP-ADDR storefront
- [TASK-KCBDDT](../../tickets/TASK-KCBDDT-generalize-addressdropdown-remove-vn-leak.md) — generic/VN boundary cleanup (storefront)
- [DEC-FEATJSZQV3-001](DEC-FEATJSZQV3-001.md) — generic VN capability → VietNamAddress
- [DEC-FEATJSZQV3-003](DEC-FEATJSZQV3-003.md) — generic Magento-default labels; VN → VietNamAddress
- [DEC-TASKYJENM2-001](DEC-TASKYJENM2-001.md) — 2-level; ward = city level; sub_city deprecated **cho VN** (DEC-FEATE2HM1J-001 clarify: generic 3rd level cho non-VN)
- DEC-8 (generic vs project boundary)
- DECISIONS.md index: DEC-FEATE2HM1J-001
