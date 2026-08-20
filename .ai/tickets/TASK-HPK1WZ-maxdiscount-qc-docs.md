# TASK-HPK1WZ — QC matrix + OSC e2e + project-context docs

**Legacy ID:** SL-025 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (slice của FEAT-JKZM68 — QC + documentation + release readiness)
**Priority:** High
**Estimate:** ~16–24h QC + ~4h docs
**Mode:** B (QC theo testcase skill; chạm checkout OSC → checklist Tier-2)
**Placement:** QC env sandbox + `.ai/testcases/` + `project-context/` updates
**Risk tier:** Tier 2 (checkout OSC e2e theo project rule: end-to-end checkout QC + payment test)
**Author:** AI draft · **Date:** 2026-08-19 · **Status:** Proposed
**Specification:** SPEC-FEAT-JKZM68 (FULL, VALID) — [spec §15 AC, §16 Test strategy QC, §14 Compatibility](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md)

## Description

**(a) QC manual matrix** (testcase skill sinh test case từ spec AC-001..015 — nhóm chưa cover bởi TASK-4HYX6Y):

- Admin UX: field gating theo action (AC-008), validation ≥ 0, persist round-trip (AC-009), note hiển thị vi/en.
- Storefront totals hiển thị: cart + checkout OSC hiển thị discount đã cap; breakdown per-rule qua totals API/GraphQL khớp cap (address extension attributes đã cap).
- Edge merchant: cap > giá trị giảm (no-op) · cap nhỏ bất thường (1 VND) · rule auto + coupon song song · đổi cap giữa 2 phiên checkout (recompute).

**(b) OSC e2e Tier-2 checklist** (project rule AGENTS §7.1 — mọi checkout flow change): capped coupon qua Mageplaza OSC → place order Mollie test mode + xác nhận payment amount khớp totals đã cap; repeat collectTotals của OSC (reload trang nhiều lần) không drift totals.

**(c) Docs update** (AGENTS §14):

- `project-context/02_BUSINESS_RULES.md` — thêm domain Promotion: BR cap semantics (NULL/0 unlimited, by_percent only, per-rule, product-only, shipping ngoài cap).
- `project-context/04_CUSTOM_MODULES_AND_CODE_AREAS.md` + `09_MAGENTO_MODULE_MAP.md` — 2 modules mới (Placement/collector 310/risk areas).
- `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md` — risk module 3rd party mutate discount sau collector 300.
- Module README/CHANGELOG final (CODING_RULES [WARN]) + `estimation-tracking` cập nhật estimate thực tế.
- Working memory: `CURRENT_STATE`/`NEXT_ACTION` milestone update.

## Acceptance Criteria

- [ ] **AC-1:** Testcase suite QC sinh từ spec AC (testcase skill) được TL duyệt; kết quả chạy pass 100% hoặc bug ticket mở tương ứng.
- [ ] **AC-2:** OSC e2e: capped coupon → totals đúng cap xuyên suốt (cart → checkout → payment → order); payment amount Mollie sandbox == grand total đã cap; không drift sau reload lặp lại.
- [ ] **AC-3:** Regression OSC: cart không có capped rule → hành vi như trước (so sánh baseline trước merge).
- [ ] **AC-4:** Docs: 4 project-context files update đúng rule §14; estimation-tracking ghi thực tế; evidence QC lưu `.ai/evidence/`.
- [ ] **AC-5:** Pre-review checklist AGENTS §8.3 pass + TL code review Tier-2 ký cho cả feature (TASK-3R6X8E..024); Release checklist (deploy skill) sẵn sàng.

## Out of Scope

Deploy production (human action) · promotion analytics · phase 2 items (spec Out of Scope).

## Risks

- OSC/Mageplaza re-collect behavior khác default checkout — nếu thấy drift totals: dừng, escalate Tier-2 (không workaround tại QC).
- Mollie test mode cần credentials sandbox sẵn có — chặn timeline nếu thiếu (flag sớm).

## Related

- Depends: TASK-3R6X8E..024 complete · Spec: [SPEC-FEAT-JKZM68](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md) toàn phần · Decision: [DEC-FEATJKZM68-001](../records/decisions/DEC-FEATJKZM68-001.md) · Feature: [FEAT-JKZM68](../records/features/FEAT-JKZM68.md)
