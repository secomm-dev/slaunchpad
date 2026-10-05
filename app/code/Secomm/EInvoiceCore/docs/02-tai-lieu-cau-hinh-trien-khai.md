# Tài liệu cấu hình & triển khai — Hệ thống Hóa đơn điện tử (Secomm EInvoice)

> Đối tượng đọc: DevOps, người triển khai, quản trị hệ thống.
> Phạm vi: cài đặt, cấu hình, deploy, vận hành 3 module EInvoice.
> Phiên bản tài liệu: 1.0.

---

## 1. Yêu cầu & thành phần

| Hạng mục | Yêu cầu |
|----------|---------|
| PHP | `^8.3` |
| Magento | 2.4.x (module dùng `magento/framework`, Backend, Config, Sales, Store, Ui) |
| Module phụ thuộc | `secomm/module-base`, `secomm/module-einvoice-log` |
| Tài khoản MISA | App ID, Mã số thuế (taxcode), Username, Password tích hợp MeInvoice |
| Chứng thư số | HSM (khuyến nghị) hoặc USB/soft certificate |

Thứ tự load module (đã khai báo trong `module.xml`):
`Secomm_EInvoiceLog` → `Secomm_EInvoiceCore` → `Secomm_EInvoiceMisa`.

---

## 2. Cài đặt & migration

### 2.1. Bật module & chạy migration

```bash
# Kiểm tra trạng thái module
bin/magento module:status | grep EInvoice

# Bật module (nếu chưa)
bin/magento module:enable Secomm_EInvoiceLog Secomm_EInvoiceCore Secomm_EInvoiceMisa

# Chạy schema + data patch (tạo bảng secomm_einvoice_issue_log,
# patch tỷ giá USD/VND EnsureUsdVndCurrencyRate)
bin/magento setup:upgrade

# Biên dịch DI (bắt buộc ở môi trường production)
bin/magento setup:di:compile

# Deploy static content (production)
bin/magento setup:static-content:deploy vi_VN en_US

# Xóa cache
bin/magento cache:flush
```

### 2.2. Những gì `setup:upgrade` tạo ra

| Thành phần | Mô tả |
|------------|-------|
| Bảng `secomm_einvoice_issue_log` | Lưu lịch sử phát hành (xem tài liệu kỹ thuật §4.1) |
| Data patch `EnsureUsdVndCurrencyRate` | Thêm tỷ giá `USD→VND = 25400`, `VND→USD = 1/25400` (chỉ khi thiếu/≤0); set `currency/options/allow = USD,VND` ở scope default |

> **Lưu ý:** Patch **không** ghi đè tỷ giá dương đã tồn tại. Nếu cần tỷ giá khác, cấu hình lại tại **Stores → Currency Rates** sau khi cài.

---

## 3. Cấu hình hệ thống

Vào **Admin → Stores → Configuration → Secomm → EInvoice**
(hoặc menu **EInvoice → Configuration**). URL trực tiếp:
`admin/admin/system_config/edit/section/secomm_einvoice`.

ACL cần thiết: `Secomm_EInvoiceCore::config`.
Mọi cấu hình hỗ trợ scope **Default / Website / Store** (cấu hình theo từng store view nếu cần khác nhau).

### 3.1. Group `General` — chung

| Field | Path | Loại | Mặc định | Ý nghĩa |
|-------|------|------|----------|---------|
| Enabled | `secomm_einvoice/general/enabled` | Yes/No | `No` (0) | Bật/tắt toàn bộ tính năng HĐĐT cho scope |
| Provider | `secomm_einvoice/general/provider` | select | `misa` | Nhà cung cấp HĐĐT áp dụng cho scope |

### 3.2. Group `Issuance` — phát hành

| Field | Path | Loại | Mặc định | Ý nghĩa |
|-------|------|------|----------|---------|
| Issue Trigger | `secomm_einvoice/issuance/issue_trigger` | select | `on_shipment` | `manual` = chỉ phát hành thủ công; `on_shipment` = phát hành khi tạo phiếu giao hàng đầu tiên |
| Auto Issue | `secomm_einvoice/issuance/auto_issue` | Yes/No | `Yes` (1) | Khi `No`: chỉ phát hành qua nút Issue ở Admin, không tự động dù trigger khớp |
| Auto Creditmemo Adjustment | `secomm_einvoice/issuance/auto_creditmemo_adjustment` | Yes/No | `No` (0) | Tự phát hành hóa đơn điều chỉnh khi tạo credit memo |
| Commercial Discount Enabled | `secomm_einvoice/issuance/commercial_discount_enabled` | Yes/No | `No` (0) | Hiện section CKTM trên tab đơn hàng |

> **Quan hệ logic phát hành tự động:** tự động phát hành khi giao hàng yêu cầu đồng thời: `enabled=Yes` **và** `issue_trigger=on_shipment` **và** `auto_issue=Yes` **và** đơn ở **lần giao hàng đầu tiên** (`FirstShipmentGate`).

### 3.3. Group `MISA API` (chỉ hiện khi provider = misa)

| Field | Path | Loại | Mặc định | Ghi chú |
|-------|------|------|----------|---------|
| Base URL | `secomm_einvoice/misa/base_url` | text | `https://testapi.meinvoice.vn` | **Sandbox:** `testapi.meinvoice.vn`; **Production:** `https://api.meinvoice.vn` |
| App ID | `secomm_einvoice/misa/app_id` | text | — | Lấy từ cấu hình tích hợp MeInvoice |
| Tax Code | `secomm_einvoice/misa/tax_code` | text | — | Mã số thuế công ty (header `CompanyTaxCode`) |
| Username | `secomm_einvoice/misa/username` | text | — | Tài khoản tích hợp |
| Password | `secomm_einvoice/misa/password` | obscure (mã hóa) | — | Lưu mã hóa, giải mã khi gọi API |
| Sign Type | `secomm_einvoice/misa/sign_type` | select | `2` (HSM) | Xem bảng SignType §3.5 |
| Use Preview Before Publish | `secomm_einvoice/misa/use_preview_before_publish` | Yes/No | `No` | Gọi `/unpublishview` trước khi phát hành |
| Send Email on Issue | `secomm_einvoice/misa/send_email_on_issue` | Yes/No | `No` | Gắn `IsSendEmail` khi phát hành (gửi email tự động) |
| Invoice With Code | `secomm_einvoice/misa/invoice_with_code` | Yes/No | `Yes` (1) | Hóa đơn có mã CQT (`invoiceWithCode`/`IsInvoiceCode`) |
| Invoice Calculating Machine (MTT) | `secomm_einvoice/misa/invoice_calculating_machine` | Yes/No | `No` | Hóa đơn từ máy tính tiền (`invoiceCalcu`) |
| Certificate SN | `secomm_einvoice/misa/certificate_sn` | select | — | **Chỉ hiện khi `sign_type=2`**; lấy từ `/get-certificates`, cache 24h. Bỏ trống = MeInvoice tự chọn khi chỉ có 1 chứng thư |
| Shipping Line Name | `secomm_einvoice/misa/shipping_line_name` | text | `Phí vận chuyển` | Tên dòng phí ship trên hóa đơn |
| Invoice Template | `secomm_einvoice/misa/invoice_template` | select | — | Mẫu hóa đơn mặc định khi phát hành tự động |

> **Legacy fallback:** nếu `secomm_einvoice/misa/app_id` rỗng, hệ thống đọc `secomm_einvoice/api/app_id`.

### 3.4. Group `Invoice Amount Format` — định dạng số

Map sang `OptionUserDefined` trong payload MISA. Tất cả dùng nguồn `DecimalDigits` (0–4).

| Field | Path | Mặc định | Map sang |
|-------|------|----------|----------|
| Amount Decimal Digits (VND) | `secomm_einvoice/amount_format/amount_decimal_digits` | `0` | `AmountDecimalDigits` |
| Amount OC Decimal Digits | `secomm_einvoice/amount_format/amount_oc_decimal_digits` | `0` | `AmountOCDecimalDigits` |
| Unit Price OC Decimal Digits | `secomm_einvoice/amount_format/unit_price_oc_decimal_digits` | `2` | `UnitPriceOCDecimalDigits` |
| Unit Price Decimal Digits (VND) | `secomm_einvoice/amount_format/unit_price_decimal_digits` | `2` | `UnitPriceDecimalDigits` |
| Quantity Decimal Digits | `secomm_einvoice/amount_format/quantity_decimal_digits` | `2` | `QuantityDecimalDigits` |
| Coefficient Decimal Digits | `secomm_einvoice/amount_format/coefficient_decimal_digits` | `2` | `CoefficientDecimalDigits` |
| Exchange Rate Decimal Digits | `secomm_einvoice/amount_format/exchange_rate_decimal_digits` | `0` | `ExchangRateDecimalDigits` |
| Clock Decimal Digits | `secomm_einvoice/amount_format/clock_decimal_digits` | `2` | `ClockDecimalDigits` |

`MainCurrency` cố định = `VND`. Với đơn VND, `AmountOC === Amount` nên hai field decimal VND/OC nên để giống nhau.

### 3.5. Bảng SignType

| Giá trị | Ý nghĩa | Ghi chú |
|---------|---------|---------|
| 1 | Ký bằng USB token / chứng thư mềm | |
| 2 | **HSM** (mặc định) | Cần cấu hình `Certificate SN` nếu có nhiều chứng thư |
| 3 | HSM bất đồng bộ | Hệ thống poll trạng thái sau phát hành |
| 4 | Vé không mã, không ký | |
| 5 | Hóa đơn POS, không ký | |

---

## 4. Cấu hình bằng CLI (tùy chọn)

Có thể set nhanh qua `config:set` (nhớ chạy `cache:flush` sau đó):

```bash
# Bật tính năng + provider
bin/magento config:set secomm_einvoice/general/enabled 1
bin/magento config:set secomm_einvoice/general/provider misa

# Trigger phát hành
bin/magento config:set secomm_einvoice/issuance/issue_trigger on_shipment
bin/magento config:set secomm_einvoice/issuance/auto_issue 1

# Kết nối MISA (production)
bin/magento config:set secomm_einvoice/misa/base_url https://api.meinvoice.vn
bin/magento config:set secomm_einvoice/misa/app_id "YOUR_APP_ID"
bin/magento config:set secomm_einvoice/misa/tax_code "0123456789"
bin/magento config:set secomm_einvoice/misa/username "integration_user"

# Password phải set qua giao diện Admin (field obscure) hoặc dùng --lock-env
bin/magento config:set secomm_einvoice/misa/sign_type 2

bin/magento cache:flush
```

> **Bảo mật:** không commit `app/etc/config.php`/`env.php` chứa thông tin nhạy cảm. Password được mã hóa, **không** lưu ở dạng plaintext. Tránh đặt credential MISA vào file cấu hình chia sẻ; ưu tiên nhập qua Admin hoặc biến môi trường được khóa.

---

## 5. Cron & job nền

Đảm bảo cron Magento đang chạy:

```bash
# Crontab hệ thống nên có dòng kiểu:
* * * * * php /var/www/.../bin/magento cron:run >> var/log/cron.log 2>&1

# Chạy thủ công group default (chứa job đối soát)
bin/magento cron:run --group=default
```

| Job | Lịch | Mô tả |
|-----|------|-------|
| `secomm_einvoice_reconcile` | `0 2 * * *` (02:00 hằng ngày) | Đối soát hóa đơn đã phát hành với MISA (gọi `/paging` ngày hiện tại). Thoát ngay nếu `Invoice With Code = No` ở scope default. |

> Hiện job đối soát mới chỉ **đọc** danh sách `TransactionID` từ MISA và ghi log số lượng; **chưa** tự sửa trạng thái log local (xem tài liệu kỹ thuật §6).

---

## 6. Checklist triển khai

### 6.1. Trước khi deploy

- [ ] Có đầy đủ credential MISA (App ID, taxcode, username, password) cho môi trường đích.
- [ ] Xác định môi trường: sandbox (`testapi.meinvoice.vn`) hay production (`api.meinvoice.vn`).
- [ ] Chuẩn bị chứng thư số HSM (lấy `Certificate SN` nếu có nhiều).
- [ ] Xác nhận mẫu hóa đơn (Invoice Template) và ký hiệu (InvSeries) trên MeInvoice.

### 6.2. Khi deploy

- [ ] `git pull` / deploy code.
- [ ] `bin/magento setup:upgrade`
- [ ] `bin/magento setup:di:compile`
- [ ] `bin/magento setup:static-content:deploy vi_VN en_US`
- [ ] `bin/magento cache:flush`
- [ ] Kiểm tra cron đang chạy.

### 6.3. Sau khi deploy (smoke test)

- [ ] Vào **Configuration → EInvoice**, set Base URL + credential, lưu.
- [ ] Mở dropdown **Certificate SN** — nếu kết nối OK sẽ load được danh sách chứng thư.
- [ ] Mở dropdown **Invoice Template** — load được danh sách mẫu (xác nhận token + API hoạt động).
- [ ] Vào một đơn hàng test → tab **EInvoice** → bấm **Issue** với mẫu phù hợp.
- [ ] Kiểm tra **EInvoice → EInvoice Logs**: dòng log status `success`, có `transaction_id`, `inv_no`.
- [ ] Thử **Refresh Status**, **Download PDF/XML**, **View Published**.

---

## 7. Vận hành & giám sát

### 7.1. Log file

| File | Nội dung |
|------|----------|
| `var/log/secomm_einvoice.log` | Log nghiệp vụ HĐĐT (phát hành thành công/thất bại, request/response đã redact, lỗi). |
| `var/log/system.log`, `var/log/exception.log` | Lỗi Magento chung. |

> Debug log của API client **đã ẩn** (redact) các field nhạy cảm `data`, `password`, `token`, `access_token` và cắt còn 240 ký tự.

### 7.2. Bảng `secomm_einvoice_issue_log`

Là nguồn tra cứu chính. Lọc theo `status`, `order_increment_id`, `transaction_id`. Mỗi đơn có thể có nhiều dòng (gốc, điều chỉnh, thay thế, CKTM, các lần thử lại).

### 7.3. Cache liên quan

| Cache key | TTL | Khi nào xóa |
|-----------|-----|-------------|
| `secomm_einvoice_misa_token_{storeId}` | ~23h (82800s) | Tự xóa khi 401; xóa khi đổi credential |
| `secomm_einvoice_misa_certificates_{storeId}` | 24h (86400s) | Khi thay/đổi chứng thư |
| Cache mẫu hóa đơn | ~30 phút | Bấm **Refresh** ở grid template hoặc đợi hết TTL |

Xóa toàn bộ cache HĐĐT khi đổi cấu hình kết nối: `bin/magento cache:flush`.

---

## 8. Troubleshooting

| Triệu chứng | Nguyên nhân thường gặp | Cách xử lý |
|-------------|------------------------|------------|
| Dropdown Certificate/Template trống | Sai credential, sai Base URL, hoặc lỗi mạng tới MISA | Kiểm tra `secomm_einvoice.log`; xác nhận App ID/taxcode/user/pass; `cache:flush` |
| Phát hành lỗi `InvoiceDuplicated` | RefID đã dùng (phát hành trùng) | Dùng **Replace** hoặc phát hành với revision mới (hệ thống tăng `ref_revision`) |
| Phát hành lỗi `InvoiceNumberNotContinuous` | Số hóa đơn không liên tục | Kiểm tra cấu hình series trên MeInvoice; phát hành lại sau khi xử lý |
| Lỗi tổng tiền không khớp (`InvoicePayloadTotalsValidator`) | Lệch giữa header và dòng > 0.01 | Kiểm tra cấu hình amount format, phí ship, chiết khấu; xem `request_payload` trong log |
| HTTP 401 lặp lại | Credential sai hoặc token không cấp được | Kiểm tra user/pass; token tự retry 1 lần rồi báo lỗi |
| Đơn ngoại tệ lỗi tỷ giá | Thiếu tỷ giá `directory_currency_rate` về VND | Cấu hình tại **Stores → Currency Rates** |
| Không tự phát hành khi giao hàng | Thiếu 1 trong các điều kiện auto (xem §3.2) | Kiểm tra `enabled`, `issue_trigger`, `auto_issue`, và đơn phải ở lần giao đầu tiên |

### 8.1. Phát hành lại thủ công qua tab

Mọi đơn lỗi có thể thao tác lại tại tab **EInvoice** trên trang đơn hàng (Issue/Refresh/Cancel/Replace). Không cần thao tác DB trực tiếp.

---

## 9. Phụ lục — Tổng hợp đường dẫn cấu hình

```
secomm_einvoice/general/enabled
secomm_einvoice/general/provider
secomm_einvoice/issuance/issue_trigger
secomm_einvoice/issuance/auto_issue
secomm_einvoice/issuance/auto_creditmemo_adjustment
secomm_einvoice/issuance/commercial_discount_enabled
secomm_einvoice/misa/base_url
secomm_einvoice/misa/app_id
secomm_einvoice/misa/tax_code
secomm_einvoice/misa/username
secomm_einvoice/misa/password
secomm_einvoice/misa/sign_type
secomm_einvoice/misa/use_preview_before_publish
secomm_einvoice/misa/send_email_on_issue
secomm_einvoice/misa/invoice_with_code
secomm_einvoice/misa/invoice_calculating_machine
secomm_einvoice/misa/certificate_sn
secomm_einvoice/misa/shipping_line_name
secomm_einvoice/misa/invoice_template
secomm_einvoice/amount_format/amount_decimal_digits
secomm_einvoice/amount_format/amount_oc_decimal_digits
secomm_einvoice/amount_format/unit_price_oc_decimal_digits
secomm_einvoice/amount_format/unit_price_decimal_digits
secomm_einvoice/amount_format/quantity_decimal_digits
secomm_einvoice/amount_format/coefficient_decimal_digits
secomm_einvoice/amount_format/exchange_rate_decimal_digits
secomm_einvoice/amount_format/clock_decimal_digits
```
