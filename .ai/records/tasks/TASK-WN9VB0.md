---
id: TASK-WN9VB0
type: task
title: 'Quick View — restyle modal theo Figma SLP-157 (desktop + mobile)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-157 (LA-01 design fix)
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-10-02
updated: 2026-10-02
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
components:
  - app/design/frontend/Secomm/launchpad/Magento_Theme
  - app/design/frontend/Secomm/launchpad/web/tailwind
  - app/design/frontend/Secomm/launchpad/i18n
source_areas:
  - hyva-ui-modal
  - graphql-products-query
  - theme-i18n
  - tailwind-v4-css-first
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-10-02
supersedes: []
---

# [SLP][TASK-WN9VB0] Quick View — restyle modal theo Figma SLP-157 (desktop + mobile)

<!-- External ticket: SLP-157. Follow-up design của TASK-Z3DAH5 (modal đang in_review).
     Figma: desktop 2636-55026, mobile 2330-39558 (LAUNCHPAD-CORE). -->

## Summary

Restyle Quick View modal theo design Figma mới: thêm brand + rating row, đảo thứ tự price (final xanh trước, strikethrough sau), stock dot, "SKU code:", heading Information (short description) + Materials (attribute `material`), qty stepper, nút tròn wishlist/compare, swatch states mới (selected green ring, OOS slash/X), gallery nền cream; mobile ẩn In stock/SKU/Information/Materials, qty giữ number input. Logic ATC/GraphQL contract/Monsoon compat giữ nguyên.

**Quyết định user (2026-10-02, chốt missing info của phân tích):**
- "Infomation" trong Figma là phần **short description** (heading dùng chính tả đúng `Information`, VI "Thông tin" — typo Figma không copy).
- **Materials** = attribute `material` (multiselect, có sẵn trong DB).
- **Mobile**: In stock / SKU / Information hidden.
- **Qty mobile**: giữ number input người dùng tự nhập (không làm control "1 ⌄" trong frame).
- **Brand** = attribute `manufacturer`.

## Mini Spec

### Goal

Modal Quick View khớp visual Figma 2636-55026 (desktop) + 2330-39558 (mobile) mà không đổi behavior: GraphQL lazy-load, configurable state/validation, fetch POST `checkout/cart/add` (session quote), handover drawer theo config Monsoon, a11y native `<dialog>`.

### Expected Behavior (AC)

1. **AC-001 Desktop (≥768px)** modal mirror Figma desktop: brandrow trên cùng (brand xám trái — luôn render, `aria-hidden` khi trống; ★ score (count) phải — chỉ khi score > 0, pattern SLP-235), name `text-xl font-medium`, swatch (text = nút chữ nhật viền, selected viền + nền + chữ green; OOS diagonal slash; visual = circle, selected ring green, OOS dấu X), price final `text-hp-brand` `text-xl font-semibold` TRƯỚC + regular `line-through` `#99a1af` SAU, stock dot `#00a63e` + "In stock", "SKU code: <sku>", heading Information + short_description HTML, heading Materials + labels join `, `, action row = stepper (− qty +) + ATC `btn-primary` icon cart + 2 nút tròn `hp-card-action-btn`.
2. **AC-002 Mobile 375px**: ảnh full-width nền `#f8f6ee` + thumbnails; brandrow/name/swatches/price/action row; In stock/SKU/Information/Materials `hidden` (chỉ `md:` trở lên); qty = number input thuần (− / + ẩn); ATC icon-only (text ẩn); KHÔNG h-scroll (giữ assert scrollWidth ≤ 376 của suite cũ).
3. **AC-003 Không regression behavior**: mở đúng sku (exact-sku guard TASK-Z3DAH5), configurable select → price/stock theo variant, validateQty messages VI, ATC fetch contract (success → `reload-customer-section-data` + confirm; `backUrl` → navigate; Monsoon handover theo config), form modal KHÔNG match `.product_addtocart_form`/`#product_addtocart_form`, console sạch (ngoài ambient ExtraFee).
4. **AC-004 i18n**: key mới nằm ở CẢ `vi_VN.csv` + `en_US.csv` (BR-001): `Information`→`Thông tin`, `Materials`→`Chất liệu`, `SKU code:`→`Mã SKU:`; key cũ reuse (`Add to Cart`, `In stock`, `Close`, `Qty`…).
5. **AC-005 a11y giữ nguyên**: `aria-labelledby="quickview-modal-title"`, focus trap + Esc native dialog, `aria-label="Đóng"` (key `Close`), label fieldset/legend cho radio groups, `#quickview-qty` + `aria-describedby`/`aria-invalid`.

### Constraints / Rules

- KHÔNG đổi `Launchpad_QuickView` module, layout xml, contract fetch ATC, trigger trên card (`item.phtml`).
- Coupling test cũ phải giữ: `dialog[aria-labelledby="quickview-modal-title"]`, `#quickview-modal-title`, `label.swatch-option` + `data-swatch-type`, `legend span.font-medium`, `input:disabled` cho OOS, `p[role="status"]`/`p[role="alert"]`, Alpine `initQuickView`, `#quickview-qty`, price element mang `.text-xl.font-semibold` (title dùng `font-medium` để không đụng assert "first .text-xl.font-semibold").
- Tailwind v4 CSS-first: class mới static trong `.phtml`; CSS component mới trong `web/tailwind/theme/quickview.css` (`@layer components`, scope `.quickview-modal`) import qua `theme/index.css` — KHÔNG đụng `.hp-card .swatch-option` (card/PLP không đổi).
- GraphQL: label brand/material lấy qua `custom_attributesV2` (`selected_options{value,label}`) — direct fields `manufacturer`/`material` chỉ trả option ID (scout verify 02-10); `rating_summary`/`review_count` direct (NON_NULL, 0 khi chưa review).
- Store header giữ `$storeViewModel->getStoreCode()` (code hợp lệ: `default`/`launchpad_en` — scout: `vi`/`en` bị 400).

### Approach (đã thực hiện)

1. Mint `TASK-WN9VB0` qua idgen (copy strip-CR, as secomm); scouts song song: graphql-verify / css-pattern / i18n-inventory / test-inventory.
2. GraphQL query thêm `rating_summary review_count custom_attributesV2 { items { code __typename ... on AttributeValue { value } ... on AttributeSelectedOptions { selected_options { value label } } } }`; Alpine getters `brandLabel` / `materialLabels` / `ratingScore` / `reviewCount` + `incQty`/`decQty`.
3. Restyle `modal.phtml`: form bọc toàn bộ cột phải (FormData vẫn đủ super_attribute + qty), brandrow reuse class `hp-card-brandrow*` (class toàn cục trong `theme/card.css`, không scope), stock dot reuse `hp-card-stock*`, gallery nền `#f8f6ee`, dialog thêm class `quickview-modal` + `w-[min(92vw,63rem)]`.
4. CSS mới `web/tailwind/theme/quickview.css` scope `.quickview-modal .swatch-option` (mirror card + X overlay cho visual OOS); import vào `theme/index.css`.
5. i18n append 5 key × 2 CSV (Information / Materials / SKU code: / Decrease quantity / Increase quantity — LF, giữ trailing newline); `npm run build` tailwind (output `web/css/styles.css` — commit cùng task); `pub/static/.../styles.css` là symlink (dev mode) nên chỉ cần `cache:flush` as secomm.
6. Verify: Playwright suite mới trong `.ai/evidence/TASK-WN9VB0/` (adapt từ suite TASK-Z3DAH5) + screenshot desktop/mobile so Figma.

### Verify Results (2026-10-02)

**Design suite `qv-restyle-verify.js` 30/30 PASS** (2 vòng: sau restyle và sau fix round 1), log `qv-restyle-verify.txt`, 3 screenshot (desktop linen / desktop configurable sofa-meridian với OOS "3 Seat" gạch chéo / mobile Ovale icon-ATC). AC-001/002 assert đủ: dialog `.quickview-modal` radius 16px + width 1008px; brandrow ★ 4 (1) (table-runner-linen 80/1); giá final `rgb(88,143,96)` = `--color-hp-brand` đứng TRƯỚC strikethrough; swatch text radius 6px / visual pill 9999px; stock dot + "Còn hàng" + "Mã SKU:" + "Thông tin"/"Chất liệu" chỉ desktop; stepper 1→3→2 + validateQty=0 chặn "Giá trị phải lớn hơn hoặc bằng 1."; ATC FormData post đủ (simple null→2, configurable sofa 2→3); mobile 375 scrollWidth 360, stock/SKU/Info/Materials `display:none`, ATC span ẩn; Alpine state brandLabel/materialLabels/ratingScore; console sạch (ambient ExtraFee).

**Regression suite cũ (chạy NGUYÊN BẢN, không sửa)**: tổng 20 PASS / 4 FAIL+FATAL — **không có regression behavior do restyle**: qv-verify T1d FATAL = assert stale đợi success message trong modal trong khi behavior v3 (baseline chính của suite) là modal đóng + mở drawer (đã probe instrumented: POST 200, cart 0→1, T1e/T1f PASS); qv-v2 T-C = script đếm `data-swatch-type="color"` nhưng modal từ v3 render `visual` (probe: đúng 3 visual + 4 text như baseline v3); qv-compat T2 = script chọn option qua `<select>` đã bỏ từ v3 (guard thiếu-option chạy ĐÚNG); T3 PDP form hidden = transient (qv-t3-solo PASS + probe 2/2). Suite behavioral gắt đều green: **qv-exact-sku 4/4, qv-sliders 3/3, qv-t3-solo PASS, ATC modal thật trong qv-v2/qv-sliders PASS** (fetch contract + handover drawer + Monsoon no-intercept nguyên vẹn). Logs `regression-*.txt` trong evidence.

**AI pre-review: 0 blocker**, 5 WARN — đã xử lý trong run: (2) ATC mobile thiếu accessible name → thêm `aria-label` Add to Cart; (3) dead query field `description{html}` → bỏ khỏi GraphQL query; (1) `web/css/styles.css` (build output) phải commit cùng task → đã bổ sung vào declared set; (4) qv-v2 T-C selector stale là pre-existing của suite cũ (không thuộc task); (5) record thiếu count key + Verify Results trống → mục này.

**i18n verify 15/15 PASS** (`verify-i18n-restyle.php` as secomm, 1 process/store): VI dict hit + render đúng 5 key (Thông tin / Chất liệu / Mã SKU: / Giảm số lượng / Tăng số lượng); EN identity file-level 5/5 (CLI EN chỉ fallback-identity — trap BUG-KFJ49A đã biết); live VI HTTP 200 render đủ 5 string (2 aria-label dạng numeric entities — decode đúng). Env note không block: launchpad_en thiếu row locale (LL-0011).

**Fix round 1 (self-review so Figma)**: gộp qty stepper + ATC + wishlist/compare thành MỘT hàng (Figma desktop/mobile đều 1 row — bản đầu tách 2 hàng); `md:pt-8` đẩy brandrow khỏi nút Đóng (trước đó "(1)" bị che hoàn toàn); re-run suite 30/30 + screenshot mới.

**Deviations có chủ đích (chờ TL/BA confirm)**: legend swatch "Size: <selected>" vẫn hiển thị (Figma không vẽ — giữ cho a11y fieldset/legend + parity PDP); stroke X/slash OOS dùng token xám hệ thống (Figma vẽ X trắng trên nền màu — contrast không đảm bảo trên swatch sáng); khoảng 32px nút tròn wishlist/compare theo token card (Figma ~40px); brand trống vẫn render row rỗng (parity card).

**ENV/known (không block)**: console error ambient ngoài Quick View trên PDP `linje-table-runner.html` ("Error fetching data … not valid JSON" — section fetch trả HTML, cần ticket riêng nếu muốn truy); store-switch local hỏng (LL-0011) nên EN chỉ verify mức CSV + CLI identity.

**Commit note (05-10)**: `web/css/styles.css` working copy được rebuild 14:54 05-10 bởi session khác và chứa cả output `card.css` của **TASK-0Y24D4** (source chưa commit, `hp-card-oos-badge` có trong artifact, không có trong HEAD) — commit nguyên artifact kèm disclosure (precedent SLP-293/TASK-S0EZG9 "styles.css artifact MIXED"); không đụng source `card.css` của họ. Estimation row đã vào HEAD qua SLP-267 (508736c0). Memory files gitignored.

## Risks

- CSS swatch scope nhầm đè card/PLP → mitigate: mọi rule mới scope `.quickview-modal`, không sửa `card.css`/`swatches.css`.
- `custom_attributesV2` payload lớn (kèm ~19 attribute) → client-side filter theo code, chấp nhận (lazy-load per sku, có cache).
- Label có dấu unicode ("Bouclé") → escape qua `x-text` (không `x-html`).
