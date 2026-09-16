# TASK-K14RVZ (SLP-217) — Verify Results

Ngày: 2026-09-16. Change set: capture-phase `invalid` listener trong
`Magento_Checkout/templates/cart-main.phtml` + 6 key CSV (vi_VN/en_US).
Cache: `cache:flush` as secomm trước khi verify.

## AC-001 — Message render theo store locale — PASS

- Framework (`verify-dictionary.php` / `dictionary-output.txt`): **12/12 PASS**
  - vi_VN (dict 1951 entries): 6/6 render bản dịch VI.
  - en_US (dict 124 entries): 6/6 render identity — dict en_US bỏ qua row
    identity nên fallback-to-source, cùng kết quả English.
- Live vi (`/checkout/cart/`, curl): messages block render đúng VI dưới dạng
  JS unicode escapes (` ` = space — encodeJs; grep text thuần sẽ miss).
  Decode khớp 6/6 (kỷ niệm trap TASK-WXBQYZ).

## AC-002 — Live DOM vi store — PASS (`verify-cart-validation.js` / `probe-output.txt`)

| Case | Input | `validationMessage` (browser UI locale **en-US**) |
|---|---|---|
| A — valueMissing | `''` | `Vui lòng điền vào trường này.` PASS |
| B — rangeOverflow | `10001` (max=10000) | `Giá trị phải nhỏ hơn hoặc bằng 10000.` PASS (interpolate `%1`) |
| C — không kẹt form | `2` | submit OK, reload → qty persist `2` PASS |

- Listener attach: `window.secommCartQtyValidationMessages` = true PASS.
- Debug run (`debug-post-log.txt`): **0 POST `/checkout/cart/updatePost`** khi
  invalid (chỉ noise extrafee/tracker) → handler chặn submit đúng; input value
  giữ nguyên qua case A/B.

### Noise pre-existing (KHÔNG phải của change set) — bằng chứng baseline

1. **pageerror** `initConfigurableSwatchOptions` / `optionIsDisabled` /
   `isTooltipVisible`: chạy lại full flow với change set DISABLED
   (`baseline-probe.js` + `/tmp/k14-baseline-full.js`, guard flag qua
   addInitScript) → **cùng bộ pageerrors** (`baseline-full-output.txt`).
2. **Banner "Khóa biểu mẫu không hợp lệ"**: request log cho thấy POST
   `/mpextrafee/product/extrafee/` trên PDP mang form_key khác POST
   add-to-cart → server từ chối → message nằm lại session, hiển thị khi load
   cart page. Xảy ra trước khi chạm cart page, không qua path của change set.
   → Mageplaza ExtraFee noise, nếu muốn xử lý thì ticket riêng.
3. Ảnh `case-b-overflow.png` qty hiển thị "100": box qty hẹp, "10001" bị clip
   3 ký tự đầu — debug run xác nhận `inputValue()` = `10001` tại thời điểm đó.

## Regression 09-16 (QC user report: "không còn validate") — ĐÃ FIX

**Symptom:** sau change set v1, submit qty invalid trên cart page **không hiển thị
bubble nào cả** (English biến mất — nhưng VI cũng không hiện).

**Root cause (code review, không cần repro headless):** handler v1 dùng
`preventDefault()` + `setCustomValidity()` + `reportValidity()`. Per spec
`reportValidity()` fire một cancelable `invalid` event riêng (#2) — chính
capture listener này bắt lại và `preventDefault()` #2 → report của
`reportValidity()` thấy event bị cancel → **không render bubble, return false**.
Hai lớp chặn cộng lại = 0 bubble.

**Vì sao probe v1 lọt:** assert `input.validationMessage` (property — chỉ chứng
minh text set, không chứng minh bubble hiển thị) + headless Chrome không render
native bubble nên screenshot vô dụng với loại bug này.

**Fix:** handler chỉ `setCustomValidity(message)` — bỏ `preventDefault()` và
`reportValidity()` (MDN constraint validation pattern: browser fire `invalid`
trước khi render bubble → message set trong handler là cái hiển thị). Bỏ
`reportedThisRound`/rAF (browser tự report control đầu tiên).

**Verify v2 (`verify-bubble-fix.js` / `bubble-fix-output.txt`) — 10/10 PASS:**
- Instrument window-capture listener: `defaultPrevented=false` sau dispatch
  (điều kiện per-spec để browser report) + đúng **1 invalid event/round**
  (không còn double-fire).
- validationMessage VI: empty → "Vui lòng điền vào trường này.", 10001 →
  "Giá trị phải nhỏ hơn hoặc bằng 10000." (interpolate `%1`).
- Submit vẫn bị chặn khi invalid (0 POST updatePost); valid submit → qty=2
  persist (POST ×1).
- pageerror swatch = cùng bộ baseline DISABLED đã đối chứng (mục trên).

**Còn lại cho QC:** mắt thường trên browser headed xác nhận bubble VI hiển thị
(headless không render được native bubble — bằng chứng tự động đã hết giới hạn).

## AC-003 — en_US không regression — PASS framework / live BLOCKED (env regression ngoài scope)

- Framework: 6/6 identity (AC-001 en_US rows).
- **Live en store hiện không verify được**: store 2 `launchpad_en` trên local
  hiện có locale = **vi_VN** (đã từng là en_US — evidence BUG-PWP31X 09-14,
  TASK-8V7ANH 09-14 live en PASS). `?___store=launchpad_en` giờ không đổi
  `<html lang>`/static path (homepage lẫn cart page) — nguyên nhân ở data
  (`general/locale/code` scope stores), không phải cache (`cache:flush` xong
  vẫn vậy). Đây là **env regression tồn tại trước change set** — cần khôi phục
  locale en_US cho store 2 (out of scope ticket này, surface TL/DevOps).
  Change set không có code path phụ thuộc store nên rủi ro live-en = 0 thêm:
  message đến từ `__()` per-locale, đã chứng minh cả 2 locale ở framework level.

## Screenshots

- `case-a-empty.png`, `case-b-overflow.png` — trạng thái cart sau invalid
  (native bubble là browser overlay, không capture được qua CDP screenshot —
  bằng chứng text = `validationMessage` property ở probe output).

## Kết luận

Change set đạt: VI/en đúng locale, không phá submit, không đụng server logic.
Status: chờ TL review (Mode C).
