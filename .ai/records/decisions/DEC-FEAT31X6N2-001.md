---
id: DEC-FEAT31X6N2-001
legacy_ids: []
title: 'Commerce Tracking — pixel inject qua GTM container tags (D1); một module Secomm_Tracking duy nhất (D2); server purchase hook = order state transition → processing (D3); GA4 refund defer Phase 1 (D4); consent Phase 1 = Cookie Restriction Mode (D5); GA4 server-side defer (D6)'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-24
created: 2026-08-24
last_verified: 2026-08-24
verified_against_commit: 56d47ade
supersedes: []
superseded_by:
work_items: [FEAT-31X6N2]
---

# Decision Record: Commerce Tracking — architecture decisions (D1–D6)

## Context

FEAT-31X6N2 (Mode A, LC-22) xây tracking layer GA4 + Meta CAPI + TikTok Events API. Spec FULL [SPEC-FEAT-31X6N2 §Open decisions](../../specs/SPEC-FEAT-31X6N2-commerce-tracking.md) nêu 6 quyết định kiến trúc chặn `spec_status: VALID`. Risk categories: PII hashing, checkout OSC, order lifecycle, external API contract.

## Decision (accepted 2026-08-24 — user acting as TL/SA, `/approve` D1–D6 theo khuyến nghị)

| # | Câu hỏi | Khuyến nghị | Alternatives bị loại/khả thi |
|---|---|---|---|
| D1 | Meta/TikTok pixel inject route? | **Qua GTM container tags** — 1 injection point, CSP-friendly, không sửa theme | Snippet trực tiếp theme (loại: CSP + maintainability) |
| D2 | Module boundary? | **Một `Secomm_Tracking` duy nhất** — cohesion cao, 1 outbox/delivery table (YAGNI) | Tách per-vendor module (chỉ khi vendor thứ 3+ xuất hiện) |
| D3 | Server purchase hook? | **Order state transition → processing** — đúng semantics "đã thanh toán", catch Mollie webhook + VNPAY IPN; cần SA confirm state matrix 2.4.8-p5 | (b) success controller (miss webhook-only confirm); (c) `sales_order_save_after` + filter (rủi ro re-fire) |
| D4 | GA4 refund event? | **Defer Phase 1** — cần MP secret, ngoài advertising-measurement core; Meta/TikTok refund đủ AC | Include qua Measurement Protocol (thêm scope + credential) |
| D5 | Consent gate Phase 1? | **Cookie Restriction Mode** (`web/cookie/cookie_restriction` + `user_allowed_save_cookie`) | Deeper Consent Mode v2 signals (defer — CMP riêng out of scope LC-22) |
| D6 | GA4 server-side (GTM Server Container)? | **Defer** — ngoài LC-22 core; Magefan `GoogleTagManagerExtra/ServerTracker` sẵn hook khi cần | Include (thêm infra + cost sTAG) |

## Consequences

- D1+D2 định hình module structure + cách QC setup GTM container (config checklist thay vì code).
- D3 quyết định correctness của purchase fire trên Mollie (async webhook) + VNPAY (IPN) — sai hook = double-fire hoặc miss; AC-010 verify.
- D4/D6 giữ scope LC-22 (advertising measurement); ghi rõ trong Out of Scope spec.
- D5 đủ pháp lý tối thiểu Phase 1 (VN market); CMP integration là work item riêng khi client yêu cầu.

## Affected components

`app/code/Secomm/Tracking/` (toàn bộ module mới); observer config `etc/events.xml`; delivery/outbox schema.

## Related records

- Feature: FEAT-31X6N2 (spec §Open decisions D1–D6, AC-003/AC-004/AC-007/AC-010)
- DECISIONS.md index: DEC-FEAT31X6N2-001