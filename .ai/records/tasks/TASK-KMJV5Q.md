---
id: TASK-KMJV5Q
type: task
title: 'Footer Legal Links — link Terms & Privacy edit được qua system config (SLP-291)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-291
legacy_ids: []
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-10-01
updated: 2026-10-01
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: display-only theme layer + system config value-only — không chạm §12 (payment/checkout/order/DB schema/security); không schema/data patch, chỉ core_config_data
components:
  - app/design/frontend/Secomm/launchpad
  - app/code/Launchpad/CmsContent
source_areas:
  - hyva-theme-frontend
  - cms-content
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: working tree (chưa commit — dev branch anhchong)
last_verified: 2026-10-01
supersedes: []
---

# [SLP][TASK-KMJV5Q] Footer Legal Links — link Terms & Privacy edit được qua system config (SLP-291)

<!-- External ticket: SLP-291 "Section footer Liên kết pháp lý, link điều khoản và link
     quyền riêng tư cần edit được ở admin". Mode B — Embedded Mini-Spec + Plan. -->

## Ticket + AC

**Ticket**: SLP-291 — Footer legal links (Terms & Privacy) edit được ở admin.

**Hiện trạng (verify 10-01)**:
- Copyright bar footer render bởi theme override `Magento_Theme/templates/html/footer/copyright.phtml`
  (TASK-7EYJ4C, SLP-275): copyright text lấy config `design/footer/copyright` (đã edit được),
  còn nav `aria-label="Footer legal"` chứa **2 link hardcode**:
  - `Terms & Conditions` → `$block->getUrl('terms-and-conditions')`
  - `Privacy Policy` → `$block->getUrl('privacy-policy-cookie-restriction-mode')`
  - Labels qua `__()` — vi_VN.csv:990-992 (`Điều khoản và Điều kiện` / `Quyền riêng tư` / `Liên kết pháp lý`).
- DB: page `privacy-policy-cookie-restriction-mode` tồn tại (page_id 4, active);
  `terms-and-conditions` **chưa tồn tại** (placeholder pending — quyết định TASK-7EYJ4C).
- Group "Pháp lý" trong CMS block `footer_links` (PageBuilder) **đã edit được ở admin** — không thuộc
  ticket này.
- `Launchpad_CmsContent` chưa có `etc/adminhtml/system.xml`. Store views: store 1 `default` (VI),
  store 2 `launchpad_en` (EN).

**AC**:
1. Admin có section config mới (Stores > Configuration > Launchpad > Footer) với 4 field:
   Terms label / Terms URL / Privacy label / Privacy URL — scope **store view**.
2. Config trống (default): copyright bar render **nguyên trạng hiện tại** (labels đã dịch + routes cũ)
   — 0 visual change khi chưa cấu hình.
3. Điền label + URL (route path) và save → storefront render giá trị mới ngay (không cần flush
   cache thủ công — observer invalidate `block_html` + `full_page` khi save section).
4. URL dạng full `http(s)://...` → href giữ nguyên (không qua `getUrl()`); route/path → resolve qua
   `getUrl()`.
5. Label/URL escape output (XSS-safe qua `$escaper`).
6. Store view đặt giá trị riêng → render theo store view; store không đặt → inherit từ default.
7. Phần còn lại của footer (newsletter / links / social / trust / accordion mobile) không thay đổi.

## Embedded Mini-Spec

### Goal
2 link pháp lý ở footer bottom bar (Terms & Conditions, Privacy Policy) hiện hardcode route tĩnh —
client không thể đổi label hay đích link. Mục tiêu: cho admin chỉnh label + URL của 2 link này qua
system config, per store view, không đụng code khi cần đổi.

### Expected Behavior
- Section mới **Stores > Configuration > Launchpad > Footer**, group **Legal Links**, 4 field text:
  `terms_label`, `terms_url`, `privacy_label`, `privacy_url` — store view scope, để trống = dùng
  giá trị fallback hiện tại.
- Frontend `copyright.phtml` đọc qua ViewModel `Launchpad\CmsContent\ViewModel\LegalLinks`
  (bind bằng layout argument trên block `copyright`):
  - Label = config value nếu có, fallback `__('Terms & Conditions')` / `__('Privacy Policy')`
    (phrase đã có trong theme CSV vi/en).
  - URL = config value nếu có — full `http(s)://` giữ nguyên, ngược lại resolve qua `getUrl()`;
    fallback route tĩnh hiện tại khi config trống.
- Save config section → observer invalidate `block_html` + `full_page` → thấy thay đổi ngay trên
  storefront.

### Constraints / Rules
- ViewModel qua layout argument (AGENTS §7.2 "ViewModels ... declare in di.xml/layout") — không
  phụ thuộc plumbing `$viewModels` của block core `Magento\Theme\Block\Html\Footer`.
- Fallback config-trống phải giữ render hiện tại byte-đẳng (backward compat — footer đang chạy).
- Escape output bằng `$escaper` (`escapeHtml` label, `escapeUrl` href) — config là user input.
- Không schema/data patch, không CMS content mới, không đụng PageBuilder (tránh strip-proof +
  Hyva CMS JIT issues TASK-0NNZCW).
- Bilingual: label là data per store view — KHÔNG đi qua CSV translation (BR-001 áp cho storefront
  phrases; các phrase fallback hiện có đã trong cả 2 CSV).
- PHP 8.2+ `strict_types`, Magento coding standard, no ObjectManager direct.

### Out of Scope
- Group "Pháp lý" trong PB block `footer_links` (đã edit được qua admin CMS block).
- Tạo placeholder CMS page `terms-and-conditions` (pre-existing gap TASK-7EYJ4C — ticket riêng).
- Copyright text (đã config `design/footer/copyright` từ trước).
- Admin translation vi cho system.xml labels (optional, làm sau nếu client yêu cầu).
- Thêm source model select CMS page (chỉ text field — TL quyết nếu muốn nâng UX sau).

### Acceptance Criteria
Xem **AC-001..AC-007** ở mục "Ticket + AC" — mỗi AC testable qua verify matrix trong plan
(`.ai/plans/TASK-KMJV5Q-implementation-plan.md`).

## Decisions (user chốt 10-01)
- **Dùng system config** (loại: CMS block riêng cho nav 2 link — nặng, dính PB strip/JIT; loại:
  thêm field design theme config — design_config per theme+store phức tạp hơn cần thiết).

## Assumptions (pending TL plan approval)
- Scope chỉ 2 link copyright bar — không đụng group "Pháp lý" PB footer_links.
- Admin edit được **cả label lẫn URL**.
- URL chấp nhận cả route/path lẫn full external URL.
- Không tạo placeholder CMS page trong ticket này.

## Updates
- **10-01 (v1 — DEV DONE, chờ TL review)**: implement + verify theo plan. Files: module
  `etc/adminhtml/system.xml` (tab `launchpad` + section `launchpad_footer` + group `legal_links`,
  4 field store view scope, resource `Magento_Backend::content`), `etc/adminhtml/events.xml` +
  `Observer\InvalidateFooterLegalConfig` (clean `block_html` + `full_page`), `ViewModel\LegalLinks`
  (`ArgumentInterface`, `ScopeConfigInterface` + `UrlInterface`, URL http(s) passthrough / else
  `getUrl()`), theme `copyright.phtml` (require VM qua `$viewModels`, merge fallback, giữ nguyên
  markup/aria/escaper). CHANGELOG 1.3.3 (unreleased) + README section Config. Verify **9/9 PASS**
  (chi tiết + evidence `.ai/evidence/TASK-KMJV5Q/VERIFY.md`): fallback cold byte-identical
  baseline; label/URL mới sau save; route→`getUrl()`, external passthrough; observer invalidate
  warm-FPC (DB-direct + config-cache-fresh simulation); XSS escape; store scope (VM per-store CLI
  emulation, store không set → null); 5 footer section regression; admin Structure API enumerate
  đúng section/group/4 field; validator PASS cho record.
  **Deviation vs plan (verify-bắt, đã update plan)**: bind VM qua `$viewModels->require()` thay
  layout argument — layout argument `xsi:type="object"` là block data, không tự thành template var
  (TemplateEngine chỉ extract `_viewVars` + engine `blockVariables`); VM class phải
  `implements ArgumentInterface`. **Không bump version module**: `setup:db:status` = declarative
  schema pending (working-tree `Secomm_Ahamove db_schema.xml`, pre-existing §12 — không đụng),
  chạy `setup:upgrade` sẽ apply schema của task khác → CHANGELOG đánh `1.3.3 (unreleased)`.
  **Flags TL/QC**: (1) admin UI click-through chưa chạy (cred admin probe `/tmp/hp-admin-cred.txt`
  bị dọn) — cấu trúc + save value đã chứng minh qua Structure API + `config:set` + storefront;
  (2) `terms-and-conditions` page chưa tồn tại trong DB (pre-existing TASK-7EYJ4C) — fallback
  route 404 cho đến khi page được tạo; config cho phép admin trỏ URL đi chỗ khác ngay; (3)
  `config:unset` không có trong core CLI — dọn config trong verify dùng SQL DELETE (test rows tự
  xóa hết, DB sạch).
