---
id: DEC-FEATYA2C0W-005
title: 'Legacy RATE strategy (FALLBACK_ONLY | MAP_THEN_FALLBACK) + LEGACY_ADDRESS_FALLBACK eligibility — tách fallback eligibility khỏi failure semantics (partial supersede rule "chỉ TECHNICAL_FAILURE fallback được")'
status: accepted             # approved 2026-09-11 (user acting as SA/TL — material architecture amendment v5 directive)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-11
created: 2026-09-11
last_verified: 2026-09-11
verified_against_commit:
supersedes: []   # KHÔNG supersede nguyên vẹn DEC nào — supersede MỘT PHẦN rule trong DEC-FEATYA2C0W-004 scope + SPEC-TASK-NAT3YV r1 (xem §Supersession)
superseded_by:
work_items: [FEAT-YA2C0W, TASK-NQT782, TASK-M3ME32]
---

# Decision Record: Legacy RATE Strategy + LEGACY_ADDRESS_FALLBACK eligibility (fallback eligibility tách khỏi failure semantics)

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-11 (user acting as SA/TL).
     Nguồn: real carrier flow (GHN RATE cần legacy 3-level) + architecture amendment directive
     2026-09-11; architecture source of truth cập nhật lên Revision v5 cùng ngày.
     Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Status

Accepted (2026-09-11 — user acting as SA/TL)

## Context

Storefront/runtime dùng `VN_ADMIN_2025`; một số carrier operation RATE (fee API — GHN đã chứng
minh) vẫn cần địa chỉ legacy 3-level `VN_ADMIN_PRE_2025`. Architecture v4 giữ invariant:
AMBIGUOUS/UNMAPPED → `UNAVAILABLE` → không fallback bao giờ; chỉ `TECHNICAL_FAILURE` → fallback
eligible. Vận hành thực tế yêu cầu merchant phải có thể opt-in chiến lược cho carrier RATE mà
KHÔNG reclassify failure: địa chỉ không map được legacy vẫn là business-unavailable, nhưng
merchant có chủ đích muốn fallback price để checkout không chết.

## Decision

1. **Legacy RATE Strategy** — provider-neutral, per carrier-operation (P1: RATE only;
   CREATE/CANCEL/TRACK không ảnh hưởng):
   - `FALLBACK_ONLY` — skip 2025→PRE-2025 mapping, skip external resolver, skip carrier realtime
     RATE API → explicitly fallback-eligible;
   - `MAP_THEN_FALLBACK` — local mapping trước: unique → carrier RATE API; AMBIGUOUS → external
     resolver (nếu configured) selector trong known candidate set, resolved → RATE, còn
     unresolved → fallback eligible; UNMAPPED → fallback eligible (resolver P1 KHÔNG xử lý).
   - Configuration thuộc carrier/shipping composition; ShippingCore chỉ own provider-neutral
     contracts/orchestration; không hardcode carrier name/provider.
2. **Fallback eligibility tách khỏi failure semantics** — đúng 2 nguồn, explicit orchestration
   state (KHÔNG derive từ `failureReason`):
   - `TECHNICAL_FALLBACK` — carrier/resolver timeout, connection, 5xx, temporary outage;
   - `LEGACY_ADDRESS_FALLBACK` — merchant opt-in qua legacy RATE strategy (FALLBACK_ONLY;
     MAP_THEN_FALLBACK + AMBIGUOUS unresolved; MAP_THEN_FALLBACK + UNMAPPED).
3. **Failure semantics GIỮ NGUYÊN** — không reclassify: AMBIGUOUS → `UNAVAILABLE`; UNMAPPED →
   `UNAVAILABLE`; technical → `TECHNICAL_FAILURE`. `CarrierRateOutcome` giữ
   SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE; status = orchestration, reason = diagnostics.
4. **Bridge invariant strengthening** — fallback pricing bắt buộc đi
   `FallbackRateProviderInterface → Launchpad_MageplazaTableRate → Mageplaza TableRate
   calculation/business semantics`. Bridge = adapter/alternate entry path (không calculator độc
   lập, không duplicate TableRate rules, không bypass business logic chỉ vì method không visible —
   visibility ≠ calculation eligibility). KHÔNG architecture-hardcode `collectRates()`;
   implementation task audit Mageplaza seam. KHÔNG `ShippingCore/Carrier → Mageplaza` cạnh mới.
5. **Realtime suppression giữ nguyên** — any valid realtime SUCCESS cho service level → REALTIME,
   fallback provider không được gọi, bất kể eligibility từ nguồn nào.
6. **Snapshot integrity** — `CanonicalResolutionSnapshot` giữ canonical identities only (không
   provider IDs); fallback usage KHÔNG được fake snapshot thành RESOLVED — failure semantics
   phản ánh đúng resolution state.

## Supersession

**Supersede MỘT PHẦN** rule fallback-eligibility trong:
- `address-shipping.md` Revision v4 (§5.1/§9/§14/§23): *"AMBIGUOUS/UNMAPPED → không fallback bao
  giờ; chỉ TECHNICAL_FAILURE fallback eligible"* → AMBIGUOUS/UNMAPPED **giữ UNAVAILABLE** nhưng
  tạo LEGACY_ADDRESS_FALLBACK eligibility khi merchant opt-in (§15.1 v5).
- `SPEC-TASK-NAT3YV` r1 (§14 E-SL2 conceptual rule "fallback eligible IFF hasTechnicalFailure")
  → eligibility từ ĐÚNG 2 nguồn trên.

**GIỮ NGUYÊN:** DEC-FEATYA2C0W-004 (dependency chain, ownership, bridge isolation, D1–D10),
failure classification, resolver AMBIGUOUS-only P1, shift-left, fallback-as-price-only,
STANDALONE bridge behavior.

## Consequences

- E-SL2 decision flow amend: eligibility là input riêng (không còn IFF hasTechnicalFailure);
  implementation follow-up spec-first (orchestrator nhận eligibility state từ strategy config +
  mapping result).
- Legacy RATE strategy config thuộc carrier/shipping composition; ShippingCore thêm
  provider-neutral seam — KHÔNG hardcode carrier/provider.
- Bảng giá fallback duy nhất qua bridge; double-use STANDALONE vẫn bị cấm như v4.

## Implementation addendum (TASK-5JQYMP, 2026-09-14)

- Implementation identity constant: `LegacyRateStrategy::DIRECT_FALLBACK` ≡ architecture §15.1
  wording "FALLBACK_ONLY" (legacy RATE skip-mapping) — tránh collision với Bridge Operational
  Mode `FALLBACK_ONLY` (§18). Mapping documented, semantics không đổi.
- Contracts shipped: `FallbackEligibilitySource`, `FallbackEligibilityInterface` +
  `FallbackEligibility` VO, `LegacyRateStrategy` (constants + assertKnown); aggregator/aggregate/
  orchestrator nhận optional `?FallbackEligibilityInterface` (BC-safe). Strategy resolver
  interface/config seam DEFER cho consumer thật (directive §16).
- Outcome taxonomy KHÔNG đổi; eligibility không infer từ reason strings.

## Alternatives considered

- Reclassify AMBIGUOUS/UNMAPPED → TECHNICAL_FAILURE để reuse fallback logic: BỊ TỪ CHỐI — phá
  failure semantics, che business/data-gap dưới dạng "sự cố kỹ thuật", sai §1 directive.
- `Secomm_Cod`-style standalone module cho fallback policy: BỊ TỪ CHỐI — scope lean, policy thuộc
  composition (§13/§16 directive).
