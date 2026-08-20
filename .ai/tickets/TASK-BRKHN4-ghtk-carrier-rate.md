# TASK-BRKHN4 — GHTK carrier skeleton + rate collection (weight contract, reliability, cache, rate composition, config/security)

**Legacy ID:** SL-009 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (sub-ticket of feature [FEAT-AE761Z](../records/features/FEAT-AE761Z.md))
**Priority:** High
**Estimate:** ~12–18h
**Mode:** A (Tier-2: shipping carrier + external API + secret token + checkout-critical rate path)
**Feature:** [FEAT-AE761Z](../records/features/FEAT-AE761Z.md) (GHTK carrier)
**Placement:** `app/code/Secomm/Ghtk/`
**Risk tier:** Tier 2
**Author:** AI draft · **Date:** 2026-07-30 · **Status:** Dev complete *(legacy reconciliation 2026-08-19, DEC-TASKZ132WA-002: shipped trong FEAT-AE761Z — Ghtk carrier rate live tại `Secomm/Ghtk/Model/{Carrier,Fee}`; status "Ready" stale từ 2026-07-30)*

## Description

Hiện thực Magento shipping carrier `Secomm_Ghtk` (carrier code `ghtk`): đăng ký carrier, admin config (`system.xml`), `collectRates()` → `DestinationAddressResolver` (TASK-YJENM2) + `PickupAddressResolver` → `fee` API → rate. Bao gồm `ShipmentWeightCalculator` (unit config, không assume kg), **rate composition** qua response mapper, **API reliability policy** (timeout/retry/graceful) + short-TTL cache. Depends on TASK-YJENM2 (mapping + destination resolver).

## Acceptance Criteria

### Carrier + config
- [ ] **AC-1 (Carrier registration):** module `Secomm_Ghtk` (`registration.php`/`module.xml`); carrier `ghtk` trong `etc/config.xml`; `collectRates()` implement `\Magento\Shipping\Model\Carrier\CarrierInterface`. Non-VN country → carrier inactive (gate, AC-14).
- [ ] **AC-2 (Admin config `system.xml`):** fields — API Token (`\Magento\Config\Model\Config\Backend\Encrypted`), `X-Client-Source` (**plaintext — KHÔNG encrypt**, Q3 resolved), base URI override (single abstraction, không expose phổ thông nếu không cần), pickup address/`pick_address_id`, `pick_province`/`pick_ward`/`pick_district`, `transport` default, **`weight_unit`** (default đề xuất `kg`), min/default weight, timeout/retry/cache TTL config.
- [ ] **AC-3 (i18n):** carrier/method title vi_VN + en_US (`vi_VN.csv` + `en_US.csv`).

### Pickup resolver (DEC-TASKBRKHN4-001)
- [ ] **AC-4 (PickupAddressResolver):** có `pick_address_id` → ưu tiên gửi, không cần pickup mapping. Không có → bắt buộc resolve `pick_province` + `pick_ward` (`pick_district` optional). Config thiếu/resolve fail → **carrier invalid/inactive theo scope** (không gửi request mơ hồ, không trả rate).
- [ ] **AC-5 (Admin connectivity/health check):** action test kết nối GHTK + pickup config (test fee request với pickup hiện tại) → báo hợp lệ/không; ACL resource.

### Weight contract (DEC-TASKBRKHN4-002)
- [ ] **AC-6 (ShipmentWeightCalculator):** chỉ tính shippable items; loại virtual; xử lý qty; configurable parent/child (không double-count); bundle theo Magento weight semantics; item/product thiếu weight → min/default từ config; **đọc `weight_unit` config → normalize sang gram** (KHÔNG assume kg); rounding rule rõ (`ceil` gram). Test: simple · configurable · bundle · qty>1 · missing weight · decimal weight · virtual · min/default fallback.

### Rate composition (DEC-TASKBRKHN4-002)
- [ ] **AC-7 (Fee response mapper):** parse + persist tối thiểu `fee.fee`, `fee.insurance_fee`, `fee.extFees`, `fee.delivery`. Encapsulate trong response mapper (doc drift → sửa mapper). **`fee.delivery` không cho giao → không trả method.** **Không âm thầm bỏ** insurance/surcharge.
- [ ] **AC-8 (Displayed amount — config multi-select, DEC-TASKBRKHN4-002 Q1 accepted):** displayed shipping amount = **base `fee.fee` + admin multi-select "include" (`insurance_fee`, `extFees`)**; default chỉ `fee.fee` (merchant bật thêm qua config). Breakdown đầy đủ (`fee.fee`/`insurance_fee`/`extFees`/`delivery`) luôn lưu debug log masked bất kể config include. Test order value cao + insurance/surcharge.

### API reliability policy
- [ ] **AC-9 (Timeout):** connection timeout + total request timeout (default đề xuất connect 2s / total 5s; configurable).
- [ ] **AC-10 (Retry):** retry policy (default đề xuất max 1 retry cho network error phù hợp); **không retry mù với HTTP 4xx**.
- [ ] **AC-11 (Graceful failure):** GHTK timeout/error → **không crash** shipping estimation; không trả rate; log masked. `collectRates()` không bao giờ throw ra ngoài Magento rate pipeline.
- [ ] **AC-12 (Short-TTL rate cache):** cache rate TTL 5–15 phút (configurable). Cache key tối thiểu: pickup identity · destination identity · weight · declared value · transport · relevant service parameters. **Không cache** token hoặc dữ liệu nhạy cảm.

### Security (DEC + AGENTS §7.2/§7.4)
- [ ] **AC-13 (Secrets/logging):** API Token **Encrypted backend**; `X-Client-Source` admin config **plaintext** (Q3 resolved — KHÔNG encrypt; không phải secret). Logger mask Token · phone · email · full address · customer PII. Production log chỉ: `correlation_id` · Magento order/quote/shipment ID · HTTP status · GHTK error code/message · masked destination · retry attempt. **Không log** raw order payload hoặc decrypted token.
- [ ] **AC-14 (Non-VN gate + scope):** non-VN country → carrier inactive; pickup misconfig → inactive theo scope.

## Technical Notes

- **Base URI (locked):** runtime default `https://services.giaohangtietkiem.vn`; endpoints `GET /services/shipment/fee` (rate) [order ở TASK-KV328X]. Một API client/config abstraction duy nhất; override base cho test/proxy allowed.
- **Fee API:** `province` + `ward` req (name), `weight` (gram), `value`, `transport`; `district` opt. Headers `Token` + `X-Client-Source`.
- **Weight:** unit config (`weight_unit`, default đề xuất `kg`) → gram; không assume. Audit: no weight-unit convention exists in project.
- **Reliability defaults** (project chưa có standard): connect 2s / total 5s / retry 1 (network) / cache 5–15 min.
- **Cache key** must include every affecting parameter (AGENTS §7.2 — cache hot columns).
- Depends on TASK-YJENM2 (mapping table + DestinationAddressResolver).

## Files/Areas Affected (planned)

- `app/code/Secomm/Ghtk/etc/config.xml` (carrier `ghtk`) + `module.xml`/`registration.php` — NEW
- `app/code/Secomm/Ghtk/etc/adminhtml/system.xml` (+ `acl.xml`) — NEW
- `app/code/Secomm/Ghtk/Model/Carrier/Ghtk.php` (`collectRates`) — NEW
- `app/code/Secomm/Ghtk/Model/Address/PickupAddressResolver.php` — NEW
- `app/code/Secomm/Ghtk/Model/Shipment/ShipmentWeightCalculator.php` — NEW
- `app/code/Secomm/Ghtk/Model/GhtkApiClient.php` (base URI abstraction + timeout/retry) — NEW
- `app/code/Secomm/Ghtk/Model/Fee/ResponseMapper.php` (rate composition) — NEW
- `app/code/Secomm/Ghtk/Model/RateCache.php` — NEW
- `app/code/Secomm/Ghtk/Controller/Adminhtml/Ghtk/TestConnection.php` — NEW
- `app/code/Secomm/Ghtk/i18n/vi_VN.csv` + `en_US.csv` — NEW
- **Reuse, không sửa:** `Secomm_AddressDropdown`, `Secomm_VietNamAddress`, TASK-YJENM2 mapping.

## Risks

- Tier-2: chạm checkout-critical rate path + secret token + external API → escalate (SA/TL).
- Weight unit assumption sai → sai phí (AC-6 mitigates).
- Rate composition business rule (Q1) chưa chốt → block `ready`.
- Rate cache key thiếu tham số → rate stale/sai.

## Open Questions

- ~~Q1 (DEC-TASKBRKHN4-002): displayed shipping amount = `fee.fee` alone hay bao gồm insurance/surcharge?~~ **RESOLVED 2026-07-30:** config multi-select "include" (`insurance_fee`, `extFees`) trên base `fee.fee`.
- Q2 (external verify): fee response field contract thực tế có đúng doc VN? (mapper cô lập drift)
- ~~Q3 (SA): `X-Client-Source` có cần encrypt?~~ **RESOLVED 2026-07-30 (user): KHÔNG encrypt** — plaintext admin config (không phải secret).

## Definition of Done

- [ ] Carrier + config + i18n (AC-1/2/3)
- [ ] Pickup resolver + health check (AC-4/5)
- [ ] Weight calculator — all test cases (AC-6)
- [ ] Rate composition mapper + breakdown (AC-7/8)
- [ ] Reliability: timeout/retry/graceful/cache (AC-9/10/11/12)
- [ ] Security: encrypted token + mask log (AC-13)
- [ ] Non-VN gate (AC-14)
- [ ] Q1 (DEC-TASKBRKHN4-002) approved hoặc pending đúng chuẩn
- [ ] AI pre-review pass
- [ ] **TL review approved** (Tier 2)
- [ ] QC: rate đúng trên cart estimate + checkout (VN); API timeout/error → no crash, no rate; cache hit/stale; high-value + insurance/surcharge; non-VN inactive
- [ ] Evidence `.ai/runtime/evidence/FEAT-AE761Z/` (TASK-BRKHN4)

## Related

- Spec: [ghtk-carrier-rate](../specs/SPEC-TASK-BRKHN4-ghtk-carrier-rate.md) · Plan: [TASK-BRKHN4 plan](../plans/TASK-BRKHN4-implementation-plan.md)
- Feature: [FEAT-AE761Z](../records/features/FEAT-AE761Z.md) · Depends on [TASK-YJENM2](TASK-YJENM2-ghtk-address-mapping.md) · Decisions: [DEC-TASKBRKHN4-001](../records/decisions/DEC-TASKBRKHN4-001.md) (resolver split) · [DEC-TASKBRKHN4-002](../records/decisions/DEC-TASKBRKHN4-002.md) (weight + rate composition)
