---
id: TASK-0Y24D4
type: task
title: 'Product card — out-of-stock option states theo Figma SLP-307'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-307
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-10-05
updated: 2026-10-05
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: none-material
components:
  - app/design/frontend/Secomm/launchpad/web/tailwind/components/swatches.css
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/card.css
  - app/design/frontend/Secomm/launchpad/web/tailwind/theme/quickview.css
source_areas:
  - hyva-ui-card
  - tailwind-v4-css-first
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-10-05
supersedes: []
---

# [SLP][TASK-0Y24D4] Product card — out-of-stock option states theo Figma SLP-307

<!-- External ticket: SLP-307. Figma LAUNCHPAD-CORE 3286-150414 (Product card,
     "In stock" với option OOS). Phân tích /task 2026-10-05; user chốt "thực thi".
     Precedent: SLP-272 (card OOS token), TASK-WN9VB0 (quickview X 2 nét xám —
     giữ nguyên, không đụng). -->

## Summary

Restyle trạng thái **out-of-stock của option (swatch) trên product card** (`.hp-card`) theo Figma 3286-150414: text swatch OOS = bg `#f3f4f6` + border `#d1d5dc` + label `#99a1af` opacity-75 + gạch chéo 2px `#d1d5dc`; color dot OOS = cả swatch opacity-50 + badge tròn trắng chứa X đỏ `#C10007` đè giữa dot (thay vệt gạch chéo 1 nét hiện tại). CSS-only, scope `.hp-card` — không đụng quickview/PDP.

**Assumption đã confirm với user (2026-10-05):** "thực thi" trên phân tích nêu rõ 2 assumption (image swatch dùng cùng badge X; quickview/PDP giữ style cũ) — không phản đối, chạy theo phân tích.

## Mini Spec

### Goal

Card swatch OOS khớp visual Figma 3286-150414, giữ nguyên markup/mechanic hiện có (label.swatch-option + radio `:disabled` qua Alpine `optionIsDisabled` —Hyvä configurable-options.js `optionIsActive`). Không đổi behavior chọn option.

### Expected Behavior (AC)

1. **AC-001 Text swatch OOS** (size): bg `#f3f4f6`, border 2px `var(--ds-border-gray-primary)` (#d1d5dc — đổi từ gray-secondary), label `var(--ds-text-gray-quinary)` + opacity .75, gạch chéo **2px** màu `var(--ds-border-gray-primary)` (đổi từ 1px gray-secondary).
2. **AC-002 Color dot OOS**: cả option `opacity: .5`; KHÔNG còn vệt gạch chéo trên dot (`> span background-image: none`); badge `::after` tròn trắng 20px giữa dot chứa X đỏ `var(--ds-global-colors-red-700)` (kỹ thuật 2 crossed-gradient, tương tự quickview nhưng màu red-700 và nền trắng). Image swatch OOS dùng cùng badge (::after override strike của base swatches.css).
3. **AC-003 Không regression**: selected/hover/focus swatch states (SLP-272 CR1) không đổi; quickview modal giữ X 2 nét xám (SLP-157); PDP swatch giữ style base; text swatch đang active trên in-stock card không đổi.
4. **AC-004** Không phát sinh string i18n mới; không đụng template/PHP/layout.

### Constraints / Rules

- Scope CSS: chỉ `.hp-card .swatch-option` override trong `web/tailwind/theme/card.css` — base `components/swatches.css` không đổi (PDP giữ nguyên).
- Màu dùng đúng token design system (khớp Figma 1:1): `--ds-border-gray-primary` = rgb(209 213 220) = #D1D5DC; `--ds-text-gray-quinary` = rgb(153 161 175) = #99A1AF; `--ds-global-colors-red-700` = rgb(193 0 7) = #C10007.
- Specificity: override phải thắng base strike (base ≈ 0,3,x; `.hp-card` scope ≈ 0,4,0 — đủ).
- Tailwind v4 CSS-first, rebuild `npm run build` trong `web/tailwind/` as secomm; rm stale `pub/static/.../css/styles.css` trước khi verify (Publisher skip-existing).

### Approach (đã thực hiện)

1. Mint `TASK-0Y24D4` (idgen strip-CR, as secomm).
2. `card.css`: sửa 2 block disabled hiện có + thêm `::after` badge X.
3. Build Tailwind, rm stale static, probe Playwright PLP `/beds.html` (bed-haven: color `sage` OOS — children 2140/2143 is_in_stock=0), chụp + assert computed styles.
4. Evidence `.ai/evidence/TASK-0Y24D4/`, pre-review, TL review.

### Kết quả thực hiện (2026-10-05)

- **Scope mở rộng (user 10-05: "chưa apply trong quickview và product detail")**: design OOS áp cho CẢ 3 surface. Nguồn chung chuyển vào **base `components/swatches.css`** (block disabled của `.swatch-option`): text OOS = bg #f3f4f6 + border 2px gray-primary + label gray-quinary @.75 + strike 2px; visual OOS = opacity .5 + dot không strike + badge `&::after` trắng (var `--swatch-oos-badge`, default 1.25rem/20px cho PDP) + X red-700 crossed-gradient. Card + quickview chỉ re-assert 3 giá trị đụng tie với rule normal (theme import sau components) + `--swatch-oos-badge: 1rem` (dot 24px). Supersedes X 2 nét xám của TASK-WN9VB0 trong quickview + strike cũ của SLP-272.
- **Điều chỉnh kỹ thuật**: (1) disabled trên card/PDP chỉ xảy ra theo TỔ HỢP sau khi chọn 1 attribute (`jsonConfig.salable` → `calculateAllowedAttributeOptions`; fresh-load không lọc — design Figma cũng vẽ state sau-chọn) → không cần override renderer. (2) Lightning CSS compile bare `::after` trong @layer nested thành DESCENDANT (`& ::after`) — phải viết `&::after` (badge đã không render lần build đầu). (3) Base `.swatch-option` đổi `border-width: 1px → 2px` (design swatch component border-2; card/quickview đã 2px từ trước, PDP theo; layered-nav swatch không ảnh hưởng — utility layer thắng).
- **Verify (Playwright, 29/29 PASS tổng)**: card 13/13 (`verify.txt`, `card-oos-{text,visual}.png` — badge 16px); PDP + QuickView 16/16 (`verify-pdp-quickview.txt`, `pdp-oos-{text,visual}.png`, `quickview-oos.png` — PDP badge 20px, quickview 16px; text OOS khớp đủ bg/border/label/strike; selected ring #588f60 không đổi; 0 pageerror sau env-fix).

### OUT-OF-SCOPE phát hiện khi verify (flag TL — xem memory `slaunchpad-deployed-version-newline-trap`)

- **[ENV BUG — đã sửa local] `pub/static/deployed_version.txt` chứa trailing newline** (`1790932977\n`, mtime 05-10 14:54 — writer không rõ, không phải core path vì `generateVersion()` trả số thuần). Newline nhiễm vào MỌI static URL (`static/version…\n/…`); browser trim trong attribute nên asset vẫn load, NHƯNG script inline `variables.phtml` (`BASE_URL`/`THEME_PATH`/`COOKIE_CONFIG`/`CURRENT_STORE_CODE`) chết tại `THEME_PATH` (unterminated string) → cascade toàn storefront: `initConfigurableOptions` throw `BASE_URL is not defined` → scope Alpine swatch card chết (`optionIsDisabled is not defined` ×N, combo-disable + price-update + link-update card PLP/chọn-option đều hỏng), formkey cookie lỗi (`lifetime`), PDP configurable cũng liệt vùng. **Đã fix**: `printf '1790932977' > deployed_version.txt` + `cache:flush` — giá trị version giữ nguyên. Cần bug ticket riêng để truy writer (nghi manual echo / script deploy) + harden (trim khi load, hoặc check trong deploy script).
- Pre-existing pageerror khác: `smileTracker` catch block ok; không còn lỗi nào sau fix (probe chạy sạch).

