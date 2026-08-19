# Feature Spec — GHTK carrier + rate calculation (SL-009)

Specification ID: SPEC-SL-009
Feature ID: NONE
Specification Level: FULL

<!-- Generated for Secomm Launchpad · Stack: Magento 2.4.8-p5 + Hyvä 3.x -->
<!-- Spec cho ticket SL-009 (fee scope). Parent: FEAT-006. Mode A · Tier-2. -->
<!-- Depends on SL-008 (DestinationAddressResolver + mapping table). -->
<!-- Decisions: DEC-021 (accepted) · DEC-022 (accepted, rate composition = config multi-select). -->

> **Project**: Secomm Launchpad · **Stack**: Magento 2.4.8-p5 + Hyvä 3.x (Tailwind v4 + Magewire 1.13)

## Feature Overview

**Feature name**: GHTK shipping carrier (`Secomm_Ghtk`) + rate calculation via `fee` API

**Ticket reference**: [SL-009](../tickets/SL-009-ghtk-carrier-rate.md) · Parent [FEAT-006](../records/features/FEAT-006.md)

**Feature type**: New (carrier registration + rate collection)

**Priority**: P1 (High — fee scope thứ 2, sau SL-008)

**Scope note (2026-07-30)**: fee-first. Order sync (SL-010) PARKED. Spec này chỉ bao phủ rate calculation (mapping từ SL-008 → fee API → rate). Không tạo GHTK order.

---

## User Stories

- **US-001**: As a shopper (VN), I want to see an accurate GHTK shipping rate on cart estimate + checkout so that I can choose GHTK with the right cost.
- **US-002**: As a merchant admin, I want to configure the API token (encrypted), partner code, pickup address, transport, weight unit, and which fee components (insurance/extFees) are included in the displayed amount.
- **US-003**: As a TL/SRE, I want GHTK API failures/timeouts to never crash shipping estimation (no rate, no crash) with masked logs, and a short-TTL rate cache so the checkout critical path stays fast.
- **US-004**: As a merchant, I want a connectivity/health check that validates my pickup configuration against GHTK before go-live.

---

## Acceptance Criteria

### Carrier + config
- [ ] **AC-001 (Carrier registration):** module `Secomm_Ghtk` enabled; carrier `ghtk` in `etc/config.xml`; `Model/Carrier/Ghtk` implements `\Magento\Shipping\Model\Carrier\CarrierInterface` (`collectRates`, `getAllowedMethods`, `isStateZoneable`...). Non-VN country → carrier inactive (gate).
- [ ] **AC-002 (Admin config `system.xml`):** fields — API Token (`\Magento\Config\Model\Config\Backend\Encrypted`), `X-Client-Source` (**plaintext — KHÔNG encrypt**, Q3 resolved), base URI override (single abstraction), pickup address/`pick_address_id`, `pick_province`/`pick_ward`/`pick_district`, `transport` default, `weight_unit` (default `kg`), min/default weight, timeout/retry/cache TTL, **rate composition multi-select "include" (`insurance_fee`, `extFees`)**.
- [ ] **AC-003 (i18n):** carrier/method title vi_VN + en_US (`vi_VN.csv` + `en_US.csv`).

### Pickup resolver (DEC-021)
- [ ] **AC-004 (PickupAddressResolver):** `pick_address_id` present → ưu tiên gửi, không cần pickup mapping. Không có → bắt buộc resolve `pick_province` + `pick_ward` (`pick_district` optional). Config thiếu/resolve fail → **carrier inactive theo scope** (không gửi request mơ hồ, không trả rate).
- [ ] **AC-005 (Connectivity/health check):** admin action test kết nối GHTK + pickup config (test `fee` request với pickup hiện tại) → báo hợp lệ/không; ACL resource.

### Weight contract (DEC-022)
- [ ] **AC-006 (ShipmentWeightCalculator):** chỉ shippable items; loại virtual; xử lý qty; configurable parent/child (không double-count); bundle theo Magento weight semantics; item/product thiếu weight → min/default từ config; **đọc `weight_unit` config → normalize sang gram** (KHÔNG assume kg); rounding `ceil` gram. Test: simple · configurable · bundle · qty>1 · missing · decimal · virtual · min/default fallback.

### Rate composition (DEC-022)
- [ ] **AC-007 (Fee response mapper):** parse + persist `fee.fee`, `fee.insurance_fee`, `fee.extFees`, `fee.delivery` (encapsulate trong mapper — doc drift → sửa mapper). **`fee.delivery` không cho giao → không trả method.** **Không âm thầm bỏ** insurance/surcharge.
- [ ] **AC-008 (Displayed amount = config multi-select, DEC-022 accepted):** displayed = **base `fee.fee` + admin multi-select "include" (`insurance_fee`, `extFees`)**; default chỉ `fee.fee`. Breakdown đầy đủ luôn lưu debug log masked. Test order value cao + insurance/surcharge.

### API reliability policy
- [ ] **AC-009 (Timeout):** connection timeout + total request timeout (default connect 2s / total 5s; configurable).
- [ ] **AC-010 (Retry):** retry policy (default max 1 retry cho network error phù hợp); **không retry mù HTTP 4xx**.
- [ ] **AC-011 (Graceful failure):** GHTK timeout/error → **không crash** shipping estimation; không trả rate; log masked. `collectRates()` không bao giờ throw ra ngoài Magento rate pipeline.
- [ ] **AC-012 (Short-TTL rate cache):** cache rate TTL 5–15 min (configurable). Cache key tối thiểu: pickup identity · destination identity · weight · declared value · transport · relevant service params. **Không cache** token hoặc dữ liệu nhạy cảm.

### Security (AGENTS §7.2/§7.4)
- [ ] **AC-013 (Secrets/logging):** API Token **Encrypted backend**; `X-Client-Source` admin config **plaintext** (Q3 resolved — KHÔNG encrypt; không phải secret). Logger mask Token · phone · email · full address · customer PII. Production log chỉ: `correlation_id` · Magento quote/order ID · HTTP status · GHTK error code/message · masked destination · retry attempt. **Không log** raw payload/decrypted token.
- [ ] **AC-014 (Non-VN gate + pickup validity):** non-VN country → carrier inactive; pickup misconfig → inactive theo scope (AC-004).

---

## Technical Notes

- **Carrier `Model/Carrier/Ghtk`**: `collectRates($request)` → `Result` factory; one method `ghtk` (`getAllowedMethods`). Gate: `countryId !== 'VN'` → return empty result (no throw). Pickup invalid → empty result.
- **Base URI (locked, single abstraction)**: `Model/GhtkApiClient` (hoặc `GhtkConfig`) hold runtime default `https://services.giaohangtietkiem.vn`; endpoint `GET /services/shipment/fee`; override base cho test/proxy qua config (không hard-code rải rác, không expose phổ thông). Headers `Token: {decrypted}` + `X-Client-Source`.
- **Flow**:
  ```
  collectRates(request):
    if countryId != 'VN': return empty                      // gate (AC-014)
    dest = DestinationAddressResolver(country, region_id, ward_id|ward_name)   // SL-008
    pickup = PickupAddressResolver(config)                  // AC-004; invalid → empty
    weight = ShipmentWeightCalculator(request)              // AC-006 (gram)
    cacheKey = hash(pickup, dest, weight, value, transport, params)
    if cached: return cached Result
    resp = GhtkApiClient.fee(dest, pickup, weight, value, transport)   // AC-009/10 (timeout/retry)
    fee = FeeResponseMapper(resp)                           // AC-007
    if !fee.delivery: return empty                          // delivery denies → no method
    amount = fee.fee + sum(selected include: insurance_fee?, extFees?)   // AC-008 (config multi-select)
    build Method(title, amount) → Result; cache TTL 5-15min (AC-012)
    catch all → log masked + return empty (AC-011, no throw)
  ```
- **ShipmentWeightCalculator**: iterate `$request->getAllItems()`; skip `getParentItem()` (configurable/bundle parent) where child counts; skip virtual/downloadable; `qty`; `product->getWeight()`; missing/0 → config min; `weight_unit` (default kg) → `*1000` gram; `ceil`. Audit: no weight-unit convention in project (TableRate reads raw `weight`) → `weight_unit` config is the source of truth.
- **FeeResponseMapper**: defensive parse (field missing → null); encapsulate doc drift; `delivery` boolean check.
- **Reliability defaults** (project chưa có standard): connect 2s / total 5s / retry 1 (network only) / cache 5–15 min — all configurable.
- **Logger**: PSR-3 với mask processor (token, phone `\d{6,}` mask, email `j***@x`, full address → ward-level masked); `correlation_id` per `collectRates` call.
- **Cache**: `Magento\Framework\App\CacheInterface` tag `secomm_ghtk_rate`; key hash bao gồm mọi tham số ảnh hưởng (AC-012). Không cache token.
- Depends on SL-008 (DestinationAddressResolver + mapping).

---

## Dependencies

| Dependency | Type | Status | Notes |
|------------|------|--------|-------|
| SL-008 (mapping + DestinationAddressResolver) | ticket | ready | `resolve()` dùng trong `collectRates` |
| DEC-021 (dest/pickup resolver split) | decision | accepted | pickup resolver = SL-009 |
| DEC-022 (weight + rate composition) | decision | accepted | multi-select include |
| FEAT-005 / SL-003 (VN 2-level address on quote) | feature | proposed | quote address carry region + ward |
| GHTK `fee` API contract | external | needs verify | mapper cô lập drift (Q2) |

---

## Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Weight unit assumption sai → sai phí | M | H | `weight_unit` config (không assume kg); test simple/configurable/bundle/missing |
| `collectRates` throw → crash checkout | M | H | catch-all → log masked + empty result (AC-011) |
| Rate cache key thiếu tham số → rate stale/sai | M | M | key = pickup+dest+weight+value+transport+params (AC-012) |
| Token log/leak | L | H | Encrypted backend + mask logger (AC-013) |
| Pickup misconfig → rate ảo | M | M | PickupAddressResolver strict gate (AC-004) + health check |
| `fee` response field drift | M | M | FeeResponseMapper cô lập (Q2 verify) |

---

## Out of Scope

- Order sync / GHTK order creation / webhook / `pick_money` / COD (→ SL-010, PARKED).
- Mapping table + DestinationAddressResolver (→ SL-008).
- Cancel API / print label / advanced reconciliation.
- Multi-store/non-VN carrier support (non-VN = inactive gate).

---

## Test Notes

- **Carrier**: `collectRates` VN → 1 method đúng amount; non-VN → empty (no throw); pickup misconfig → empty.
- **Weight**: simple/configurable/bundle/qty>1/missing/decimal/virtual/min-fallback → gram đúng.
- **Rate composition**: `fee.fee` only (default); +insurance; +extFees; both; `delivery=false` → no method; high-value order + insurance/surcharge.
- **Reliability**: timeout → empty (no crash); retry 1 (network); 4xx → no retry; cache hit + invalidate on config change; cache key stability.
- **Security**: token encrypted in config; log mask (token/phone/email/address); no raw payload.
- **Health check**: test connection với pickup hợp lệ/không.
- Evidence → `.ai/runtime/evidence/FEAT-006/` (SL-009).
