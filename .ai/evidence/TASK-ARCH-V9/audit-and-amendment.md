# Evidence — Architecture Revision v9 (Carrier Eligibility / Zones / Rate Source Mode / Address Resolution Policy)

Ngày: 2026-09-18 · Material architecture amendment (doc + governance ONLY — 0 production code).

## Delta matrix (directive §2)

| Concern | Existing contract | Existing semantics | New proposal | Overlap/conflict | Recommendation |
|---|---|---|---|---|---|
| LegacyRateStrategy | `LegacyRateStrategy` (DIRECT_FALLBACK/MAP_THEN_FALLBACK) + assertKnown | skip-mapping vs map-then-fallback cho legacy RATE | RateSourceMode + AddressResolutionPolicy | **OVERLAP trục config** (DIRECT≡FALLBACK_ONLY; MAP_THEN≡CARRIER_WITH_FALLBACK+FALLBACK policy) | **FULLY SUPERSEDE** (constants deprecated, alias transition) |
| FallbackEligibility | `FallbackEligibilitySource` 2 nguồn + VO | TECHNICAL_FALLBACK \| LEGACY_ADDRESS_FALLBACK | mở rộng: + INTEGRATION_LIMITATION (mapping missing/capability/auth-config) | **CONFLICT với §14 "ĐÚNG 2 nguồn, không mở rộng"** + §23 v7 đã accept CANONICAL_AMBIGUOUS eligible (mâu thuẫn nội bộ §14-vs-§23) | **NORMALIZE** (taxonomy mở rộng, business rejection NO) |
| CarrierRateOutcome | 3-state SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE | failure classification | giữ nguyên | none | KEEP |
| ShippingFailureReason | 5 shared reasons, diagnostics-only | status vs reason | giữ nguyên | none | KEEP |
| ServiceLevelRateAggregate/Decision | hasSuccessfulRate/hasTechnicalFailure + REALTIME/FALLBACK/UNAVAILABLE | technical-only eligibility signal | + eligibility v9 input | minimal extension | EXTEND (implementation sau) |
| FallbackPolicyInterface | per-level enabled | per-level on/off | + per-source eligibility defaults | extend nhẹ | EXTEND (implementation) |
| CarrierOperationAddressCapability | per-op scheme/representations/textual (Y3X6H5) | per-operation | giữ nguyên | none | KEEP |
| CarrierAddressHandoff | handoffForOperation + candidates + representations | per-op handoff | giữ nguyên | none | KEEP |

## V9 decisions (DEC-FEATYA2C0W-006)

1. CarrierEligibility + DestinationScope (ALL | SELECTED_ZONES) — evaluate theo canonical identity
   TRƯỚC provider conversion.
2. Canonical Zones: ZoneDefinition generic (code/label/enabled/include province/include ward/
   exclude ward); zone codes = composition data; KHÔNG DSL/GIS; INTERPROVINCE origin-relative →
   CarrierEligibilityContext.
3. RateSourceMode: CARRIER_ONLY / CARRIER_WITH_FALLBACK (default Launchpad) / FALLBACK_ONLY —
   FALLBACK_ONLY vẫn respects eligibility + skip resolution/mapping/RATE API.
4. AddressResolutionPolicy: STRICT | FALLBACK; **PICK_PRIMARY = DEFERRED** (không real consumer;
   reopen cần deterministic selection + provenance; `$candidates[0]` vẫn cấm).
5. Fallback eligibility normalize: technical YES · mapping-missing YES (outcome vẫn UNAVAILABLE —
   không reclassify) · capability-unsupported YES · AMBIGUOUS+policy-FALLBACK YES · auth/config
   configurable YES + **mandatory high-severity warning** · invalid-address/business-rejection NO.

## Consistency verification

```text
$ grep bare never-fallback không kèm strategy context → 0 (§29/§35.4 STRICT semantics là chính xác)
$ grep AMBIGUOUS=TECHNICAL_FAILURE reclassify → 0 (chỉ mapping-description "UNAVAILABLE vs TECHNICAL_FAILURE")
$ grep ShippingCore→Mageplaza trực tiếp → 0 real hit (4 adjacency đều là bridge dependency graph §21 đúng ownership)
$ Rev order: v3 → v4 → v5 → v5.1 → v6 → v7 → v8 → v9 (chronological, không retroactive minor)
```

## Files changed (doc + governance ONLY)

- `address-shipping.md`: header **Revision v9** + §14 supersession note + §15.1 supersession note
  + **§35 mới** (35.1 eligibility/scope · 35.2 zones · 35.3 rate source mode · 35.4 address policy ·
  35.5 eligibility normalization · 35.6 processing order · 35.7 defaults · 35.8 migration · 35.9
  ownership) + §27 row + §28 v9 reopen note + §29 +6 câu hỏi + §31 step 7.
- `DEC-FEATYA2C0W-006.md` (mới — material amendment) + `DEC-FEATFQWEQ3-001.md` implementation
  addendum (VietMap removed → legacy strategy path) + `CURRENT_STATE.md` (VietMap blockers
  update).

**0 production code thay đổi** (ShippingCore/Ghn/Ghtk/Launchpad/Mageplaza nguyên vẹn trong task
này). Full suite không re-run (0 code change — smoke php -l các file chạm gần nhất đã pass ở
TASK-5JQYMP).
