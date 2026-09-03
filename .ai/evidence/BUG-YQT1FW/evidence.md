# Evidence — BUG-YQT1FW (SLP-138): Shipping fee quy đổi sai VND→USD ở Store View EN

**Date**: 2026-09-03 · **Environment**: Docker `launchpad-docker-phpfpm-1` (Magento 2.4.8-p5, PHP 8.3), DB `slaunchpad-staging` (MySQL 8.4)

## 001 — Cấu hình currency thực tế (DB)

```
currency/options/base    = VND            (mọi scope)
currency/options/default = VND (VI) / USD (store 4 launchpad_en)
currency/options/allow   = USD,VND

directory_currency_rate:
  USD → VND = 25000
  VND → USD = 0.00004
```

⇒ Carrier price contract = base currency = **VND**; Magento tự quy đổi base→display lúc render.

## 002 — php -l (Docker)

```
app/code/Secomm/GiaoHangNhanh/Helper/Rate.php      — No syntax errors detected
app/code/Secomm/GiaoHangNhanh/Model/Carrier/GHN.php — No syntax errors detected
app/code/Secomm/Ahamove/Helper/Data.php            — No syntax errors detected
```

## 003 — setup:di:compile (Docker)

Constructor `Rate` thêm `LoggerInterface` ⇒ compile bắt buộc:

```
php bin/magento setup:di:compile → "Generated code and dependency injection configuration successfully." EXIT=0
```

(Lần compile đầu báo stale config — script verify 004 bắt được `Too few arguments ... 5 expected`, chạy lại compile sạch → pass.)

## 004 — Verify conversion trên từng store view (script `var/bugyqt1fw_verify.php`, gitignored)

Bootstrap Magento thật, `setCurrentStore()` từng view, gọi helper cả 2 module với phí mẫu **250,000 VND**:

```
store=default      base=VND display=VND | GHN helper: 250000 | Ahamove helper: 250000 | OLD(sim): helper=250000 -> displayed=250000 VND | NEW displayed=250000 VND
store=launchpad_en base=VND display=USD | GHN helper: 250000 | Ahamove helper: 250000 | OLD(sim): helper=10     -> displayed=0 USD       | NEW displayed=10 USD
store=fashion_vi   base=VND display=VND | GHN helper: 250000 | Ahamove helper: 250000 | OLD(sim): helper=250000 -> displayed=250000 VND | NEW displayed=250000 VND
store=fashion_en   base=VND display=VND | GHN helper: 250000 | Ahamove helper: 250000 | OLD(sim): helper=250000 -> displayed=250000 VND | NEW displayed=250000 VND
```

**Đọc kết quả**:
- **OLD (simulated)** tái hiện đúng bug SLP-138: trên `launchpad_en`, helper cũ trả `10` (đã chia 25000 — mệnh giá USD) nhưng Magento coi là VND base và quy đổi tiếp → khách thấy **0 USD**. ✓ root cause xác nhận.
- **NEW**: helper trả nguyên 250,000 (base VND) → Magento render 1 lần duy nhất → **$10.00** trên EN, **250,000 ₫** trên VI. ✓ AC-001, AC-002.
- Cả `Secomm_GiaoHangNhanh` và `Secomm_Ahamove` cho kết quả đồng nhất. ✓

## 005 — Chạy thực trên hệ thống (log)

`var/log/system.log` 03:11 (code mới đã volume-mount, dev test checkout thật):

```
[GHN Estimate Shipping Cost Error]: Services do not exist
No mapping found for address: region_id=1185, city_id=2756
```

⇒ Debug/error logging mới (`GHN.php::isDebug()` + catch log) hoạt động. Lỗi trên là **address-mapping GHN** (region/city chưa map) → carrier bị ẩn, không thuộc BUG-YQT1FW — theo dõi ticket GhnAddressMapper.

## 006 — Gaps / QC còn lại (cho TL/QC)

- **AC-003** (fallback khi thiếu rate row / exception): guard `rate > 0` + catch-log được review code; chưa mutate DB để mô phỏng thiếu row (tránh đụng data) → QC verify bằng cách xóa tạm row `USD→VND` trên staging khi checkout.
- **End-to-end checkout thật trên `launchpad_en`** cần GHN API trả rate hợp lệ (hiện chặn bởi address mapping — xem 005) → QC chạy sau khi map address.
