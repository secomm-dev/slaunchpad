---
id: BUG-N8D8AY
type: bug
title: '[COD Risk] Admin surface defect batch — self-test rounds 1-3 (404/actions, datetime, columns, UI/UX feedback 01-02/10)'
project_code: SLP
parent:
external_refs:
  tickets: TASK-YPWH9B
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-30
updated: 2026-10-02
ticket_ref: TASK-YPWH9B
affects_version: Magento 2.4.8-p5
decisions: []
decision_assessment: none-material
components:
  - app/code/Secomm/CodRisk/Controller/Adminhtml
  - app/code/Secomm/CodRisk/view/adminhtml
  - app/code/Secomm/CodRisk/Ui/Component
  - app/code/Secomm/CodRisk/view/adminhtml/web
  - app/code/Secomm/CodRisk/Logger
source_areas:
  - admin
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-30
supersedes: []
related: TASK-YPWH9B
---

# [SLP][BUG-N8D8AY] [COD Risk] Admin surface defect batch (self-test round 1–3)

<!-- Consolidated record for the smaller admin-UX defects found and fixed during TASK-YPWH9B self-test. Chi tiết đầy đủ trong app/code/Secomm/CodRisk/CHANGELOG.md (mục Fixed 2026-09-22/30). -->

## Mini Spec

### Goal

Toàn bộ trang/thao tác admin của COD Risk hoạt động đúng: đúng URL, đúng ngày giờ/múi giờ, đúng giá trị cột, đủ field vận hành.

### Expected Behavior & Fix list

1. **404 toàn bộ trang COD Risk + POST actions**: controller đặt thừa tầng `Codrisk\` → dời về `Controller/Adminhtml/{Area}/`; luật map URL thật = `strtolower` + `_`→`\` (`ActionList::get`) → thư mục `OrderRisk`→`Risk`, action `add_to_list`→`addlist`; verify 12/12 URL bằng mô phỏng luật router.
2. **404 mọi POST handler**: base controller khai `HttpGetActionInterface` → FrontController 404 mọi POST. Tách: 7 trang GET `HttpGetActionInterface`, 4 handler `HttpPostActionInterface`, base không khai.
3. **Historical Count hiển thị 0 sai**: pipeline break sớm ở rule ưu tiên cao → OrderRiskView/PhoneInspector tự tính count/spam trực tiếp từ event store cho hiển thị (decision giữ nguyên precedence).
4. **Giờ lệch 7h + format**: raw UTC → `formatRiskDateTime()` (stdlib `formatDateTime` pattern `yy-MM-dd HH:mm`, timezone admin); lưu ý `Template::formatDate()` không nhận pattern.
5. **Order entity id → increment id** (`#000000036`) ở Inspector + cột Order grid Events (class `OrderIncrement`, batch 1 query/page); grid Evaluations bỏ cột Quote/Order (luôn NULL lúc evaluate).
6. **Cột Status rỗng** (Yesno int vs DB string) → source chuỗi `YesNo` (`ActivationStatus` = Active/Inactive) + **pill màu** (`ActiveStatus`, bodyTmpl `ui/grid/cells/html`); form Add/Edit có field Status; row action toggle theo trạng thái dòng.
7. **Cột Website ID → name** (`WebsiteName` + source `Websites`, filter dropdown, 0 = All Websites).
8. **CSS Order View section**: badge inline-style (admin không có `bg-*`), form bọc `admin__fieldset/admin__field`; nút Save/Back đầu form căn phải (pattern `Widget\Form\Container` bị trùng button-ID layout → bỏ container).
9. **Menu group "COD Risk"** trong Sales (node cha không action) + rút gọn title con.

### Constraints / Rules

- URL segment = từ đơn giản viết thường (underscore tách namespace); controller GET/POST khai đúng interface; ngày giờ admin luôn qua formatter có timezone; cột select grid cần options value CHUỖI (DB smallint trả string).

### Out of Scope

Customer-facing checkout message UI (đã wire server-side qua guard, text cấu hình); Audit phone linkage FK (free-text match) — xem Known limitations.

### Acceptance Criteria

- AC-01: 5 trang menu + mọi action mở/save được, không 404.
- AC-02: Thời gian hiển thị đúng giờ VN, format `yy-MM-dd HH:mm`.
- AC-03: Grids hiển thị đúng pill màu + tên website + increment id; Status edit được từ form.
- AC-04: Menu có nhóm COD Risk; user không cấp quyền không thấy.

## Round 3 — UI/UX feedback (01–02/10, sau khi PO tự dùng thử)

Sửa theo feedback sử dụng thực tế (chi tiết đầy đủ: CHANGELOG mục Fixed 01–02/10):

1. **Nút hành động không có skin** — `action secondary/primary scalable` không render trong ngữ cảnh này → `action-default scalable` (+`primary`); toàn bộ nút action bar + Save form đồng bộ primary theo yêu cầu PO.
2. **Accordion form** — mở Record Risk Event / Add to List / Override thì tự đóng 2 form kia (trước: 3 form mở cùng lúc, dễ submit nhầm).
3. **View in Phone Inspector** — nút trên Order View redirect thẳng Inspector kèm phone + website (thay button+setLocation inline).
4. **Reason hiển thị label** mọi nơi (Order View, Inspector, grid Lists/Events qua options `ReasonCodes` + dataType select; Audit Log ghi label thay vì code).
5. **Effective From/To lên datetime đầy đủ**: schema `date`→`timestamp`; calendar chuẩn `mage/calendar` qua `data-mage-init` (có giờ); parser mở rộng; storage timezone-correct (nhập theo timezone website → lưu UTC, grid convert ngược) — end-to-end giống `updated_at`.
6. **Logger riêng** `var/log/codrisk.log` (virtual type CodRiskLogger cho plugin/guard/EvaluationLogger) — trace không còn đổ vào system.log.
7. **Filter dropdown trống** (Status/Type/Website/Decision/Spam): thiếu `<dataType>select</dataType>` trên 6 cột — framework không convert `options class` (bằng chứng diff cms_block_listing).
8. **Cột Status pill màu + cột Website name**; **ListActions đọc nhầm HTML** (ActiveStatus ghi đè is_active → stash `is_active_raw`); conflict check gating theo trạng thái (edit/deactivate luôn cho phép) + `setActive` cũng check; website 0 overlap.
9. **Phone Inspector**: layout grid cố định (message không làm rớt hàng), nút align input, input chỉ nhận số + `+` đầu chuỗi; normalizer siết theo quy hoạch băng tần VN (mobile 9 số đầu 3/5/7/8/9, cố định 9-10 số đầu 2) + JS mirror đồng bộ.
10. **Refactor chuẩn Magento**: CSS → `view/adminhtml/web/css/codrisk-admin.css` (include qua layout default head); JS → `web/js/order-view.js` + `inspector-search.js` (x-magento-init / data-mage-init); 0 inline `<script>`/`style=` trong admin templates.

**Orphan files (chờ PO xác nhận xóa — sản phẩm session song song):** `Ui/Component/Listing/Columns/EffectiveDate.php`, `Ui/Component/Listing/Columns/ReasonLabel.php` — không còn được reference trong XML.

## Verification

- AC-01…AC-04: PASS (user-verified 2026-09-29/30 sau từng deploy). Regression: grid/form/inspector flows bình thường sau các fix.
- Round 3 (01–02/10): PASS — user-verified (accordion, datetime grid/DB, logger riêng, filter options, normalize số dư, layout inspector).
