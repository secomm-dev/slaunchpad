# BUG-TK8C2Y (SLP-144) — Translate extra fee "(Excl. Tax)" suffix on cart totals

Ngày verify: 2026-09-09 · Env: local dev (MAGE_MODE=developer), file cache · Run as secomm (memory `slaunchpad-local-env-shell-traps`)

## Thay đổi

| File | Thay đổi |
|---|---|
| `app/design/frontend/Secomm/launchpad/Mageplaza_ExtraFee/templates/hyva/cart/totals/extra-fee.phtml` | **MỚI** — override vendor `Mageplaza_ExtraFee::hyva/cart/totals/extra-fee.phtml`; verbatim trừ 3 suffix hardcode `'(Excl. Tax)'` / `'(Incl. Tax)'` → `'<?= $escaper->escapeHtml(__(' (Excl. Tax)')) ?>'` / `__(' (Incl. Tax)')` + header comment "Secomm override" + `declare(strict_types=1)` |
| `app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv` | +1 dòng: `" (Incl. Tax)"," (đã gồm thuế)"` (CRLF, QUOTE_ALL; 889 → 890 dòng) |
| `app/design/frontend/Secomm/launchpad/i18n/en_US.csv` | +1 dòng: `" (Incl. Tax)"," (Incl. Tax)"` identity (842 → 843 dòng) — BR-001 mirror |

Key `" (Excl. Tax)"` đã có sẵn 2 file từ BUG-HVMB4G (SLP-133) batch 1 — không thêm.

Deploy: phtml override không cần static deploy; `bin/magento cache:flush block_html` (as secomm).

## Framework verify (`verify-dictionary.php`, 1 process/store, as secomm)

```
[default]  PASS   (Excl. Tax) =>  (chưa gồm thuế)
[default]  PASS   (Incl. Tax) =>  (đã gồm thuế)
[default]  PASS   Delivery Date => Ngày giao hàng        (regression dict intact)
[launchpad_en] PASS   (Excl. Tax) =>  (Excl. Tax)        (identity)
[launchpad_en] PASS   (Incl. Tax) =>  (Incl. Tax)        (identity)
[launchpad_en] PASS   Delivery Date => Delivery Date
[vi_VN.csv] format rows=890 PASS    [en_US.csv] format rows=843 PASS
```

- Lưu ý tooling: script verify cũ (BUG-5NR0PD) split `explode("\r\n")` — CSV hiện tại mixed CRLF + LF-only (~9-10 dòng LF-only do các batch append trước, fgetcsv của Magento parse được cả hai) → script này dùng `preg_split('/\r\n|\n|\r/')` + dup whitelist pre-existing (`Email`, `Comments`, `Enter your comment here` — BUG-5S2Z25).
- Check template-fallback qua `Fallback\TemplateFile::getFile()` trả `null` cả với override lẫn trước khi có override (CLI context — cách gọi chưa đúng; không phải evidence thiếu) → chứng minh override thắng fallback bằng **live render** bên dưới (output có key leading-space + bản dịch = chỉ override mới render được).

## Live verify (curl, guest session + add product 1 / 24-MB01, `--resolve slaunchpad.localhost:80:127.0.0.1`)

**Store vi (`default`)** — cart page sau khi có item, `cache:flush block_html`:

```
x-html="getTitle(segment) + ' (chưa gồm thuế)'"     ← both-mode row 1 (Excl)
x-html="getTitle(segment) + ' (đã gồm thuế)'"       ← both-mode row 2 (Incl)
x-html="getTitle(segment) + ' (chưa gồm thuế)'"     ← including-mode row
```

**Store en (`launchpad_en`, `?___store=launchpad_en`, `<html lang="en">` xác nhận store switch):**

```
x-html="getTitle(segment) + ' (Excl. Tax)'"
x-html="getTitle(segment) + ' (Incl. Tax)'"
x-html="getTitle(segment) + ' (Excl. Tax)'"
```

- VI leak check trên trang en: `grep -c "chưa gồm thuế|đã gồm thuế"` = **0** ✅
- Vendor render cũ không space — `Bảo hiểm(Excl. Tax)`; override render `Bảo hiểm (chưa gồm thuế)` (leading-space key) — AC-002,改善 nhỏ có chủ ý, cả en cũng được space `Bảo hiểm (Excl. Tax)`.

## Scope check (git)

- Của ticket: 2 CSV (M, +1 dòng/file) + 1 template override mới (??). Không đụng vendor `app/code/Mageplaza/**`.
- Các file M khác trong working tree (`Launchpad/MageplazaTranslate/i18n/*`, `.gitignore`, `bin/magento`, `app/etc/*`, `Ahamove/db_schema.xml`…) là pre-existing từ các ticket chờ TL review khác — không đụng trong ticket này.

## Scope extension 09-09 — "This is a required field" (user QC report trên demo)

**Phát hiện:** message validation form Extra Fee ở cart hardcode EN tại `Mageplaza_ExtraFee/view/frontend/templates/hyva/cart/extra-fee.phtml:429` — `messageDiv.innerHTML = '<span>This is a required field</span>'` trong click-handler `#checkout-link-button` (mục (3) Out of Scope của BUG-HVMB4G, đưa vào scope ticket này).

**Thay đổi thêm:**

| File | Thay đổi |
|---|---|
| `app/design/frontend/Secomm/launchpad/Mageplaza_ExtraFee/templates/hyva/cart/extra-fee.phtml` | **MỚI** — override vendor 441 dòng verbatim (giữ Mageplaza license + Secomm note; **không strict_types** — vendor price formatting dựa type coercion); dòng 429 → `'<span><?= $escaper->escapeJs(__('This is a required field.')) ?></span>'` |

Không thêm CSV — key `"This is a required field."` (Magento core phrase, có dấu chấm) có sẵn dòng 52 cả 2 CSV: vi "Trường này là bắt buộc." / en identity.

**Framework verify** (script cập nhật — +1 key): vi PASS "Trường này là bắt buộc.", en identity PASS; CSV vi 897 / en 850 rows PASS (session khác append song song "Disconnect"/"Ajax error…", format sạch).

**Live verify** (cart guest session + product **2041 `atlas-pouf`** qty=5 — SKU trigger fee rule như screenshot demo):

- vi: script render `'<span>Trường này là bắt buộc.</span>'` — `escapeJs` encode non-ASCII/space thành `\uXXXX` (script context không decode HTML entity), JS engine decode đúng khi gán innerHTML → DOM hiển thị "Trường này là bắt buộc."; fee form render (rule + `block-extrafee-summary` = 6 matches).
- en: `'<span>This is a required field.</span>'` identity (space ` ` decode đúng); VI leak = 0; `<html lang="en">`.
- Regression suffix cùng session: vi ` (chưa gồm thuế)`/` (đã gồm thuế)`; en ` (Excl. Tax)`/` (Incl. Tax)`.
- Click-path (click "Tiến hành thanh toán" với fee required chưa chọn → warning hiển thị + chặn navigate) = QC browser.

## Còn lại (QC/TL)

- **QC browser (vi)**: cart có fee rule match điều kiện (rule "Bảo hiểm", "Extra_fee Cart - 20HKD" như screenshot SLP-144 — cart test với product 24-MB01 chưa trigger fee segment, rules có điều kiện riêng) → số tiền KHÔNG đổi, label "… (chưa gồm thuế)".
- **QC browser (en)**: regression identity + space mới.
- TL review wording: " (chưa gồm thuế)" / " (đã gồm thuế)" — khớp dict sẵn có (line 534/792 cũ).
- Out of scope đã ghi record §Out of Scope: OSC checkout summary + My Account order view (variant key ` (Excl.Tax)` không leading space ở `Block/Sales/Order/ExtraFee.php:123` — nếu QC thấy EN ở đó → ticket riêng), rule name "Extra_fee Cart" = store data (Admin), PDF, vendor quirk mode `including`.
