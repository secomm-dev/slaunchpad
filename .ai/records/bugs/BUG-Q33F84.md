---
id: BUG-Q33F84
type: bug
title: 'Mageplaza_RMA footer links không hiển thị trên Hyvä footer — vendor blocks neo vào footer-static-links bị theme remove (SLP-275)'
project_code: SLP
parent:
external_refs:
  tickets: []
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-10-05
updated: 2026-10-05
ticket_ref:
affects_version: Magento 2.4.8-p5 + Mageplaza_RMA 4.1.1 (mới install)
decisions: []
decision_assessment: theme-owned additive layout fix; không chạm generic risk category; pattern user chọn (move + referenceBlock) — trade-off vendor-name coupling đã ghi trong layout comment
components:
  - app/design/frontend/Secomm/launchpad/Mageplaza_RMA/layout/hyva_default.xml
  - app/design/frontend/Secomm/launchpad/Mageplaza_RMA/templates/footer/link.phtml
  - app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/footer.phtml
  - app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv
  - app/design/frontend/Secomm/launchpad/i18n/en_US.csv
source_areas:
  - frontend/theme
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-10-05
supersedes: []
related: Mageplaza_RMA install (context update 2026-10-05)
---

# [SLP][BUG-Q33F84] Mageplaza_RMA footer links không hiển thị trên Hyvä footer

## Mini Spec

### Goal

Link "RMA Request" + "RMA Terms And Conditions" hiển thị ở footer trên theme
`Secomm/launchpad` khi config tương ứng bật, giữ nguyên toàn bộ gating config của vendor
(`mprma/general/enabled`, `location`, `policy`, `policy_location`, `enabled_guest`).

### Expected Behavior

- Vendor `Mageplaza_RMA/view/frontend/layout/hyva_default.xml` khai 2 block
  `mprma_footer_link` + `mprma_policy_footer_link` — nhưng dưới block
  `footer-static-links`, mà theme đã remove (SLP-275 footer CMS) → không render.
- Sau fix: 2 block được re-home vào `footer-content`, render bằng template theme
  (`<ul><li><a>` khớp typography footer), children được gọi tường minh trong
  `Magento_Theme::html/footer.phtml`.

### Constraints / Rules

- KHÔNG sửa module vendor (third-party — extend qua theme layout/template only).
- KHÔNG khôi phục `footer-static-links` (template vendor hard-code footer English sẽ phá footer CMS).
- Fix additive, theme-owned; label translate qua theme i18n (BR-001: cả vi_VN + en_US).

### Out of Scope

- Header top link (vendor gắn vào `header.customer.logged.in.links` — block này vẫn sống, không dính bug).
- i18n 413 chuỗi còn lại của module (gap đã ghi `06_KNOWN_CONSTRAINTS_AND_RISKS.md`).
- RMA status labels DB + reasons config (data config, task riêng khi bật module cho storefront).

### Acceptance Criteria

- AC-1: logged-in + `location` chứa Footer → "Yêu cầu trả hàng" (vi_VN) hiện cuối section links, href `/mprma/request/form`.
- AC-2: `policy` đã chọn trang + `policy_location` chứa Footer → "Điều khoản và điều kiện đổi trả" hiện cạnh link trên (không guest check — hành vi vendor).
- AC-3: logged-out + `enabled_guest=0` → link Request ẩn, không lỗi; Policy vẫn hiện.
- AC-4: `mprma/general/enabled=0` → footer bình thường, không exception (ifconfig + move no-op).
- AC-5: Accordion mobile footer không vỡ; vi_VN/en_US đúng label.

## Root Cause

Theme remove block mà vendor dùng làm neo:

1. `Mageplaza_RMA/view/frontend/layout/hyva_default.xml:43-58` — `referenceBlock name="footer-static-links"`
   (setTemplate + 2 block con).
2. `Secomm/launchpad/Magento_Theme/layout/default.xml:54` — `<referenceBlock name="footer-static-links"
   remove="true"/>` (TASK-7EYJ4C / SLP-275: footer chuyển sang 4 CMS blocks). Subtree bị remove →
   2 block vendor chết theo parent → link không bao giờ render. Không phải lỗi config.

## Fix (phương án user chọn: move + referenceBlock)

- `Secomm/launchpad/Mageplaza_RMA/layout/hyva_default.xml` (theme, mới):
  `<move element="mprma_footer_link|mprma_policy_footer_link" destination="footer-content"/>`
  + `referenceBlock` setTemplate → `Mageplaza_RMA::footer/link.phtml`.
- Hoạt động vì `GeneratorPool::buildStructure()` xử lý scheduled moves TRƯỚC scheduled removes
  (`vendor/magento/framework/View/Layout/GeneratorPool.php:134-139`) — block tách khỏi parent
  trước khi parent bị xóa. Handle order không ảnh hưởng (move/remove chạy lúc generation);
  Hyvä append handle `hyva_` SAU handle gốc (`AddLayoutHandles` dùng `addHandle`).
- `Magento_Theme/templates/html/footer.phtml:35-36` — +2 dòng `getChildHtml()` tường minh.
- `Mageplaza_RMA/templates/footer/link.phtml` (theme, mới) — markup thay `<li class="nav item">`
  của `Html\Link\Current`; dùng chung cho cả 2 block (policy block override `getHref()`).
- i18n theme: `"RMA Request","Yêu cầu trả hàng"` + `"RMA Terms And Conditions","Điều khoản và
  điều kiện đổi trả"` (vi + en).

## Verification

- Root cause + cơ chế move-before-remove: đã verify qua vendor source (file:line ở trên).
- AC-1..AC-5: **PENDING** — chờ user chạy `cache:flush` + tailwind rebuild + check config
  (`mprma/general/location` chứa Footer; `mprma/general/policy` + `policy_location` cho link
  Policy; `enabled_guest=1` nếu test logged-out). Xem `.ai/evidence/BUG-Q33F84/root-cause-and-fix.md`.
- Trade-off đã ghi trong layout comment: phụ thuộc tên block vendor — rename khi upgrade = move
  no-op silent (link biến mất; QC bằng sự vắng mặt của link).

## Evidence

- `.ai/evidence/BUG-Q33F84/root-cause-and-fix.md`