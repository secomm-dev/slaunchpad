---
id: SPIKE-YH439T
type: spike
title: 'ShippingCore carrier runtime handoff + failure semantics — audit handoff thật + đề xuất handoff model Option B-minimal (pre-implementation)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: MINI
spec_status: VALID            # user-directed architecture task 2026-09-08 (scope + 19 mục directive + DoD trong request); TL/SA review report pending
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-08
updated: 2026-09-08
decisions: [DEC-FEATYA2C0W-004]
decision_assessment: none-material   # audit VALIDATE DEC-004 trên code thật; handoff model Option B-minimal + reason vocabulary là ĐỀ XUẤT (report §4/§9, OD-1..OD-6) — TL/SA approve trước khi implement
components:
  - CMP-SHIPPING
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/GiaoHangNhanh/
  - app/code/Secomm/GhnAddressMapper/
  - app/code/Secomm/Ghtk/
  - app/code/Secomm/Ahamove/
  - app/code/Secomm/VietNamAddress/
changes_project_state: true
changes_architecture: false   # analysis/design only — 0 production code
changes_integration: false
changes_known_limitations: true
last_verified: 2026-09-08
supersedes: []
---

# [SLP][FEAT-YA2C0W][SPIKE-YH439T] ShippingCore carrier runtime handoff + failure semantics — audit handoff thật + đề xuất handoff model Option B-minimal (pre-implementation)

## Mini Spec

### Goal

Định nghĩa standard runtime handoff ShippingCore → mọi Secomm-built VN carrier (resolved handoff,
non-VN applicability, AMBIGUOUS/UNMAPPED, external resolver flow, textual fallback, rate-unavailable,
diagnostic reason) — **analysis/design only, 0 production code**, báo cáo 15 mục theo directive.

### Expected Behavior

1. Audit ShippingCore E-A/E-B/r1 thật (không dựa SPIKE cũ) + 4 carrier module thật → báo cáo
   hiện trạng handoff + gap duplication (mục 1–3 report).
2. Chọn 1 handoff model (A raw / B handoff object / C adapter) — đề nghị **Option B-minimal**
   (`DestinationContextBuilder` + `CarrierAddressHandoff` DTO ≤5 members + 1 service method),
   giữ nguyên toàn bộ contract E-A/E-B.
3. Định nghĩa ownership: non-VN exception → dịch tại handoff service (không leak carrier);
   AMBIGUOUS/UNMAPPED flow ShippingCore-owned (external → fallback-eligible → unavailable);
   textual fallback = ShippingCore quyết ALLOW / carrier thực thi payload.
4. Tách bạch canonical failure (stage 1) vs provider mapping failure (stage 2) — vocabulary
   failure-reason 6 mã đề xuất, không taxonomy lớn.
5. Chuẩn rate-failure storefront (theo pattern GHTK: hide + log luôn, không debug-gated, không
   fake rate) + operational fail-closed; capability contract verdict; cache implications 2 layer;
   engineering rule cho carrier tương lai; phases E-C0→E-F; open decisions OD-1..OD-6.

### Constraints / Rules

- KHÔNG production code: không DTO/base-class mới, không external orchestration, không textual
  fallback, không carrier change, không rate abstraction, không OrderOperations, không DB/config.
- Bảo toàn historical research (SPIKE-W273TB, SPIKE-9Z231Q giữ nguyên); KHÔNG rewrite finding cũ.
- Không DEC mới — mọi kiến nghị là ĐỀ XUẤT chờ TL/SA (OD-1..OD-6 report §20).
- Reference: TASK-AQT7V3 + TASK-5XDG1P + SPIKE-W273TB + DEC-FEATYA2C0W-004.

### Out of Scope

Implement handoff · carrier migration (GHN/GHTK/Ahamove) · external resolver/selection config ·
textual fallback execution · OrderOperations integration · DB/schema/config · provider mapping
migration key · quote/order persistence snapshot (OD-2 cũ).

### Acceptance Criteria

AC-1 gap handoff document được (context builder thiếu + 0-consumer bridge verified) · AC-2 1
handoff model chọn được · AC-3 non-VN/AMBIGUOUS/UNMAPPED semantics định nghĩa xong · AC-4
external + textual fallback ownership xong · AC-5 provider vs canonical failure tách bạch ·
AC-6 rate + operational failure semantics xong · AC-7 capability verdict · AC-8 engineering rule
· AC-9 phases nhỏ · AC-10 0 production code (git diff ShippingCore/carrier = 0).

## Plan

Embedded Mini-Spec (analysis-only; report là deliverable):
`../../research/SPIKE-YH439T-shippingcore-carrier-runtime-handoff.md`
