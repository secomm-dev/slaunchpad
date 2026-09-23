# TASK-WAWNDS — GHN RATE Type-5 / Multi-parcel Estimation — Evidence (PAUSED draft)

> Trạng thái: **PAUSED/UNCOMMITTED** — chờ ShippingCore v10 frozen contracts (TL prompt).
> Sandbox: staging dev-online-gateway.ghn.vn, shop 200537 (token mới rotated 2026-09-17 —
> không bao giờ in token). Probes sanitized: chỉ in provider codes/messages/fee totals.

## 1. Contract evidence matrix (AC-1 — provenance tách bạch)

| Fact | OFFICIAL_DOCUMENTED (docs 2026-09-18) | SANDBOX_OBSERVED (probes 2026-09-18) | SECOMM_ASSUMPTION |
|---|---|---|---|
| service_type_id | 2 = total <20kg; 5 = ≥20kg OR multi-parcel; threshold = TOTAL weight | A/B/C/D khớp | — |
| Fee type-5 `items[]` | "items used for heavy goods"; shape theo create doc; "fees per parcel from items[]" | **BẮT BUỘC** — root-only → 400 "Cân nặng không hợp lệ" (B2) | — |
| items[] sub-fields | name ≤512; quantity min 1; **weight/dims required type-5 (create)** | **fee: per-item dims OPTIONAL** (I3 weight-only = 200); root-only = reject | — |
| quantity semantics | "Minimum 1" — **không định nghĩa N-units vs N-parcels** | **qty=2 ≠ 2 rows** (C2 616,000 ≠ C 605,000); 25kg I1≡I2 (không nhất quán) | assumption "aggregation OK" BÁC BỎ → per-unit rows |
| root weight 50,000g | create-root cap | fee: KHÔNG enforce aggregate (D 2×35kg = 200) và KHÔNG enforce per-parcel (F 60kg = 200) | GHN-D "per-package 50kg" → CHỈ CREATE semantics; KHÔNG pre-reject RATE |
| dims 200cm | create-root cap | **fee KHÔNG enforce; create REJECT >150cm** ("Kích thước (dài) vượt quá mức cho phép: 150") | GHN-D "200cm/package" supersedes |
| dims per-parcel hard limit | 200cm (docs) | **150cm (sandbox create rejection)** — conflict docs-vs-sandbox; sandbox thắng cho RATE | — |
| Aggregate >50kg | — | KHÔNG bị chặn (D) | — |
| Fee type-2 dims | optional | **THAY ĐỔI GIÁ** (A 70,400 → A2 185,900) | OMIT dims policy giữ |

## 2. Sandbox probes (§35, sanitized — token never printed)

| # | Probe | Kết quả |
|---|---|---|
| A | type2 1500g no-dims | 200 total=70,400 |
| A2 | type2 +dims 40/30/20 | 200 total=185,900 (**dims đổi giá**) |
| B | type5 25kg item qty1 +dims | 200 total=605,000 |
| B2 | type5 root-only NO items | **400 "Cân nặng không hợp lệ"** → items[] BẮT BUỘC |
| C | type5 2×15kg | 200 total=605,000 |
| C2 | type5 1 row qty2×15kg | 200 total=**616,000** (≠ C) → per-unit rows |
| D | type5 2×35kg (agg 70kg) | 200 — **không aggregate cap** |
| E | single 50kg | fee 200; create bị upstream tenant-api timeout — **UNSETTLED** |
| F | single 60kg | fee 200 — **fee KHÔNG enforce 50kg**; create timeout — **UNSETTLED** |
| G/H | len 200/210 | fee 200; create reject "…: 150" — **150cm SANDBOX-OBSERVED** |
| I1/I2 | qty2×25kg vs 2 rows | đều 605,000 (trùng C2 case khác) |
| I3 | type5 item no-dims | 200 — dims optional ở fee |

## 3. GHN-specific implementation draft (preserved, uncommitted)

- `Model/Rate/{QuoteParcelEstimate, EstimatedPackage, QuoteParcelEstimator, GhnPackageLimits, GhnRateAdjuster, GhnRateEstimationException (Model/Exception/)}`.
- Wiring: `GhnRateQuery` (estimate-based), `GhnRateRequestMapper` (estimator), `GhnRateCalculator`
  (type selection, per-unit items[] payload, 150cm pre-gate trước handoff/fee, diagnostics log),
  carrier estimation-exception catch → UNAVAILABLE + reason verbatim.
- Magento parent-parity expansion (virtual skip, configurable parent-weight, ship-separately
  children, double-count guard), decimal qty → estimation-unavailable, weight qua
  `StoreWeightConverter` fail-closed. Bundle-with-children ship-together = Magento-defined
  1 solid (parent weight = Magento-resolved dynamic); ship-separately = per-child units.
- Tests: Ghn **342/0F/0E** (estimator/mapper 12, calculator 24 incl. hard-limit + aggregate
  distinction + missing-dims, adjuster 5). Cross-module: 1 failure EXTERNAL (VietNamAddress
  VnMappingReaderTest — stream khác sửa 11:35 hôm nay, ngoài stream).

## 4. v10 realignment (§32 audit + §3/§4/§33)

- **Legacy/v10-generic audit (§32)**: `LegacyRateStrategy`/`DIRECT_FALLBACK`/`MAP_THEN_FALLBACK`/
  `RateSourceMode`/`PICK_PRIMARY`/zones/eligibility — **NOT PRESENT trong Secomm_Ghn** (grep 0
  production refs; các terms chỉ tồn tại ShippingCore `Api/Fallback/LegacyRateStrategy`).
- **FALLBACK_ONLY** (§4): GHN không biết RateSourceMode — misuse-detection CHỜ v10 contract
  (orchestrator phải không invoke GHN RATE trong FALLBACK_ONLY); documented tại
  `GhnRateCalculator` entry docblock + đây.
- **v10 dependency cần từ ShippingCore (§33, chưa frozen in code)**: input boundary
  "selected canonical PRE-2025 destination (unit_code) đã chọn bởi orchestrator" để thay handoff
  call trong `resolveAndQuote` — migration adapter point hiện tại = handoff call; PICK_PRIMARY
  applicability = GHN RATE (legacy-scheme) theo DEC-FEATYA2C0W-006 amendment 2026-09-18.
- **Buffer boundary** (§30): `apply_to = carrier_and_fallback` — composition layer phải áp
  (GHN không tự gọi fallback); nếu v10 không expose fallback rate cho adjustment → REPORT
  generic contract gap, không hack local.

## 5. Gates

- Ghn scoped: **342/0F/0E** (342 = 326 + 16 net WAWNDS).
- Cross-module: 1 failure EXTERNAL (VnMappingReaderTest — concurrent stream, 11:35, không thuộc stream này).
- `setup:di:compile`: **GREEN** (chạy sau tất cả WAWNDS code).
- Validator: **0 fail reference WAWNDS** (32/82 pre-existing stream khác).
- External concurrent-edit note: VietNamAddress `VnMappingReader*` modified 11:35 bởi stream
  khác → failure của stream đó, document riêng (§38).

## 6. v10 adaptation (TL prompt — RE-FROZEN contracts, 2026-09-18)

- **§1 compile blocker**: system.xml `<option value="…"/>` — system.xsd chỉ cho phép text-content
  options (`<option label="…">value</option>`); fixed 7 legacy + 6 mới → `SCHEMA VALID` qua
  `vendor/magento/module-config/etc/system.xsd`.
- **§3/§6/§7 v10 consume**: `Config::getRateSourceMode/getAddressResolutionPolicy`
  (whitelist → frozen constants, fail-closed defaults); carrier entry guard
  `FALLBACK_ONLY → GHN_RATE_SKIPPED_FALLBACK_ONLY` (thin adapter read duy nhất);
  `GhnRateCalculator` pass policy verbatim vào `handoffContextForOperation` (4th param v10).
  PICK_PRIMARY boundary: mapping resolver input = `getResolvedAddress()->getUnitCode()` đơn
  (test `testCandidatesNeverLeakIntoProviderMappingInput`); GHN không đụng
  is_primary/candidate_count/ranking (grep 0).
- **§5 migration**: GHN 0 LegacyRateStrategy refs — không có saved-config migration; defaults
  mới = CARRIER_WITH_FALLBACK + FALLBACK (không merchant bị re-interpret — config cũ không tồn tại).
- **§10 origin**: `origin_district_id` = 1 config value deterministic (empty = shop default) —
  P1 single-origin rule ✓; không có multi-origin routing trong GHN.
- **§12 classification (audit)**: translator hiện có (auth 401-403 / invalid 400,404 /
  rate-limit 429 / 5xx / timeout / fragments "SERVICE IS NOT READY|NO SERVICE|NOT AVAILABLE FOR"
  → ProviderRateUnavailableException) → calculator: technical (timeout/5xx) vs
  unavailable (business); mapping-missing = PROVIDER_MAPPING_MISSING. Khớp shared policy map
  (TASK-5XQXZK): SERVICE_UNAVAILABLE not-eligible (= §12 service-area no-fallback),
  PROVIDER_MAPPING_MISSING eligible (= §12 integration limitation), TECHNICAL eligible.
- **§15/§28**: grep GHN production = 0 Mageplaza/TableRate refs, 0 `$candidates[0]`,
  0 `is_primary`, 0 LegacyRateStrategy branches.
- **Gates**: Ghn **346/0F/0E** (+4 v10 tests) · cross-module **1039/0F/0E** · compile GREEN ·
  validator 0 WAWNDS.

## 7. FREEZE VERIFICATION (2026-09-18 — ShippingCore v10 runtime orchestration COMPLETE, TASK-8MQHJX CLOSED)

- **Runtime ownership (code-truth)**: `CarrierRateExecutionService::execute()` hard-ordered
  (1) `CarrierEligibilityEvaluator` + `CanonicalZoneMatcher/Registry` + `DestinationScope` →
  (2) `RateSourceMode` (FALLBACK_ONLY short-circuit + LEGACY_ADDRESS_FALLBACK eligibility) →
  (3) origin readiness gate → (4) `AddressResolutionPolicy` qua shared handoff (STRICT/
  PICK_PRIMARY blocks) → (5) `RealtimeCarrierRateContributor::contribute(carrierCode, handoff)`
  → eligibility emission (`outcomeDrivenEligibility`: TECHNICAL→TECHNICAL_FALLBACK;
  PROVIDER_MAPPING_MISSING→INTEGRATION_LIMITATION; SERVICE_UNAVAILABLE/capability→NONE;
  INVALID_CONFIGURATION fail-closed). Fallback dispatch = `ServiceLevelRateOrchestrator::decide`
  (max-once, 3-source explicit, reason strings never parsed).
- **GHN contributor**: `Model/Rate/RealtimeRateContributor` + `RealtimeRateContributorFactory`
  (implement `RealtimeCarrierRateContributorInterface` — carrier-owned, no default). Input =
  FINAL gated handoff + đóng RateRequest; output = CarrierRateOutcome. **Structural §14 proof**:
  contributor deps = calculator/request-mapper/logger/request — KHÔNG có handoff-service/context
  builder → Stage-1 canonical resolution không thể lặp trên contributor path. Calculator split:
  `calculate()` (compat/standalone, handoff+policy như cũ) vs `quoteWithHandoff()` (Stage-2+fee,
  public, contributor path) — hard-limit pre-gate idempotent trên CẢ HAI đường.
- **§19 defensive guard classification**: GHN FALLBACK_ONLY carrier-entry guard =
  **DEFENSIVE_ONLY** — normal runtime: `CarrierRateExecutionService` step-2 chặn
  FALLBACK_ONLY TRƯỚC contributor (unit-locked trong ShippingCore `CarrierRateExecutionServiceTest`);
  guard không bao giờ là runtime owner.
- **Tests**: ShippingCore execution/flow/orchestrator tests (TASK-8MQHJX) GREEN — eligibility
  ordering (ineligible → contributor 0), FALLBACK_ONLY (realtime 0), policy blocks
  (AMBIGUOUS không tới contributor), realtime-success suppression. GHN contributor tests 7:
  wrong-carrier LogicException; unresolved-handoff defensive (calculator never);
  resolved-delegation (map→quoteWithHandoff với selected unit); mapping-missing
  UNAVAILABLE+PROVIDER_MAPPING_MISSING verbatim; estimation-limitation structured reason;
  timeout propagates; missing-request LogicException.
- **Gates**: Ghn **353/0F/0E** · ShippingCore **278/0F/0E** · cross-module **1131/0F/0E** ·
  compile GREEN · validator 0 WAWNDS.
- **Runtime smoke**: GraphQL ALL + ward "Tây Thạnh" → `secomm_ghn 53,900 VND` thật
  (`calculate_fee` HTTP 200) sau calculator split — compat path intact. (Apache restart cần
  giữa chừng — environment, không phải code.)
- **§23 deferred seam**: provider-auth/config mandatory-warning seam — vẫn DEFERRED
  (INVALID_CONFIGURATION fail-closed đúng frozen; không blocking freeze).
