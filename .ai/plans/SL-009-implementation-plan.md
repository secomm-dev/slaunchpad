# Implementation Plan: SL-009 — GHTK carrier + rate calculation

| Field | Value |
|---|---|
| Specification | specs/SPEC-SL-009-ghtk-carrier-rate.md |

> Mode A · **Plan only — chưa viết code** (Hard Gate 3: No Code Without Plan).
> Tier-2 (shipping carrier + external API + secret token + checkout-critical rate path — §12) → escalate SA/TL; code review trước/sau.
> Parent: [FEAT-006](../records/features/FEAT-006.md). Ticket: [SL-009](../tickets/SL-009-ghtk-carrier-rate.md). Spec: [ghtk-carrier-rate](../specs/SPEC-SL-009-ghtk-carrier-rate.md).

## Metadata

| Field | Value |
|-------|-------|
| Ticket | [SL-009](../tickets/SL-009-ghtk-carrier-rate.md) |
| Spec | [ghtk-carrier-rate](../specs/SPEC-SL-009-ghtk-carrier-rate.md) |
| Feature | [FEAT-006](../records/features/FEAT-006.md) |
| Author | AI draft |
| Reviewer (TL) | user (acting as SA/TL) — **pending approval** |
| Workflow Mode | A |
| Date | 2026-07-30 |
| Decisions | DEC-021 (accepted) · DEC-022 (accepted, multi-select) · DEC-018/019/DEC-8 |
| Validation level | **L3** (shipping carrier + secret + checkout-critical — §8.6) |
| Depends on | [SL-008](SL-008-implementation-plan.md) (DestinationAddressResolver + mapping) |

## 1. Approach

**Carrier-owned rate logic, reuse SL-008 resolver (DEC-018/019):** `Secomm_Ghtk` owns carrier + rate; reuse `DestinationAddressResolver` (SL-008). KHÔNG sửa generic.

- **`collectRates` resilient-by-default**: non-VN → empty; pickup invalid → empty; weight từ `ShipmentWeightCalculator` (unit config, không assume kg); `fee` API qua `GhtkApiClient` (base URI lock + timeout/retry); response mapper (rate composition config multi-select); `delivery=false` → no method; catch-all → log masked + empty (no throw).
- **Base URI locked** `https://services.giaohangtietkiem.vn` trong một `GhtkApiClient`/config abstraction (override cho test/proxy).
- **Reliability defaults** (project chưa có standard): connect 2s / total 5s / retry 1 (network) / cache 5–15 min — configurable.
- **Security**: Token Encrypted backend; mask logger; no raw payload log.

**Lý do chọn:** catch-all + strict pickup gate → không bao giờ crash checkout hay trả rate ảo; weight unit config → không sai phí; mapper cô lập API drift.

## 2. Files affected

| File | Change type | Lý do / AC |
|------|-------------|-------|
| `Secomm/Ghtk/etc/config.xml` (carrier `ghtk` + defaults) | new | carrier registration + default config (AC-001/002) |
| `Secomm/Ghtk/etc/adminhtml/system.xml` + `acl.xml` | new/modify | config fields + ACL (AC-002/005) — append vào SL-008 system.xml |
| `Secomm/Ghtk/Model/Carrier/Ghtk.php` | new | `collectRates` + `getAllowedMethods` (AC-001/011/014) |
| `Secomm/Ghtk/Model/Address/PickupAddressResolver.php` | new | pickup strict gate (AC-004) |
| `Secomm/Ghtk/Model/Shipment/ShipmentWeightCalculator.php` | new | unit→gram, shippable only, configurable/bundle (AC-006) |
| `Secomm/Ghtk/Model/GhtkApiClient.php` (+ `GhtkConfig`) | new | base URI lock + timeout/retry + headers (AC-009/010) |
| `Secomm/Ghtk/Model/Fee/FeeResponseMapper.php` | new | parse fee + delivery check (AC-007) |
| `Secomm/Ghtk/Model/Fee/RateComposer.php` | new | displayed amount = base + multi-select include (AC-008) |
| `Secomm/Ghtk/Model/RateCache.php` | new | short-TTL cache, key hash đầy đủ (AC-012) |
| `Secomm/Ghtk/Model/Log/MaskingLogger.php` (hoặc plugin PSR-3) | new | mask token/phone/email/address + correlation_id (AC-013) |
| `Secomm/Ghtk/Controller/Adminhtml/Ghtk/TestConnection.php` | new | connectivity/health check (AC-005) |
| `Secomm/Ghtk/etc/di.xml` (preferences + cache type + virtual types cho Guzzle/curl) | new | DI wiring + cache + HTTP client opts |
| `Secomm/Ghtk/i18n/vi_VN.csv` + `en_US.csv` | modify | carrier/method title + admin labels (AC-003) |
| **Reuse** `DestinationAddressResolver` (SL-008) | — | inject vào carrier (AC-001 flow) |
| **Không sửa** generic modules | — | DEC-019 |

## 3. Steps (độc lập reviewable, theo thứ tự)

1. **Carrier skeleton + config + i18n** — risk: low — deps: SL-008
   - `config.xml` carrier `ghtk` (active/method/title defaults); `system.xml` fields (token Encrypted backend, `X-Client-Source`, base URI override, pickup fields, transport, `weight_unit`, min weight, timeout/retry/cache TTL, rate-composition multi-select); `acl.xml`; i18n vi/en.
   - verify: admin config render; carrier registered (`bin/magento` carrier list); token persist encrypted.
2. **GhtkApiClient (base URI + timeout/retry + masking logger)** — risk: high — deps: 1
   - `GhtkConfig` hold default `https://services.giaohangtietkiem.vn` + override; `fee()` GET với headers `Token`+`X-Client-Source`; curl/Guzzle opts (connect 2s / total 5s); retry max 1 (network error set, **no 4xx**); `MaskingLogger` (correlation_id + mask token/phone/email/address).
   - verify: request tới base đúng; timeout → exception handled; 4xx → no retry; log masked.
3. **PickupAddressResolver** — risk: medium — deps: 1
   - `pick_address_id` ưu tiên; không có → resolve `pick_province`+`pick_ward` (`pick_district` opt); thiếu/resolve fail → trả `invalid` (carrier inactive theo scope).
   - verify: config đầy đủ → pickup obj; thiếu → invalid flag.
4. **ShipmentWeightCalculator** — risk: high — deps: 1
   - shippable only; skip virtual; configurable/bundle parent/child (no double-count); qty; `product->getWeight()`; missing/0 → min config; `weight_unit` (default kg) → gram `ceil`.
   - verify: simple/configurable/bundle/qty>1/missing/decimal/virtual/min → gram đúng (unit test matrix).
5. **FeeResponseMapper + RateComposer** — risk: medium — deps: 2
   - mapper parse `fee.fee`/`insurance_fee`/`extFees`/`delivery` (defensive); `delivery=false` → no method. RateComposer: amount = `fee.fee` + selected include (config multi-select `insurance_fee`,`extFees`); breakdown log masked.
   - verify: each include combo; delivery=false → empty; high-value + insurance/surcharge.
6. **Carrier `collectRates` + gate + cache** — risk: high — deps: 2,3,4,5
   - non-VN → empty; pickup invalid → empty; cache key hash (pickup+dest+weight+value+transport+params) TTL 5–15min; gọi `fee`; compose; `delivery` check; catch-all → log masked + empty (**no throw**).
   - verify: VN → 1 method đúng amount; non-VN empty; timeout → empty no-crash; cache hit/stable; config change → recompute.
7. **Connectivity/health check** — risk: medium — deps: 2,3
   - admin action `TestConnection` → test `fee` request với pickup hiện tại → báo hợp lệ/không; ACL.
   - verify: pickup hợp lệ → ok; pickup sai/missing → báo lỗi; ACL gate.
8. **Tests + QC + evidence** — risk: low — deps: 6,7
   - unit/integration: weight matrix, mapper, composer, cache key; QC matrix (VN rate/non-VN empty/timeout no-crash/cache/include-combos/health-check/token-encrypted/mask).
   - evidence → `.ai/runtime/evidence/FEAT-006/` (SL-009).

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| `collectRates` throw → crash checkout/cart estimate | high | catch-all → log masked + empty result (AC-011); L3 checkout QC |
| Weight unit sai → sai phí | high | `weight_unit` config; test matrix |
| Mageplaza OSC / cart estimate regression | high | OSC out of scope; smoke-test OSC rate unchanged; L3 |
| Rate cache stale/sai (thiếu key) | medium | key đầy đủ (AC-012); invalidate on config change |
| Token leak | high | Encrypted backend + mask logger (AC-013) |
| `fee` API field drift | medium | FeeResponseMapper cô lập (Q2 verify) |
| GHTK timeout kéo checkout path | medium | total timeout 5s + cache; retry chỉ network |

## 5. Test approach

- Unit/Integration: `ShipmentWeightCalculator` matrix (simple/configurable/bundle/qty>1/missing/decimal/virtual/min); `FeeResponseMapper` + `RateComposer` (include combos, delivery=false); `RateCache` key stability; `GhtkApiClient` timeout/retry/no-4xx.
- QC (L3): VN rate đúng (cart estimate + checkout); non-VN empty; GHTK timeout/error → no crash, no rate; cache hit + invalidate; high-value + insurance/surcharge; token encrypted in DB; log mask; health check; OSC smoke-test unchanged.
- High-risk: checkout-critical rate path (L3) + secret token.

## 6. Out of scope

- Order sync / GHTK order / webhook / `pick_money` / COD (SL-010, PARKED).
- Mapping table + DestinationAddressResolver (SL-008).
- Cancel API / print label / advanced reconciliation; multi-store/non-VN carrier.

## 7. Open questions / Escalation

- Q2 (external verify): `fee` response field contract thực tế đúng doc VN? — mapper cô lập; verify sandbox.
- ~~Q3 (SA): `X-Client-Source` có cần encrypt?~~ **RESOLVED 2026-07-30 (user): KHÔNG encrypt** — plaintext admin config (không phải secret).
- Cache backend (framework cache vs Redis) — Dev; Redis = prod infra (DEC-2 TBD).
- **Tier-2 escalation required** trước code (shipping carrier + secret + checkout-critical).
