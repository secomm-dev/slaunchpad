---
id: BUG-N8D8AY
type: bug
title: '[COD Risk] Admin surface defect batch — 404 pages/actions, wrong datetime display, wrong column values, missing Status field (self-test round 1-2)'
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
updated: 2026-09-30
ticket_ref: TASK-YPWH9B
affects_version: Magento 2.4.8-p5
decisions: []
decision_assessment: none-material
components:
  - app/code/Secomm/CodRisk/Controller/Adminhtml
  - app/code/Secomm/CodRisk/view/adminhtml
  - app/code/Secomm/CodRisk/Ui/Component
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

# [SLP][BUG-N8D8AY] [COD Risk] Admin surface defect batch (self-test round 1–2)

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

## Verification

- AC-01…AC-04: PASS (user-verified 2026-09-29/30 sau từng deploy). Regression: grid/form/inspector flows bình thường sau các fix.
