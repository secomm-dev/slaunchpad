# TASK-WY5JRN Implementation Plan — QC e2e matrix + debug admin grid + project-context docs

| Field | Value |
|---|---|
| Specification | tickets/TASK-WY5JRN-tracking-qc-docs.md (embedded Mini Spec) · canonical parent specs/SPEC-FEAT-31X6N2-commerce-tracking.md §10, §13, §14 |
| Mode | B · Tier 1 |

> **Status:** Retro-canonical — distilled 2026-08-27 từ work đã dev-complete (grids chạy thật sau chuỗi fix). QC execution theo matrix còn dở.

---

## PART 1 — ANALYSIS

| Quyết định | Kết luận | Nguồn |
|---|---|---|
| Grid không dùng PHP class | **VirtualType XML** dựa trên framework `UiComponent\DataProvider\SearchResult` (pattern core module-search) — theo yêu cầu user "ko muốn dùng GridCollection" | user 2026-08-27 |
| collections array PHẢI global di.xml | Area-scoped declaration **thay thế** (không merge) array của core → làm vỡ Sales → Order (`Not registered handle sales_order_grid_data_source`) — đã gặp thật | runtime lesson |
| Menu + routes riêng | `menu.xml` dưới Secomm CORE; **`etc/adminhtml/routes.xml` bắt buộc** cho frontName `secomm_tracking` — thiếu = 404 mọi action | runtime lesson |
| ui_component XSD | Top-level `<settings>` **không cho phép** `<label>` — element không tồn tại trong XSD | runtime lesson |
| Read-only grids | Không row action — resend/retry thuộc outbox cron | spec §10 |
| Payload không render trong grid | PII-safe ngay cả trong admin view | §10 |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | Controllers read-only | `Controller/Adminhtml/{Delivery,Event}/Index.php` | ✅ |
| 2 | Menu + ACL + routes | `etc/adminhtml/{menu,routes}.xml` (ACL trong scaffold) | ✅ |
| 3 | Layout + ui_components 2 grid | `view/adminhtml/layout/*`, `ui_component/*.xml` | ✅ runtime |
| 4 | Data source virtualTypes | `etc/di.xml` (global) | ✅ |
| 5 | Options classes | `Ui/Component/Listing/Column/{VendorOptions,StatusOptions}.php` | ✅ (VendorOptions TikTok-only sau DEC-002) |
| 6 | QC matrix + GTM checklist + context docs + estimation | `.ai/evidence/FEAT-31X6N2/*`, project-context 03/04/05/06 | ✅ docs |
| 7 | QC execution AC-001..012 | — | ⏳ partial (TikTok server xanh; browser/refund/phpunit pending; Meta chuyển qua Magefan Extra) |

## Scope note DEC-FEAT31X6N2-002

AC-002/AC-003/AC-005 phần Meta giờ verify qua **Magefan FacebookPixelExtra** (chưa cài tại 2026-08-27) — ngoài QC matrix của module.
