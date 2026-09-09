# BUG-NY0M3S (SLP-139) — Verification Results

**Date**: 2026-09-09 · **Env**: local `slaunchpad.localhost` (Magento 2.4.8-p5, developer mode, file cache) · **Method**: Playwright headless Chromium (fresh context per store) + static-dictionary inspection + curl.

## Changes under test

| File | Change |
|---|---|
| `app/code/Launchpad/MageplazaTranslate/i18n/vi_VN.csv` | +1 key `Please choose at least one option for each require extra fee` → VI (wording = theme CSV:523) |
| `app/code/Launchpad/MageplazaTranslate/i18n/en_US.csv` | +1 key mirror (identity EN, BR-001) |
| `app/code/Launchpad/MageplazaExtraFeeFix/view/frontend/web/css/extra-fee-checkout.css` | NEW — collapse empty `.mp-description` line (`:has(span:not(:empty))` guard) + cap margin 4px; scoped `#mp-extra-fee-billing` |
| `app/code/Launchpad/MageplazaExtraFeeFix/view/frontend/layout/onestepcheckout_index_index.xml` | NEW — attach the CSS to the Mageplaza OSC checkout page only |
| `app/code/Launchpad/MageplazaExtraFeeFix/README.md`, `CHANGELOG.md` | NEW — module docs (Fix 1 plugin was undocumented; [WARN] rule) |

Deploy steps: `bin/magento cache:flush` + js-translation.json regen (see Findings F1) — all magento CLI/static ops ran as `secomm` (memory shell-traps).

## AC results

### AC-001 — dictionary framework-level: PASS

`pub/static/frontend/Magento/luma/<locale>/js-translation.json` regenerated:

- vi_VN: `"Please choose at least one option for each require extra fee": "Vui lòng chọn ít nhất một tùy chọn cho mỗi phí bổ sung bắt buộc"` (52 keys = 51 + 1)
- en_US: 0 keys — **đúng thiết kế**: `DataProvider` chỉ include phrase khi bản dịch khác source (`vendor/magento/module-translation/Model/Js/DataProvider.php:95`), en_US identity → dict rỗng, `$t()` trả source EN.

### AC-002 — live VI message: PASS

Submit Place Order khi chưa chọn option required (rule #1, is_required=1, checkbox):

- vi store: `#mp-extra-fee-billing .message.notice` = **"Vui lòng chọn ít nhất một tùy chọn cho mỗi phí bổ sung bắt buộc"**, visible ✓ (`trigger-vi.json`)
- Baseline trước fix (dict chưa regenerate): EN `"Please choose at least one option for each require extra fee"` (`/tmp/extrafee-trigger.json` trước deploy — EN xác nhận path đúng)

### AC-003 — spacing: PASS

| Store | Gap dt-label → option input | State |
|---|---|---|
| vi | **49px → 9px** | baseline → after |
| en | **49px → 9px** | baseline → after |

Culprit (đo computed style, `spacing-baseline.json`): block `.mp-description` **rỗng** (rule không có description) vẫn chiếm line box 20px + margin 10px×2 = 40px; sau fix `display: none` (CSS presence + computed style: `css-presence.json` — `descDisplay: "none"`). Còn lại 9px = input offset + dd margin — mức demo Mageplaza (~0–10px). Files: `spacing-baseline.json` (49px), `spacing-after.json` (9px cả 2 store).

### AC-004 — en_US regression: PASS

en store (`?___store=launchpad_en`, html lang=en): message EN identity, không VI leak; spacing 9px như vi (`trigger-en.json`, `spacing-after.json`).

### AC-005 — scope: PASS

- `extra-fee-checkout.css` chỉ attach trên handle `onestepcheckout_index_index` — curl homepage/cart/PDP (200): 0 reference tới file; trang checkout có cart: link tag + stylesheet loaded + rules > 0 (`css-presence.json`).
- Selector scope `#mp-extra-fee-billing` — không đụng `#mp-extra-fee-shipping` (không có rule area=2), cart extra fee, discount block.
- Console: chỉ ambient errors có từ trước (302 `mpextrafee/product/extrafee/` trên PDP — pre-existing, khỏi scope).

## Findings (cho team)

- **F1 — js-translation.json regenerate on-demand (dev mode)**: `setup:static-content:deploy -f` KHÔNG regenerate file đã tồn tại; cơ chế đúng = xóa file stale rồi request qua HTTP (dev-mode StaticResource + `Magento\Translation\Model\Json\PreProcessor` generate lại). Bổ sung cho LL-0011/LL-0012: dict chỉ chứa phrase có literal trong JS/HTML scan được **và** bản dịch khác source tại thời điểm generate (theme fallback luma + module CSVs).
- **F2 — test fixture**: rule #1 `rrrrrr` (rule test dùng chung) `area` đã được đổi 3 (Cart) → 1 (Payment) trên local DB để mirror setup demo (rule hiển thị ở payment step). Có dấu hiệu bị save đè về area=3 giữa chừng (updated_at 09:03:40 — trùng lúc deploy, có thể session/team khác) → đã set lại; **QC lưu ý kiểm tra area=1 trước khi test**.
- **F3 — store-switch cookie**: sau `cache:flush`, request với cookie `store=launchpad_en` vẫn nhận trang vi (FPC vary nghi vấn); `?___store=launchpad_en` hoạt động đúng. Out of scope — harness Playwright chuyển sang dùng `___store` query.
- **F4 — observation cho SLP-150 QC**: khi Place Order thiếu shipping method, global error **"The selected shipping method is not applicable…" vẫn EN** trên store vi — path place-order validation, khác path shipping-method-item template đã fix 09-04 (theme-layer). Cần QC/re-verify khi quay lại SLP-150.

## Probe scripts

`probe-extrafee.js` (baseline gap), `probe-extrafee2.js` (diagnosis AJAX/computed), `probe-extrafee3.js` (A/B measure + lang assert + poll), `probe-extrafee-trigger.js` (validation message), `probe-css-presence.js` (stylesheet + computed display).
