# BUG-9X14Y1 — Evidence (SLP-159 flash sale hết hạn)

Run: 2026-10-01, local env `slaunchpad.localhost` (file cache, MAGE_MODE=developer).
Fix: method (a) — hết hạn = mất cả section (heading chuyển vào widget `title`).

## Artifacts

- `migrate-flashsale-heading.php` — content migration (fixture + DB), assert từng bước.
- `probe-cache-lifetime.php` — CLI bootstrap probe `isSaleEnded()`/`getCacheLifetime()`.

## Migration output

```
FIXTURE: heading "Flash sale" -> widget title OK (content 39210 -> 38982 bytes)
PAGE #2: heading "Siêu sale" -> widget title OK (content 43863 -> 43635 bytes)
PAGE #8: heading "Flash sale" -> widget title OK (content 46355 -> 46127 bytes)
PAGE #10: heading "Flash sale" -> widget title OK (content 39790 -> 39562 bytes)
PAGE #13: heading "Flash sale" -> widget title OK (content 41455 -> 41227 bytes)
DB pages migrated: 4
```

## AC1 — sale_end tương lai: render đủ, heading trong widget

`curl /` sau cache:clean:

- `lp-flash` markup: 6 occurrences; `lpFlashCountdown(1792576800000)` (= 2026-10-21 17:00:00 +07, future vs now 1790826493679).
- `<h2 class="lp-flash-title">Siêu sale` — heading render TỪ widget title.
- Không còn heading PB riêng: `data-content-type="heading"..." >Siêu sale` → trống.
- Wrapper html quanh widget: `<div data-content-type="html" data-appearance="default" data-element="main" data-decoded="true">` — không còn class `!mt-5`.

## AC2 — sale_end quá khứ: không render, content khác nguyên vẹn

Page #10 (testpage2) set `sale_end="2020-01-01 00:00:00"` → cache:clean → `curl /testpage2`:

- `lp-flash` count: **0** — không có markup/JS flash sale nào trong page source (hết hạn chốt server-side, không còn flash-of-content/JS hide).
- Các section khác render bình thường: `Everyday more value`, `Shop all lower-price products` (×4), `Living, Reimagined`… — chính là content bị nuốt trước khi fix.
- Sau verify: `sale_end` khôi phục `2026-10-21 17:00:00`.

## AC3/AC4/AC6 — probe cache lifetime (CLI bootstrap)

```
sale_end=2026-10-01 12:00:00    active lifetime=4249s (isSaleEnded=0, endMillis-left=4249s)
sale_end=2026-10-21 17:00:00    active lifetime=86400s (isSaleEnded=0, endMillis-left=1750249s)
sale_end=2020-01-01 00:00:00    ENDED  lifetime=1s (isSaleEnded=1, endMillis-left=-213014951s)
sale_end=(empty)                active lifetime=47449s (isSaleEnded=0, endMillis-left=47449s)
sale_end=not-a-date             active lifetime=72649s (isSaleEnded=0, endMillis-left=72649s)
```

- Tương lai gần: lifetime = đúng giây còn lại (cap active). Tương lai xa: parent 86400 thắng. Quá khứ: ENDED + floor 1s.
- Rỗng + sai format: midnight kế tiếp, active, không lỗi (47449s ≈ 13.2h đến 00:00 +07).

## AC5 — visual parity

- CSS compiled sau `npm run build`: `.lp-flash-head{...gap:calc(var(--spacing) * 5)...}` (20px mobile) + `lg` gap-6 row — giữ rhythm cũ heading→pill 20px (trước là wrapper `!mt-5`).
- `.lp-flash-title` giữ nguyên type scale v4.9.9 (20/28 mobile → 36/40 lg #293e2d) — trùng scale heading PB bị bỏ.
- Eyeball desktop/mobile theo design: QC xác nhận ở bước QC.

## Regression đã nghĩ tới

- Rule desktop `row:has(.lp-flash) h2[data-content-type='heading']` (homepage.css ~407) giữ nguyên — content chưa migrate (nếu có) vẫn style đúng.
- Widget không title: hết hạn render trống nhưng heading PB riêng còn lại (dangling) — docblock template đã ghi rõ title là layout khuyến nghị.
- PB admin save round-trip: directive widget chuẩn (thêm `title` attr), wrapper html không class — QC test 1 lần save + re-render.
- Cache: block_html + full_page đã clean sau migrate; lifetime cap bảo đảm block hết hạn đúng lúc sale kết thúc.
