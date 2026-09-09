# RESULTS — BUG-1N8XC8 (ext SLP-112) — Translate TableRate `{{delivery_days}}` suffix

**Ngày:** 2026-09-09 · **Mode:** C · **Layer:** module (`Launchpad_MageplazaTranslate`) — đúng layer theo LL-0011 (theme layer dead trên checkout luma-scope; module CSV load ở mọi area/theme).

## Change

- `app/code/Launchpad/MageplazaTranslate/i18n/vi_VN.csv` — +2 phrase: `"%1 day(s)","%1 ngày"`, `"%1 - %2 day(s)","%1 - %2 ngày"`
- `app/code/Launchpad/MageplazaTranslate/i18n/en_US.csv` — +2 identity row (BR-001)
- Không đụng vendor `Mageplaza_TableRateShipping`, không đổi logic `collectRates()`/`getDeliveryDays()`, không đổi DB/config.

## Verify — dictionary-level, per-store (1 process/store)

Script: `.ai/runtime/evidence/BUG-1N8XC8/verify-day-phrases.php` (pattern BUG-GJT6C1; **không** setDesignTheme → chứng minh fix theme-independent, sống trên cả checkout luma-scope).
Chạy: `sudo -u secomm php verify-day-phrases.php <store> <expectSingle> <expectRange> <expectRegress>` sau `cache:flush`.

| Store | Check | Got | Expect | |
|---|---|---|---|---|
| `default` (vi_VN) | single `__('%1 day(s)', 3)` | `3 ngày` | `3 ngày` | PASS |
| `default` (vi_VN) | range `__('%1 - %2 day(s)', 2, 5)` | `2 - 5 ngày` | `2 - 5 ngày` | PASS |
| `default` (vi_VN) | regress `__('Delivery Date')` | `Ngày giao hàng` | `Ngày giao hàng` | PASS |
| `launchpad_en` (en_US) | single | `3 day(s)` | `3 day(s)` | PASS |
| `launchpad_en` (en_US) | range | `2 - 5 day(s)` | `2 - 5 day(s)` | PASS |
| `launchpad_en` (en_US) | regress | `Delivery Date` | `Delivery Date` | PASS |

**Kết quả: 6/6 PASS** (exit 0 cả 2 process) — AC-001 ✅, AC-002 ✅, AC-004 (dictionary-level) ✅.

## Đối chiếu AC

- **AC-001** (vi resolve "ngày") — PASS tự động, bảng trên.
- **AC-002** (en identity) — PASS tự động, bảng trên.
- **AC-003** (storefront OSC Order Summary hiển thị "… ngày") — **chờ manual QC trên demo env** (`slaunchpad-demo.secomm.vn` — nơi chụp screenshot SLP-112; local TableRate `active=0`, cần cart + rate có delivery days). Cần QC: load cart có item, chọn phương thức Giao hàng - Tiêu chuẩn, kiểm tra Order Summary dòng Shipping hiển thị "3 ngày" (hoặc range "x - y ngày").
- **AC-004** (regression) — PASS dictionary-level (phrase `Delivery Date` resolve đúng 2 store); regression UI đầy đủ gộp vào lần QC browser chung.

## Deploy đã chạy

- `sudo -u secomm php bin/magento cache:flush` — xong trước verify (bước bắt buộc: dictionary PHP được cache).
- `setup:static-content:deploy -f vi_VN en_US` — đã chạy, **không ảnh hưởng fix này**: kiểm chứng `pub/static/frontend/<theme>/<locale>/js-translation.json` (2.7KB) chỉ chứa phrase scan từ JS, KHÔNG chứa dictionary CSV module (không có cả "Ngày giao hàng" đã deploy từ 09-07) → phrase PHP-side của fix không thuộc file này; bước rm theo README §Maintenance chỉ cần khi phrase JS thay đổi.

## Ghi chú ngoài scope

- Trong lúc thực thi, CSV có thêm 1 dòng từ process khác ("Please choose at least one option for each require extra fee" — ExtraFee) — edit song song của team, giữ nguyên, không thuộc ticket này; SCD hiện tại chưa gồm phrase đó (người sửa sẽ deploy).

## Ghi chú

- Screenshot SLP-112 hiển thị title DB "Tiêu chuẩn {{delivery_days}}" + carrier title "Giao hàng" (admin store data) — phần text này không qua `__()`, không thuộc scope i18n; chỉ placeholder là translatable.
- Cùng dictionary này phục vụ cả case range (2 rate có delivery khác nhau trong 1 method) — đã cover cả 2 shape của `getDeliveryDays()`.
