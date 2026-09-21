---
id: TASK-5XDG1P
type: task
title: 'Phase E-B — Local canonical shipping-address orchestration trong Secomm_ShippingCore (manager + request cache)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-5XDG1P — runtime flow + cache + non-VN bypass theo approved Phase E-B directive (SPIKE-W273TB basis); TL review spec text chạy cùng code pre-review
specification_ref: ../../specs/SPEC-TASK-5XDG1P-shippingcore-local-address-orchestration.md
risk: medium                  # additive manager + 1 exception + 1 DI preference; 0 carrier code, 0 schema; manager chưa có caller runtime
status: in_progress
priority: high
decision_assessment: none-material   # thực thi DEC-FEATYA2C0W-004 + SPIKE-W273TB §4/§7 (đã approved); contract adjustment nhỏ nhất (non-VN bypass exception) là REPORT theo directive, không phải DEC mới
decisions: [DEC-FEATYA2C0W-004]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/ShippingCore/
changes_project_state: true
created: 2026-09-08
updated: 2026-09-08
owner: [dev]
related_tickets: [TASK-AQT7V3, SPIKE-W273TB]
---

# [SLP][FEAT-YA2C0W][TASK-5XDG1P] Phase E-B — Local canonical shipping-address orchestration trong Secomm_ShippingCore (manager + request cache)

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-TASK-5XDG1P-shippingcore-local-address-orchestration.md, FULL)*

### Goal

Implement concrete local canonical orchestration: `ShippingAddressResolutionManager` validate
context → request-scoped cache lookup (key `sourceScheme|sourceUnitCode|targetScheme`) → delegate
canonical graph resolution 100% cho `Secomm_VietNamAddress` (`VnAdminAddressResolverInterface`)
→ convert 1-1 sang `ResolvedShippingAddress` → cache → return. LOCAL ONLY — không external
resolver, không carrier integration, không persistence.

### Expected Behavior

1. 4-state REUSE nguyên vẹn từ resolver: EXACT (same-scheme, unit tồn tại — không cần mapping
   edge) / MAPPED (1 candidate) → unitCode + isResolved true; AMBIGUOUS (>1) → unitCode null bắt
   buộc + toàn bộ candidates giữ thứ tự, không bao giờ auto-select; UNMAPPED (0) → unitCode null +
   candidates rỗng. Cardinality authoritative — không logic MERGED_INTO/SPLIT_INTO (DEC-004 D9).
2. Non-VN (`countryId` non-null ≠ 'VN'): manager BYPASS — không gọi resolver, ném
   `Model\Address\Exception\UnsupportedDestinationException` (extends `LocalizedException`,
   mirror precedent `GhnLocationMappingException`). KHÔNG status thứ 5, KHÔNG abuse UNMAPPED —
   contract adjustment nhỏ nhất (docblock-only trên manager interface) REPORT cho TL (directive §6).
3. Invalid context: missing/invalid `sourceScheme`/`sourceUnitCode` trên destination VN → UNMAPPED
   không gọi resolver (explicit unresolved theo contract, được cache). Unknown scheme code →
   `LocalizedException` từ resolver propagate nguyên văn (config fault). Impossible canonical
   response → `LogicException`.
4. Cache: in-memory array trên manager (shared DI instance = request scope — SPIKE-W273TB §7);
   key `sourceScheme|sourceUnitCode|targetScheme` byte-exact (KHÔNG street/candidates, KHÔNG bất
   kỳ recipient identity nào; countryId không cần vì non-VN ném trước khi chạm cache); value
   `ResolvedShippingAddress` VO immutable; AMBIGUOUS/UNMAPPED/missing-identity cũng cached —
   không recompute trong 1 request.
5. Capability: chỉ đọc `getRequiredScheme()`; `supportsTextualFallback()` không được execute
   (directive §12). Context `getStreetText()`/`getCandidateCodes()` không consume;
   `getReceiverText()` ĐÃ XÓA khỏi contract theo TL review — recipient PII, không cần cho
   canonical resolution, external disambiguation chỉ dùng address-related text (§6 spec).

### Constraints / Rules

- Resolution/cardinality/same-scheme semantics là CỦA `Secomm_VietNamAddress` — ShippingCore
  orchestrate-only, KHÔNG duplicate resolver logic, KHÔNG constant scheme/status song song (D2).
- KHÔNG: external resolver invocation (pool đứng ngoài manager), provider selection config,
  textual fallback execution, carrier code (§12 directive), provider IDs, persistence, origin
  resolution, fuzzy/name normalization, Redis/Magento-cache-frontend/session, logger injection
  (expected states không log; unexpected = exception tự mang ngữ cảnh — directive §17/§18).
- DI: 1 preference trong `ShippingCore/etc/di.xml` (interface → manager); KHÔNG plugin/preference
  ngoài ShippingCore; pool giữ nguyên array rỗng.
- Architecture decisions mới: KHÔNG (non-VN bypass exception là contract adjustment nhỏ nhất được
  directive §6 chỉ định report-before-implement; documented spec §4.2 — TL review cùng pre-review).

### Out of Scope

External resolution · VietMap/Google · textual fallback · carrier migration (GHN/GHTK/Ahamove/
GiaoHangNhanh/GhnAddressMapper) · provider mapping/IDs · quote/order persistence · origin
canonicalization (OD-1) · admin config · geocoding · name-based matching · DB schema · i18n.

### Acceptance Criteria

AC-1..AC-7 của SPEC-TASK-5XDG1P (nguyên văn — tóm tắt): delegate 100% qua resolver contract (0
logic mapping tự chế) · 4-state + AMBIGUOUS không auto-select · non-VN bypass exception + resolver
0 lần · cache hit/separation/unresolved-cache đúng key canonical · missing identity → UNMAPPED +
unknown scheme propagate + impossible → LogicException · DI preference + compile + phpunit Secomm
(includes manager↔resolver THẬT integration-level test) + validator pass · README/CHANGELOG +
working memory + FEAT ticket_ref sync.

## Plan

`../plans/TASK-5XDG1P-implementation-plan.md`

## Review rounds

- **r1 (2026-09-08, TL review E-B)** — API hygiene/PII cleanup trước contract freeze:
  `ShippingAddressResolutionContextInterface::getReceiverText()` + concrete property/param/getter
  XÓA (recipient PII; audit 0 production consumer, 0 carrier reference). KHÔNG field recipient
  identity thay thế; `streetText`/`candidateCodes` giữ nguyên cho external disambiguation;
  manager orchestration + cache + 4-state KHÔNG đổi. Spec r1 §6; evidence
  `../evidence/TASK-5XDG1P/review-cleanup-receiver-text.md`.
