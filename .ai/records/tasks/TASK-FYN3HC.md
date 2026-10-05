---
id: TASK-FYN3HC
type: task
title: 'PDP + product card old price typography — đúng Figma Body/Body 3 (SLP-296)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-296
legacy_ids: []
mode: C
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
decision_assessment: display-only theme CSS — không chạm §12 (payment/checkout/order/DB
  schema/security); không markup, không JS, không data
components:
  - app/design/frontend/Secomm/launchpad
source_areas:
  - hyva-theme-frontend
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: working tree (pre-commit; code task khác để nguyên trạng)
last_verified: 2026-10-01
supersedes: []
---

# [SLP][TASK-FYN3HC] PDP + product card old price typography — đúng Figma Body/Body 3 (SLP-296)

<!-- External ticket: SLP-296 "Fix product old price fontsize" — kèm 2 screenshot:
     (1) Figma Typography panel chỉ old price = Body/Body 3 (14px/20px, 400, #99A1AF,
     line-through); (2) storefront old price đang to/đen. Scope chốt với dev (10-01):
     gồm cả home page product card. Mode C. -->

## Ticket + AC

**Ticket**: SLP-296 — Fix product old price fontsize (PDP + home page product card).

**Hiện trạng (verify 10-01)**:
- Old price chạy qua CSS var cascade `--price-font-size`; rule unlayered
  `product-price.css` `.price-box .price { font-size: var(--price-font-size, text-base) }`
  thắng mọi rule layered (Tailwind v4: unlayered > layered bất kể specificity).
- PDP (`page-catalog.css` `.product-info-main .old-price`): biến `1em` = 16px, màu ink
  (đen), line-height 24px — sai toàn bộ 3 giá trị so với Figma.
- Card `.hp-card` (pipeline `product_list_item` — PLP/search/PB carousel/flash-sale rail):
  biến `0.875em` resolve trên context 14px của card = **12.25px**; layered rule
  `card.css` (`@apply text-sm …`) chỉ thắng color/line-height/weight, thua font-size.
- Weight token generated bị lỗi: `--ds-type-body-3-font-weight: 400px` (malformed) —
  không thể dùng token cho weight. Không có color token cho `#99a1af` (card.css đã dùng
  literal từ trước).

**AC**:
1. PDP old price = 14px / 20px / 400 / `#99a1af` / line-through (Figma Body/Body 3).
2. Home page product card (PB carousel + flash-sale rail) old price = cùng bộ giá trị.
3. PLP/search card old price = cùng bộ giá trị (cùng pipeline, cùng selector fix).
4. Giá chính PDP + mọi phần khác của card/PDP không đổi (regression-free).

## Embedded Mini-Spec

### Goal
Old price (giá regular khi có special price) trên PDP và product card render sai
typography so với Figma LAUNCHPAD-CORE. Mục tiêu: old price = Body/Body 3 trên cả 3
surface (PDP, home card, PLP card) bằng cách khắc phục cascade layers chứ không chỉ
đổi giá trị font-size.

### Expected Behavior
- `page-catalog.css` `.product-info-main & .old-price`: biến `--price-font-size:
  0.875rem` + direct declarations trên `& .price` (14px/20px/400/#99a1af) — unlayered,
  specificity 0,3,0, file import sau `product-price.css` → thắng cascade ở mọi tầng.
- `card.css`: thêm block unlayered cuối file — `.hp-card .price-box .old-price`
  (biến `0.875rem`) + `.hp-card .price-box .old-price .price` (font-size 14px direct).
  Color/line-height/weight giữ từ layered rule hiện có (đã đúng, unlayered không đụng).
- Line-through giữ nguyên từ `product-price.css` (đã có ở cả 2 surface).

### Constraints / Rules
- CSS-only trong theme `Secomm/launchpad`; không đụng template markup, JS, price data.
- Weight/màu dùng literal (pattern `card.css` hiện có) — token weight generated lỗi
  (`400px`), không có token màu xám; comment trong code ghi rõ nguyên nhân.
- File theme CSS giữ LF (trap autocrlf — dataset checksum).
- Không đụng `product-price.css` global (hạn chế scope bleed sang surface khác:
  grouped/bundle/tier, crosssell… — chỉ 2 scope đã chốt).

### Out of Scope
- Chuẩn hoá old price toàn cục (mọi surface ngoài PDP + card) — TL quyết nếu muốn.
- Sửa token generated `400px` (nguồn Figma token file / generator — ticket riêng).
- Flash-sale card đo riêng (cùng template `.hp-card` — fix selector bao phủ; lúc verify
  products trong rail không có special price nên không render old price).

### Acceptance Criteria
AC-001..AC-004 — verify matrix trong `.ai/evidence/TASK-FYN3HC/VERIFY.md`.

## Approach (đã thực hiện)
1. Phân tích cascade 3 tầng: product-price.css (unlayered) / Hyvä base theme build /
   layered rules card.css — xác định unlayered > layered là gốc đè.
2. Sửa `page-catalog.css` (PDP) + `card.css` (card) như Expected Behavior.
3. `npm run build-prod` (web/tailwind) → `web/css/styles.css`; redeploy static
   (`cp` source → `pub/static` vi_VN + en_US — SCD skip file tồn tại, xem VERIFY.md).
4. Verify Playwright: computed style 3 surface + regression giá chính PDP.

## Updates
- **10-01 (v1 — DEV DONE, chờ TL review)**: implement + verify xong. Files:
  `tailwind/theme/page-catalog.css` (+15/-1), `tailwind/theme/card.css` (+14),
  build output `web/css/styles.css`. Verify **4/4 PASS** (matrix + screenshots +
  probe scripts trong `.ai/evidence/TASK-FYN3HC/`): PDP 16px/24px/đen → 14px/20px/400/
  #99a1af; home card 12.25px → 14px; PLP search card 14px; giá chính PDP 36px giữ
  nguyên. Cả 2 file sửa giữ LF. **Flags TL**: (1) `setup:static-content:deploy -f`
  báo xong nhưng không ghi đè styles.css đã tồn tại trong pub/static (local dev,
  developer mode) — đã `cp` thay thế; cần chú ý khi deploy staging/prod dùng pipeline
  SCD chuẩn; (2) token `--ds-type-body-3-font-weight` generated bị lỗi `400px` —
  đề xuất ticket riêng sửa token file/generator; (3) scope bleed: các surface khác
  (grouped/bundle price box, crosssell, wishlist…) vẫn giữ render cũ — out of scope
  theo chốt scope 10-01.
