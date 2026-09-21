---
id: TASK-7AJ3K8
type: task
title: 'Phase E-C1/GHTK — alignment Secomm_Ghtk lên target architecture ShippingCore (address pipeline + API profile + HTTP primitive + tracking reconciliation + origin semantics)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-7AJ3K8 (r1 amendment: TEXT_NATIVE canonical-first + override-only theo directive r1 2026-09-10); TL review spec text chạy cùng code pre-review
specification_ref: ../../specs/SPEC-TASK-7AJ3K8-ghtk-shippingcore-alignment.md
risk: high                    # shipping carrier runtime behavior + shared contracts; Tier-2 review bắt buộc trước merge
status: dev-complete          # r1 implemented 2026-09-10 (TEXT_NATIVE correction; scoped suite pass; evidence .ai/evidence/TASK-7AJ3K8/); setup:upgrade + E2E sandbox PENDING; chờ TL Tier-2 code review + QC
priority: high
decision_assessment: material # r0: DEC-TASK7AJ3K8-001 (4 amendments); r1: DEC-TASK7AJ3K8-002 (TEXT_NATIVE canonical-first; override-only table canonical-keyed; bỏ textual fallback — supersede MỘT PHẦN -001 Decision 2+4)
decisions: [DEC-FEATYA2C0W-004, DEC-TASK7AJ3K8-001, DEC-TASK7AJ3K8-002]
components:
  - CMP-SHIPPING
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/Ghtk/
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/VietNamAddress/
changes_project_state: true
created: 2026-09-10
updated: 2026-09-10
owner: [dev]
related_tickets: [TASK-AQT7V3, TASK-5XDG1P, TASK-T78YH6, TASK-Q4B98P, TASK-NAT3YV]
---

# [SLP][FEAT-YA2C0W][TASK-7AJ3K8] Phase E-C1/GHTK — alignment Secomm_Ghtk lên target architecture ShippingCore

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-TASK-7AJ3K8-ghtk-shippingcore-alignment.md, FULL)*

### Goal

Chuyển `Secomm_Ghtk` từ address-resolution tự giữ (WardIdBridge raw-SQL `directory_region_city` +
first-match, BestEffortViVnResolver raw-SQL locale tables, DestinationAddressResolver combine 4
trách nhiệm) sang pipeline chuẩn DEC-FEATYA2C0W-004: runtime (region_id, ward-name) → VietNamAddress
name-based bridge → ShippingCore canonical orchestration (E-B manager + E-C0 handoff) → GHTK Stage-2
adapter (alias map + canonical name) → `GhtkAddress`. Kèm 3 shared primitive P1 dùng chung cho
carrier sau: CarrierApiProfile contract, HTTP/retry/error-taxonomy primitive, TrackingReconciliation
service; và dọn VN semantics khỏi `ShippingOriginProvider`.

### Expected Behavior

1. GHTK destination/pickup resolution: canonical-first qua handoff; AMBIGUOUS name match → KHÔNG
   rate (thay cho first-match cũ — intentional change theo directive); alias miss → canonical
   `name_vi` fallback; UNMAPPED + textual-fallback-eligible → best-effort text (province name_vi +
   submitted ward name) đúng semantics graceful cũ.
2. `secomm_ghtk_address_map`: giữ nguyên schema/runtime key; responsibility giới hạn = GHTK name
   alias map (KHÔNG là source of truth VN identity); lookup chỉ qua reverse bridge runtime ids.
3. `GhtkApiClient`: transport qua shared `CarrierHttpClientInterface` + `RetryExecutor`; GHTK vẫn
   tự giữ auth headers/payload/endpoint (qua `GhtkApiProfile`). Fee GET retry (NETWORK/SERVER_ERROR,
   config `retry_max`); create-order SINGLE attempt (không đổi).
4. Tracking reconciliation: orchestration (state query, terminal exclude, stale filter, batch,
   per-item continue) chuyển vào `ShippingCore\TrackingReconciliationService`; GHTK chỉ còn
   `GhtkTrackingFetcher` (API call + status map) + config gate trong cron shell.
5. Backward compat: legacy `carriers/ghtk/pick_*` origin chain giữ nguyên 100%.

### Constraints / Rules

- KHÔNG rewrite GHTK; giữ nguyên: GhtkConfig, FeeRequest/ResponseMapper, RateComposer, OrderRequest/
  ResponseMapper, CodAmountResolver, native label flow, webhook path, GhtkStatusMapper, pickup gate.
- KHÔNG đưa carrier logic vào ShippingCore; ShippingCore KHÔNG query raw directory datasets.
- Carrier KHÔNG query directory tables; mọi identity resolution qua VietNamAddress contracts.
- AMBIGUOUS không bao giờ auto-pick (D9).
- Không commit/push; Tier-2 review trước merge.

### Out of Scope

GHN/Ahamove migration (chỉ reuse-readiness) · alias-table scheme-aware re-key (D6 — DB migration,
follow-up riêng) · external disambiguation providers · service-level/fallback pricing (E-SL1/2) ·
system.xml config mới · webhook payload contract.

### Acceptance Criteria

AC-1..AC-12 của SPEC-TASK-7AJ3K8 (tóm tắt): 0 raw-SQL directory trong Ghtk · resolution pipeline
qua handoff duy nhất · ambiguous → hide (test) · alias/canonical-name fallback đúng thứ tự · legacy
pickup config behavior không đổi · fee retry + create-order single-attempt không đổi · taxonomy
6 category · tracking orchestration shared + GHTK fetcher tách · webhook vẫn qua shared processor ·
phpunit pass · validator 0 new finding · grep gates sạch.

## Plan

`../plans/TASK-7AJ3K8-implementation-plan.md`
