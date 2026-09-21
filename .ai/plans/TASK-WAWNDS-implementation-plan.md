# Implementation Plan: TASK-WAWNDS — GHN RATE Type-5 / Multi-parcel Estimation

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-WAWNDS (parent FEAT-FQWEQ3) — GHN-specific RATE capability enhancement |
| Mode | A (checkout pricing path → Tier-2; plan approval = signoff) |
| Specification | Mini-Spec (embedded) — [records/tasks/TASK-WAWNDS.md](../records/tasks/TASK-WAWNDS.md) |
| Contract source | docs re-fetch 2026-09-18 (calculate-fee / create / available-services) + sandbox probes A–I 2026-09-18 + existing matrix §1–§5 (TASK-FMBBSD) |
| Out of Scope | ShippingCore strategy/zones/fallback redesign · address resolution · CREATE changes · cartonization · label · legacy cutover · client-side volumetric pricing |

## Contract evidence matrix (AC-1) — provenance tách bạch

| Fact | OFFICIAL_DOCUMENTED (docs 2026-09-18) | SANDBOX_OBSERVED (probes 2026-09-18) | EXISTING_SECOMM_ASSUMPTION |
|---|---|---|---|
| service_type_id semantics | 2 = total <20kg; 5 = ≥20kg OR multi-parcel; threshold áp TOTAL weight (root) | A/B/C khớp | GhnParcel heuristic (chỉ weight, chưa multi-parcel) |
| Fee type-5 `items[]` | "items used for heavy goods"; shape theo Create doc | **BẮT BUỘC**: root-only type-5 → 400 "Cân nặng không hợp lệ" (B2) | — |
| items[] sub-fields (create doc) | name ≤512; quantity min 1; price optional; **weight/dims REQUIRED type-5 (create)** | **fee: dims per-item OPTIONAL** (I3 = 200); weight-only item OK | — |
| quantity semantics | "Quantity. Minimum 1" — **không định nghĩa N-units vs N-parcels** | **qty=2 ≠ 2 rows** (C2 616,000 ≠ C 605,000); 25kg case I1≡I2 (không nhất quán) → serialize per-unit rows (an toàn) | assumption "aggregation OK" BÁC BỎ |
| Root weight max 50,000g | create root cap (DOCS) | fee KHÔNG enforce aggregate (D 2×35kg=200) **và KHÔNG enforce per-parcel** (F 60kg single = 200) | GHN-D "per-package 50,000g" — áp CREATE; KHÔNG hợp lệ làm pre-reject tại RATE |
| Root dims max 200cm | create root cap (DOCS) | fee KHÔNG enforce (H len=210 = 200) | GHN-D "200cm/package" — CREATE only |
| Type-2 dims tại fee | optional | **dims THAY ĐỔI GIÁ** (A 70,400 → A2 185,900) | policy OMIT dims tại RATE giữ nguyên (không có unit contract) |
| Available Services | service_id "reference only, not used in fee/create"; per-route type list; naming `from_district/to_district` (docs) — legacy naming conflict documented (matrix §2) | chưa probe thêm (§27 decision: fee rejection đủ — KHÔNG thêm runtime call) | existing decision giữ |
| Provider caps thực tế (CREATE) | 50kg/200cm per docs | tạo-heavy r3: Σ60kg ACCEPTED (provenance: GHN-D sandbox); fee accepts mọi giá trị probe | CREATE là nơi cap thật sự matter — unchanged |

**Conclusion**: không có "verified hard limit" nào bị fee enforce trên probes hiện tại → các
`GHN_PACKAGE_*_LIMIT_EXCEEDED` codes KHÔNG emit ở RATE (chỉ dùng nếu tương lai provider evidence
chứng minh rejection — ghi trong taxonomy). Pre-reject CHỈ cho invalid data (weight ≤0 / unit
unknown / bundle-with-children / decimal qty / non-shippable-only quote).

## Approach

1. `Model/Rate/QuoteParcelEstimate` + `EstimatedPackage` VOs (transient, không persist).
2. `Model/Rate/QuoteParcelEstimator`: RateRequest items → units (configurable→child, grouped→
   child simples, virtual/downloadable→skip, bundle-with-children→ESTIMATION_UNAVAILABLE);
   weight per-unit qua `StoreWeightConverter`; decimal qty → ESTIMATION_UNAVAILABLE;
   no-physical-items → ESTIMATION_UNAVAILABLE; parent/child guard via `getHasChildren()`.
3. `GhnRateRequestMapper` → trả `GhnRateQuery` mở rộng (estimate thay GhnParcel đơn); type =
   `packages>1 OR total≥20000 → 5 else 2`.
4. `GhnRateCalculator::fetchFeeTotal`: type-5 payload += `items[]` per-unit rows
   `{name: sku, quantity: 1, weight: grams}`; type-2 giữ nguyên payload hiện tại (weight-only).
5. `GHN_HEAVY_PARCEL_UNSUPPORTED` loại khỏi calculator (happy path heavy giờ supported);
   reason mới `GHN_RATE_ESTIMATION_UNAVAILABLE` (mapping_invalid/reason text).
6. Buffer: system.xml `rate_adjustment` + `Config` accessors + `GhnRateAdjuster` (providerRate
   → finalRate; rounding none|1000|5000; fixed|percent; apply_to field — carrier-only effective,
   carrier_and_fallback documented cho Launchpad stream); log `provider_rate` + `final_rate`.
   Áp tại carrier method-build SAU success; không đổi outcome/eligibility.
7. Tests: estimator matrix (§32 + §39 list), serializer per-unit, calculator type-5 payload,
   type-2 no-regress, adjuster (fixed/percent/rounding/off; không đổi eligibility).

## Verification

- Ghn scoped + cross-module (Ghn/ShippingCore/VietNamAddress/Ghtk) 0F/0E; compile; validator.
- Sandbox (đã chạy + thêm khi cần): A–I evidence sanitized (token never printed).
- Grep: 0 persist estimate; 0 cartonization; buffer post-rate only.

## Risk & open points cho TL

1. Fallback-under-GHN: method fallback 0 VND branded `secomm_ghn` quan sát được trên dev store
   (Launchpad bridge) — vi phạm nguyên tắc §8; cần stream Launchpad/ShippingCore xử lý branding
   (GHN chỉ cung cấp outcome classification).
2. Fee type-5 flat-ish trên probe route (service 550,000 không đổi theo weight) — rate thật có
   thể route-dependent; KHÔNG build local formula (§28).
3. Per-unit expansion: quote quá lớn (hàng trăm units) → payload lớn; chưa thấy provider cap —
   ghi nhận, chưa cap (lean).
