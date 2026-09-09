# BUG-AMRBJR (SLP-187) — Results — Translate OSC checkout sections — 2026-09-09

## Change set (2 files, CSV-only)

- `app/code/Launchpad/MageplazaTranslate/i18n/vi_VN.csv` — +32 keys (58 → 90 rows)
- `app/code/Launchpad/MageplazaTranslate/i18n/en_US.csv` — +32 identity keys (58 → 90 rows)
  - Bonus pre-existing fix: en_US row 37 `"Allows admins to choose the time frame for delivery"`
    carried another row's value (copy-paste from SLP-146 batch) → restored identity.
- No vendor file touched, no template override, no layout/JS change.
- Wording source: theme dict `Secomm/launchpad/i18n/vi_VN.csv` where key existed (SSOT for
  wording consistency), else new. See `add-keys.py` (idempotent generator, kept as evidence).

## Procedure (proven recipe, same as BUG-5NR0PD / BUG-NY0M3S / BUG-1N8XC8)

1. Append keys to module-layer CSVs (theme dict is DEAD on OSC checkout — luma scope, LL-0011).
2. Delete `pub/static/frontend/Magento/luma/{vi_VN,en_US}/js-translation.json`, trigger regen by
   HTTP request to the static resource directly (`curl http://127.0.0.1/static/frontend/Magento/
   luma/vi_VN/js-translation.json -H 'Host: slaunchpad.localhost'` → 200). vi dict 52 → 78 keys.
   (Note: plain page request via 127.0.0.1 + Host header returned 302 — static-resource request
   is the reliable trigger; Playwright uses `--host-resolver-rules`.)
3. `runuser -u secomm -- php bin/magento cache:flush` (shell AI session runs as root; magento CLI
   must run as secomm — vhost user, see memory slaunchpad-local-env-shell-traps).

## Verification results

### Framework level
- CSV structure: 90 rows/file, all 2-column, vi/en key parity OK, en identity OK (after row-37 fix).
- js-translation.json (vi): 21/32 new keys packed (only JS/KO-literal phrases pack — expected;
  the other 11 are server-side config translations which don't need packing).
  Packed spot-checks incl. `Please specify a payment method.` → `Vui lòng chọn phương thức thanh toán.`

### Live (Playwright, guest cart Joust Duffle Bag → /onestepcheckout/, 1504x940)
Store vi (default) — 16 phrase groups confirmed rendered VI in page innerText
(`innerText-after-vi.txt`, compare `innerText-baseline-vi.txt`):
  Địa chỉ giao hàng / Phương thức vận chuyển / Phương thức thanh toán / Thông tin thanh toán /
  Mã giảm giá / Tóm tắt đơn hàng / Sản phẩm trong giỏ hàng / Tên sản phẩm / Số lượng / Tổng phụ /
  Thao tác / Tổng phụ giỏ hàng / Giao hàng / Đặt hàng / Đăng ký nhận bản tin / Tạo tài khoản /
  Địa chỉ thanh toán và giao hàng của tôi giống nhau / Bạn sẽ thanh toán /
  Phương thức giao hàng đã chọn không khả dụng (notCalculatedMessage — server-side config
  translate confirmed working) — all EN in baseline, VI after.
- `Place Order` button: text + `title` attr both `Đặt hàng` (vi) / `Place Order` (en) — covers
  both `i18n:` binding and the `$t('Place Order')` title in placeOrder templates.
- Store vi: **0 EN-visible strings remain** from the ticket scope. Remaining EN = store data (below).

Store en (launchpad_en): all scope strings still EN (identity) — `innerText-after-en.txt`.

Regression (store vi): previously fixed strings still VI — Ngày giao hàng, Thời gian giao hàng,
-- Vui lòng chọn thời gian giao hàng--, Mã bảo mật nhà, Ghi chú giao hàng, Bình luận,
Enter discount code → "Nhập mã giảm giá" (dict), Apply Discount → "Áp dụng", extra-fee required
message (SLP-139). No regression observed from the js-translation regen.

### Conditional / blocked (QC to verify on demo)
- `Already have an account? Click here to login` + `You already have an account with us.`:
  keys PACKED in dict, but the OSC authentication block does NOT render on local
  (`.osc-authentication-wrapper` absent from DOM — OSC config hides the login block).
  Key coverage is dictionary-level; visual verify on demo (where the block renders, per ticket
  screenshot 1). Email-note actually rendered on local is a DIFFERENT string
  ("Please complete your information below to creat an account." — vendor typo, not in scope)
  which already renders VI from store config — store data, not CSV.
- `Save in address book`: PACKED ("Lưu vào sổ địa chỉ"); new-address modal not opened live
  (login automation blocked — see below). QC verify on demo (ticket screenshot 6).
- `Please specify a payment method.`: PACKED, render path `$.mage.__()` literal (payment.js:110).
  Live trigger blocked by pre-existing shipping errmsg (below) — QC verify on demo (screenshot 3).

## Known issues NOT fixed by this change (out of scope, pre-existing)

1. **Shipping errmsg still EN and blocks guest place-order locally** — `The selected shipping
   method is not applicable to your order. Please contact us for more details.` renders raw from
   carrier config (`carriers/mptablerate/specificerrmsg`, default in
   `TableRateShipping/etc/config.xml`) — not dict-translated. Re-confirms finding F4 of
   BUG-NY0M3S / BUG-5NR0PD note. With this message present, place-order validation stops at
   shipping on local, so the payment validation message cannot be reached live. The key was still
   mirrored into the module CSV (wording from theme dict) for any `$t()`-translated path.
   Options for follow-up ticket: JS mixin (like BUG-GJT6C1 calendar mixin pattern) or Admin
   config `specificerrmsg` per store view.
2. `Please enter your details below to complete your purchase.` (page subtitle) — rendered raw
   (`@noEscape`) from OSC config `<general><description>` in `OscUltimate/etc/config.xml` —
   store data. Admin: Stores → Configuration → One Step Checkout → General → Description per
   store view (demo already shows VI).
3. `Leave a message with the extra fee.` — ExtraFee rule `message_title` stored in DB rule #1
   (rendered raw) — store data, Admin per store view. (SLP-146 had added the "…for…" variant key;
   the "…with…" variant is now also in the CSV but cannot affect raw DB-rendered labels.)

## Store data — hand to Admin (cannot be fixed via CSV)

| Visible string | Where | Admin path |
|---|---|---|
| Table Rate / Best Way | shipping method title | Stores → Config → Sales → Shipping Methods → Table Rate (title/method name per store view) |
| Flat Rate / Fixed | shipping method title | Stores → Config → Sales → Shipping Methods → Flat Rate |
| Check / Money order | payment method title | Stores → Config → Sales → Payment Methods |
| rrrrrr, 20HKD, 30HKD, Extra_fee_Payment_VI | ExtraFee rule names/options | Mageplaza → Extra Fee → Manage Rules (per store view) |
| Please enter your details below… | OSC subtitle | Stores → Config → One Step Checkout → General → Description |
| Leave a message with the extra fee. | ExtraFee rule message title | Mageplaza → Extra Fee → Manage Rules → rule #1 → Message Title |

## Artifacts

- `probe-baseline.js` / `innerText-baseline-{vi,en}.txt` — baseline capture (before)
- `innerText-after-{vi,en}.txt` — after-capture (main PASS evidence)
- `verify-conditional.js` — auth-link DOM state, email-note, button label, modal attempt
- `add-keys.py` — idempotent CSV append generator
- `/tmp/osc-i18n-{baseline,after,conditional}.json` — raw probe output (session-scratch)
