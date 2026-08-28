# TASK-WY5JRN — QC e2e matrix + debug admin grid + project-context docs

**Type:** Task (slice của FEAT-31X6N2 — QC + docs + release prep)
**Priority:** Medium
**Estimate:** ~12h
**Mode:** B (QC execution + docs; grid UI là read-only admin component)
**Placement:** `app/code/Secomm/Tracking/view/adminhtml/` (grid UI nếu include) + `.ai/project-context/` updates
**Risk tier:** Tier 1
**Author:** AI draft · **Date:** 2026-08-24 · **Status:** Dev complete (grids + docs + QC matrix shipped) — chờ QC execution theo matrix + context diff human review
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-31X6N2](../specs/SPEC-FEAT-31X6N2-commerce-tracking.md) §10, §13, §14 · Plan: [TASK-WY5JRN plan](../plans/TASK-WY5JRN-implementation-plan.md)

## Approach

> Retro-canonical 2026-08-27 — distilled từ work đã dev-complete + runtime fixes.

1. **Admin grids qua framework virtualType** (không PHP grid class — theo yêu cầu user): `etc/di.xml` global khai báo 2 virtualType dựa trên `UiComponent\DataProvider\SearchResult` + map vào `CollectionFactory` (pattern core module-search). Bài học đắt giá: collections array **phải nằm global di.xml** — khai báo area-scoped thay thế array của core module-sales và vỡ Sales → Order grid (`Not registered handle sales_order_grid_data_source`).
2. **Layout + menu:** controllers Adminhtml `delivery/index` + `event/index` (read-only, ADMIN_RESOURCE riêng) + `menu.xml` dưới Secomm CORE + `routes.xml` frontName `secomm_tracking` (thiếu file này = 404 menu) + ui_component 2 grid (column set tối giản, payload không render — PII-safe).
3. **Runtime fixes:** `<label>` không hợp lệ trong top-level `<settings>` của ui_component (XSD không có element này); GridCollection class PHP ban đầu không thỏa `SearchResultInterface` → đổi hẳn sang virtualType.
4. **QC matrix + docs:** `.ai/evidence/FEAT-31X6N2/qc-matrix.md` theo AC-001..012; project-context 03/04/05/06 append integration/module/risk; GTM checklist trong README module; estimation CSV row.
5. **Meta removal (DEC-002) ảnh hưởng task này:** VendorOptions chỉ còn TikTok; AC-002/AC-003/AC-005 phần Meta chuyển verify qua Magefan Extra (ngoài matrix của module).

**QC pending:** chạy matrix còn lại theo CURRENT_STATE (browser pipeline, refund, phpunit, Meta-via-Magefan-Extra).

## Description

- **QC e2e matrix** theo spec §13 AC-001..012: Hyva storefront walk-through, OSC e2e Mollie test + VNPAY sandbox, Meta Test Events + TikTok Event Debug reconcile, consent flows, retry/failure simulation, perf sanity (checkout TTFB trước/sau).
- **Admin delivery grid** (Secomm CORE menu): list `secomm_tracking_delivery` — filter event_id/order/vendor/status, view masked payload — công cụ reconcile cho QC + marketer (AC-008). UI component chuẩn Magento, no logic.
- **GTM container config checklist doc** (nếu chưa hoàn tất ở TASK-E0NG8Z): variables/tags/triggers cho GA4 + Meta Pixel + TikTok Pixel đọc `launchpad_event`.
- **project-context updates** (§14 Documentation Update Rules): `03_ARCHITECTURE_AND_INTEGRATIONS.md` (+2 integrations Meta CAPI, TikTok Events API), `05_API_CONTRACTS.md` (endpoints spec §6), `06_KNOWN_CONSTRAINTS_AND_RISKS.md` (token rotation risk, Magefan upgrade pin), `04_CUSTOM_MODULES_AND_CODE_AREAS.md` (+Secomm_Tracking), memory CURRENT_STATE/NEXT_TASK compact.
- Estimation tracking row append.

## Mini Spec

### Goal

Feature FEAT-31X6N2 hoàn tất Quality Gates (Test Gate + docs) — QC reconcile được mà không cần ssh DB, project-context phản ánh kiến trúc mới.

### Expected Behavior

- Full AC-001..012 pass với evidence ghi vào `.ai/evidence/FEAT-31X6N2/` (screenshot Meta Test Events dedup, TikTok debug, delivery grid, console sạch, TTFB numbers).
- Admin grid: filter theo event_id của order test → thấy sent/failed/skipped từng vendor.
- Docs PR-diff surfaces cho human review (AI generate, human commit — §14).

### Constraints / Rules

- Grid read-only (không action mass delete/resend ở Phase 1 — resend thuộc outbox retry tự động).
- Evidence không chứa plain PII (mask trước khi chụp/ghi).
- QC dùng Mollie test mode + VNPAY sandbox + Meta Test Event code + TikTok debug — không hit production audiences.

### Out of Scope

Actual production GTM container deployment · campaign setup · BI warehouse (feature out of scope) · Estimation của tasks khác.

### Acceptance Criteria

- [ ] **AC-1:** QC matrix chạy đủ 12 AC với evidence artifacts filed (`.ai/evidence/FEAT-31X6N2/`).
- [ ] **AC-2:** Admin grid filter theo event_id order test hoạt động; hiển thị đúng status từng vendor.
- [ ] **AC-3:** project-context diff (03/04/05/06 + memory) surface cho human review — không tự commit.
- [ ] **AC-4:** Estimation-tracking row append cho FEAT-31X6N2 tasks.

**Parent:** [FEAT-31X6N2](../records/features/FEAT-31X6N2.md) · **Spec:** SPEC-FEAT-31X6N2 §10/§13/§14