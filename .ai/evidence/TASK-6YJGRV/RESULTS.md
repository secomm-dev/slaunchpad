# TASK-6YJGRV — Evidence

Date: 2026-10-06 · Mode C · Ticket: SLP-305

## Change set

| File | Change |
|---|---|
| `app/design/frontend/Secomm/launchpad/web/tailwind/components/actions-toolbar.css` | Rule deprecated `& a.back { @apply underline }` → `& a.action.back { @apply btn btn-tertiary }` (+ comment TASK-6YJGRV) |

## Root cause

QC screenshot (06-10): "Quay lại" trên form địa chỉ render plain text link (blue underline) cạnh primary "Lưu" — `a.action back` là legacy Luma pattern chưa map vào hệ `btn`. Convention markup của mọi template (3 theme override + 14 vendor account-area) đều là `.actions-toolbar > .secondary > a.action.back` → 1 rule CSS cover toàn bộ, đúng cơ chế CSS-remap của style guide (như `card`, `form-input`).

## Decision

- Variant: **`btn-tertiary`** (outline) — pairing chuẩn cho action phụ cạnh primary; precedent anchor + `btn-tertiary` đã có ở styleguide index nav. Size mặc định `btn` (36px) khớp `btn btn-primary` không size class của nút Lưu cùng toolbar.
- **Variant choice cờ cho TL**: nếu Figma quy định back = `btn-secondary` (soft bg) thì đổi 1 class trong 1 rule.

## Verification

1. `npm run build` pass (476ms).
2. Compiled CSS: `.actions-toolbar a.action.back{...}` base btn (36px + ::after 44px touch target + text-decoration:none) **+ block 2** tertiary vars (--btn-bg --ds-bg-white-transparent, --btn-color brand-700, inset border brand-400) + `:hover`, `:focus-visible` (focus ring), `:is(:disabled)` đầy đủ.
3. Serve vi_VN (curl Host-header 127.0.0.1:80): `a.action.back` count > 0.
4. Không cần cache:flush / template change — chỉ CSS asset (symlink).

## Residual / cho TL

- AC visual: "Quay lại" render tertiary button 36px cạnh "Lưu" trên form địa chỉ + back links các trang account khác (order view, wishlist, address book, newsletter, register...) — QC/TL browser check.
- Variant tertiary vs secondary — chờ TL chốt theo Figma.

## Round 2 (2026-10-06) — underline/Luma-blue trên hover

QC feedback: "không giống style guide" — hover lên "Quay lại" vẫn underline + xanh Luma.

**Root cause**: `app/code/Mageplaza/SocialLogin/view/frontend/web/css/hyva/style.css:74-82`:
```css
#popup_test .remind:hover, .back:hover, .action.create:hover { color:#006bb4 !important; text-decoration:underline !important }
#popup_test .remind:active, .back:active, .action.create:active { color:#ff5501 !important; text-decoration:underline !important }
```
Aggregate vào bundle qua `@import "@hyva-themes/hyva-modules/css"`; bare-class + `!important` thắng var-based btn states.

**Fix**: trong `.actions-toolbar a.action.back` thêm `:hover`/`:focus-visible`/`:is(:active,.is-active)` re-assert `color: var(--btn-*-color) !important; text-decoration:none !important` — (0,2,2)+!important thắng (0,1,1)+!important. Mageplaza module không sửa.

**Verify**: compiled + serve vi_VN có 3 re-assert rules; pub/static giữ symlink.

**Residual (SLP-305 backlog)**: `.action.create:hover` / `.remind:hover` cùng file vẫn Luma-color/underline các link register/remind — gom vào audit SLP-305 tổng thể.

## Round 3 (2026-10-06) — variant chốt: TEXT LINK theo reference "Xem tất cả"

QC feedback: "nút quay lại nên có style giống nút Xem tất cả" (screenshot wishlist + recently ordered).

**Reference**: `Magento_Sales/templates/order/recent.phtml:54` — "View All" = `action view inline-block underline`: text link underline, màu ink kế thừa, không box. Design intent cho back/secondary action trong toolbar = text link (round-1 chọn `btn-tertiary` là sai variant).

**Fix**: bỏ `@apply btn btn-tertiary` → `.actions-toolbar a.action.back{text-decoration:underline}` + grouped `:hover/:focus-visible/:is(:active,.is-active){color:inherit!important;text-decoration:underline!important}` (giữ re-assert !important chặn Luma blue/orange của Mageplaza legacy).

**Verify**: compiled chỉ còn 2 rule (base + grouped states), không còn btn vars; serve vi_VN count=1; pub/static symlink.

