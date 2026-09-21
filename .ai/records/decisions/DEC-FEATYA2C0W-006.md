---
id: DEC-FEATYA2C0W-006
title: 'Carrier Eligibility / Destination Scope / Canonical Zones / Rate Source Mode / Address Resolution Policy + Fallback Eligibility normalization — supersede LegacyRateStrategy và rule "chỉ TECHNICAL_FALLBACK eligibility"'
status: accepted             # approved 2026-09-18 (user acting as SA/TL — material architecture amendment v9 directive)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-18
created: 2026-09-18
last_verified: 2026-09-18
verified_against_commit:
supersedes: []   # KHÔNG supersede nguyên vẹn DEC nào — supersede MỘT PHẦN DEC-FEATYA2C0W-005 (eligibility 2-nguồn + LegacyRateStrategy) — xem §Supersession
superseded_by:
work_items: [FEAT-YA2C0W, TASK-M3ME32, TASK-NQT782]
---

# Decision Record: Carrier Eligibility / Destination Scope / Canonical Zones / Rate Source Mode / Address Resolution Policy + Fallback Eligibility Normalization

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-18 (user acting as SA/TL).
     Nguồn: material architecture amendment directive 2026-09-18 (proposal Carrier Eligibility /
     Destination Scope / Rate Source Mode / Address Resolution Policy / Fallback Eligibility
     normalization); architecture source of truth cập nhật lên Revision v9 cùng ngày.
     Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Status

Accepted (2026-09-18 — user acting as SA/TL)

## Context

Carrier RATE cần legacy scheme đã được xử lý bằng `LegacyRateStrategy` (DIRECT_FALLBACK |
MAP_THEN_FALLBACK — DEC-FEATYA2C0W-005). Vận hành mở rộng + proposal mới yêu cầu merchant cấu
hình được: carrier nào eligible cho destination nào (zones), RATE chạy theo mode nguồn nào, và
address resolution policy xử lý AMBIGUOUS thế nào — mà không biến ShippingCore thành routing
engine. Đồng thời eligibility taxonomy hiện tại ("ĐÚNG 2 nguồn") chưa phủ các integration
limitation thật (provider mapping missing, capability unsupported, auth/config failure).

## Decision

1. **CarrierEligibility + DestinationScope** (`ALL` | `SELECTED_ZONES`) — merchant eligibility
   config per carrier; ShippingCore evaluate bằng **canonical address identity TRƯỚC provider
   conversion** (không provider IDs trong zone logic). `CarrierEligibilityContext` model origin
   khi zone origin-relative (INTERPROVINCE).
2. **Canonical Zones** — ShippingCore-owned generic evaluation (`ZoneDefinition`: code, label,
   enabled, include province codes, include ward codes, exclude ward codes); zone CODES là
   merchant/composition data (HCM_INNER… chỉ là example, không phải domain enum). KHÔNG DSL /
   rules engine / GIS / polygon.
3. **RateSourceMode** (`CARRIER_ONLY` | `CARRIER_WITH_FALLBACK` | `FALLBACK_ONLY`) — per carrier
   RATE operation. FALLBACK_ONLY vẫn respects CarrierEligibility (destination ineligible →
   contribution unavailable, KHÔNG expose fallback price giả eligibility). `FALLBACK_ONLY`
   skip resolution/mapping/RATE API (không lãng phí work).
4. **AddressResolutionPolicy** (`STRICT` | `FALLBACK`) — STRICT: AMBIGUOUS → unavailable, không
   fallback vì ambiguity (trừ technical failure độc lập); FALLBACK: AMBIGUOUS → address-related
   fallback eligible (thực tế vẫn qua RateSourceMode + policy). **PICK_PRIMARY = DEFERRED** —
   hiện không có real consumer; nếu reopen bắt buộc deterministic selection + provenance đầy đủ
   (candidate_count/selected/policy/reason/rank/mapping version), `$candidates[0]` vẫn cấm.
5. **Fallback Eligibility normalization** — taxonomy mở rộng từ 2 nguồn:
   TECHNICAL_FALLBACK (giữ) · **INTEGRATION_LIMITATION_FALLBACK** (mới: provider mapping missing,
   capability unsupported, auth/config failure — configurable, BẮT BUỘC high-severity
   admin/operational warning, không mask vĩnh viễn) · LEGACY_ADDRESS_FALLBACK (giữ). Business
   rejection / out-of-service-area / invalid address → KHÔNG fallback. Outcome taxonomy
   `CarrierRateOutcome` KHÔNG đổi — separation outcome semantics vs fallback eligibility.
6. **LegacyRateStrategy: FULLY SUPERSEDED (config axes)** bởi
   `RateSourceMode` + `AddressResolutionPolicy`: DIRECT_FALLBACK ≡ FALLBACK_ONLY;
   MAP_THEN_FALLBACK ≡ CARRIER_WITH_FALLBACK + AddressResolutionPolicy::FALLBACK. Không giữ cả
   hai long-term. Migration: carriers chuyển config; deprecated alias trong transition.
7. **Processing order** — FALLBACK_ONLY skip resolution/mapping/RATE API (không lãng phí work);
   carrier modes: canonical destination → eligibility → policy → resolution → mapping → RATE →
   classification → service-level decision. Realtime suppression giữ nguyên.

## Supersession

**Supersede MỘT PHẦN DEC-FEATYA2C0W-005**: eligibility "ĐÚNG 2 nguồn" → taxonomy mở rộng (§5);
`LegacyRateStrategy` config axes → superseded bởi RateSourceMode + AddressResolutionPolicy (§6 —
constants deprecated trong migration, không xoá khỏi history). **Supersede MỘT PHẦN** §23/§15.1
v8 wording: resolver seam → dormant extension point (giữ nguyên); AMBIGUOUS residual → fallback
theo AddressResolutionPolicy (không riêng legacy strategy).

**GIỮ NGUYÊN:** DEC-FEATYA2C0W-004/005 (bridge isolation, failure classification, realtime
suppression, snapshot integrity, fallback-as-price-only), outcome 3-state taxonomy, D9
no-auto-pick, v7 Mageplaza composition (collector + method grouping).

## Consequences

- Implementation follow-up (spec-first riêng): CarrierEligibility/Zones/RateSourceMode/
  AddressResolutionPolicy contracts + orchestrator processing-order v9 + auth/config warning seam.
- §14 v5 "ĐÚNG 2 nguồn" được revision v9 thay bằng taxonomy mở rộng — Migration
  LegacyRateStrategy constants deprecated alias transition.
- Defaults Launchpad (proposal §19): DestinationScope=ALL; RateSourceMode=CARRIER_WITH_FALLBACK;
  AddressResolutionPolicy=FALLBACK; fallback matrix theo §35.

## Amendment (2026-09-18 — v10: PICK_PRIMARY promoted to P1, legacy-scheme RATE only)

**Supersede MỘT PHẦN decision #4 của DEC này** ("PICK_PRIMARY = DEFERRED"):

- `AddressResolutionPolicy` P1 = `STRICT` | `FALLBACK` | `PICK_PRIMARY`.
- **PICK_PRIMARY applicability:** chỉ carrier `RATE` operation có `requiredScheme(RATE)` là legacy
  scheme khác runtime scheme (cross-scheme mapping có thể AMBIGUOUS — GHN chứng minh). Carrier
  RATE dùng current canonical identity → PICK_PRIMARY not applicable. KHÔNG leak vào
  CREATE/CANCEL/TRACK. FALLBACK_ONLY không evaluate policy (RATE path skipped).
- **Deterministic selection basis:** curated primary designation trong mapping dataset
  (`is_primary`/`rank` trên mapping edge — curated/import-declared), KHÔNG alphabetical/db-row/
  code-order/fuzzy. `same input + same mapping version → same selected candidate`. Candidates
  thiếu curated designation → không pick (xử lý như unresolved theo policy).
- **Guard:** DATA_INTEGRITY_DEFECT (dataset hư hỏng chưa curated) KHÔNG được auto-select —
  PICK_PRIMARY không phải "ignore data problem" switch.
- **Snapshot provenance additions (minimal):** `selection_policy` (PICK_PRIMARY),
  `selection_reason` (curated designation basis), `candidate_count`. Selected candidate KHÔNG
  lưu trùng (đã là `resolved_pre2025` ward field). `selection_rank` DEFER tới khi mapping data
  có rank metadata.
- **Launchpad default vẫn FALLBACK; PICK_PRIMARY = merchant explicit opt-in** per applicable
  carrier RATE (không global default). `$candidates[0]`/first-sorted/db-row vẫn FORBIDDEN.
- Implementation = controlled task riêng (candidate selector + mapping metadata extension +
  snapshot provenance fields + carrier config integration + tests §28 directive).

## Alternatives considered

- Giữ cả LegacyRateStrategy và RateSourceMode song song: BỊ TỪ CHỐI — duplicate configuration
  axes cho cùng một business decision.
- PICK_PRIMARY implement ngay (disabled): BỊ TỪ CHỐI — không real consumer, D9 risk.
- Rules engine cho eligibility/zones: BỊ TỪ CHỐI — D10 lean.
