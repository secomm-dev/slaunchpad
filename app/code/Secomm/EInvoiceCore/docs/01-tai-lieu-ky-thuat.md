# Tài liệu kỹ thuật — Hệ thống Hóa đơn điện tử (Secomm EInvoice)

> Đối tượng đọc: Developer, Tech Lead, người maintain/mở rộng module.
> Phạm vi: 3 module `Secomm_EInvoiceCore`, `Secomm_EInvoiceMisa`, `Secomm_EInvoiceLog`.
> Phiên bản tài liệu: 1.0 — Cập nhật lần đầu.

---

## 1. Tổng quan

Hệ thống hóa đơn điện tử (HĐĐT) được tách thành **3 module độc lập** theo nguyên tắc tách biệt trách nhiệm (separation of concerns), cho phép thay thế nhà cung cấp (provider) mà không sửa lớp nghiệp vụ lõi:

| Module | Vai trò | Mô tả |
|--------|---------|-------|
| `Secomm_EInvoiceCore` | **Orchestration / Khung nghiệp vụ** | Định nghĩa hợp đồng (interface), pool theo provider, cấu hình chung, UI trang đơn hàng, service điều phối trung tâm. **Không** tự gọi API nhà cung cấp. |
| `Secomm_EInvoiceMisa` | **Provider MISA MeInvoice** | Triển khai cụ thể cho MISA: API client, issuer, mapper payload, chứng thư số/HSM, cron đối soát. Đăng ký vào các pool của Core với key `misa`. |
| `Secomm_EInvoiceLog` | **Lưu trữ & hiển thị log** | Bảng `secomm_einvoice_issue_log`, model, repository, lưới (grid) admin. Là **nguồn sự thật** về lịch sử phát hành. |

### 1.1. Quan hệ phụ thuộc

```
Secomm_EInvoiceMisa  ──depends──▶  Secomm_EInvoiceCore  ──depends──▶  Secomm_EInvoiceLog
        │                                                                     ▲
        └──────────────────────── ghi log qua IssueLogRepository ─────────────┘
```

- `EInvoiceCore` require: `secomm/module-base`, `secomm/module-einvoice-log`, Magento Backend/Config/Sales/Store. PHP `^8.3`.
- `EInvoiceMisa` sequence sau `EInvoiceCore` (`etc/module.xml`).
- `EInvoiceLog` chỉ phụ thuộc Magento Backend/Ui/Sales/Store.

### 1.2. Pattern kiến trúc

- **Strategy + Pool theo provider:** mỗi điểm mở rộng là một interface có một "pool". Pool phân giải implementation theo `provider` cấu hình ở phạm vi store (`misa`, `viettel`, ...).
- **Orchestrator tập trung:** `IssueInvoiceService` (Core) là điểm vào duy nhất cho mọi thao tác phát hành/hủy/điều chỉnh và là nơi duy nhất ghi/cập nhật `IssueLog`.
- **Provider-agnostic contracts:** Core không biết MISA; mọi thứ qua interface trong `Secomm\EInvoiceCore\Api`.

```
   Sự kiện Magento / Thao tác Admin
                │
                ▼
     IssueInvoiceService  (Core — điều phối + ghi log)
                │
         ┌──────┴─────────────────────────────────┐
         ▼                                        ▼
   *RequestBuilderPool                      InvoiceIssuerPool / InvoiceDocumentServicePool
         │                                        │
         ▼                                        ▼
   Mapper (EInvoiceMisa)                    MisaInvoiceIssuer / MisaInvoiceDocumentService
         │                                        │
         └──────────► payload InvoiceData ────────┘
                                │
                                ▼
                    MisaApiClient ──HTTP──▶ MISA MeInvoice API
                                │
                                ▼
                  IssueLogRepository (EInvoiceLog) + Logger
```

---

## 2. Module `Secomm_EInvoiceCore`

### 2.1. Service điều phối — `Model/Service/IssueInvoiceService.php`

Lớp trung tâm, đồng bộ (synchronous), chịu trách nhiệm điều phối và lưu `IssueLog`. Phụ thuộc các pool, `IssueLogRepository`, `InvSeriesPublishLock`, `PublishRetryPolicy`, `OriginInvoiceResolverInterface`, `LoggerInterface`.

#### Các phương thức public chính

| Phương thức | Chữ ký | Mục đích |
|-------------|--------|----------|
| `schedule` | `schedule(int $orderId, bool $force = false, array $context = []): IssueLog` | Điểm vào phát hành chính (observer + nội bộ) |
| `issueNow` | `issueNow(int $orderId, array $context = []): IssueLog` | Phát hành cưỡng bức từ Admin = `schedule(orderId, true, context)` |
| `processLog` | `processLog(IssueLog $log): IssueLog` | Xử lý log đang pending/failed qua issuer |
| `getLatestLogForOrder` | `(int $orderId): ?IssueLog` | Log mới nhất theo đơn |
| `getIssuedLogForOrder` | `(int $orderId): ?IssueLog` | Log thành công có `transaction_id` |
| `getOrderTabLog` | `(int $orderId): ?IssueLog` | Log đã phát hành nếu có, ngược lại log mới nhất |
| `shouldReactToTrigger` | `(string $trigger, ?int $storeId): bool` | Kiểm tra bật + trigger khớp cấu hình |
| `refreshStatus` | `(int $orderId): IssueLog` | Truy vấn trạng thái từ provider |
| `downloadInvoice` | `(int $orderId, string $type): array` | Trả `{filename, mime, contents}` |
| `cancelForOrder` | `(int $orderId, string $reason): IssueLog` | Hủy hóa đơn |
| `sendInvoiceEmailToCustomer` | `(int $orderId, ?string $receiverEmail, ?string $receiverName, ?string $ccEmail, ?string $replyEmail): IssueLog` | Gửi email hóa đơn |
| `cancelForOrderSafe` | `(int $orderId, string $reason): void` | Hủy "an toàn" — nuốt `LocalizedException`, chỉ log |
| `issueAdjustment` | `(int $creditmemoId): IssueLog` | Hóa đơn điều chỉnh (ReferenceType=2) |
| `issueReplace` | `(int $orderId, array $context = []): IssueLog` | Hóa đơn thay thế (ReferenceType=1) |
| `issueCommercialDiscount` | `(int $orderId, array $discount): IssueLog` | Chiết khấu thương mại — CKTM (ReferenceType=5) |
| `issueAdjustmentSafe` | `(int $creditmemoId): void` | Điều chỉnh "an toàn", nuốt lỗi |
| `getInvoiceStatusLabel` | `(int $orderId): ?string` | Nhãn trạng thái MeInvoice từ log |

#### Luồng phát hành chuẩn (`schedule` → `processLog` → `runIssuer`)

1. **Phân giải store:** `OrderStoreIdProvider::getStoreIdByOrderId($orderId)`.
2. **Gate bật/tắt:** nếu không `$force` và `!Config::isEnabled($storeId)` → ném `LocalizedException`.
3. **Idempotency:** nếu không `$force` và đã có log `success` → trả về log đó (không phát hành lại).
4. **Tiếp tục lần dở dang:** nếu có log `pending/processing` (`findOpenByOrderId`) → `processLog()` log đó (không tạo dòng mới).
5. **Build request:** set `context['ref_revision']` từ `countByOrderId` nếu thiếu → `IssueRequestBuilderPool::get($storeId)->build($orderId, $context)`.
6. **Tạo log:** status `pending`, lưu `ref_id`, `inv_series`, `invoice_template_id`, `parent_log_id`, `reference_type`, `request_payload` (JSON). `setLastAction(ACTION_ISSUE)`.
7. **`processLog`:** kiểm tra lại bật/tắt → set `processing` → phân giải `inv_series` → khóa **`InvSeriesPublishLock::execute($invSeries, callback)`** (khóa 120s, tuần tự hóa publish theo ký hiệu) → `runIssuer`.
8. **`runIssuer`:**
   - `InvoiceIssuerPool::get($storeId)->issue($request)` → `IssueResultInterface`.
   - Lưu `raw_response`; trích `meta` (`ref_id`, `transaction_id`, `inv_no`, `inv_series`, `invoice_template_id`, `sign_type`).
   - **Thành công:** status `success`, set `external_id`, `issued_at`, `recordActionOutcome(action, true)`.
   - **Thất bại:** status `failed`; nếu lỗi `InvoiceDuplicated` / `InvoiceNumberNotContinuous` → gắn gợi ý retry qua `PublishRetryPolicy::mayRetry()`; `recordActionOutcome(action, false, message)`; `logger->error('EInvoice issue failed', ...)`.
   - `issueLogRepository->save($log)`.

#### Thao tác sau phát hành

- **refreshStatus:** `InvoiceDocumentServicePool::getStatus()` → merge vào `response_payload['status_check']`, cập nhật `invoice_status`/`inv_no`.
- **cancel / sendEmail:** gọi document service → merge response → cập nhật log; ném lỗi nếu thất bại.
- **adjustment / replace / commercial discount:** build qua builder pool tương ứng + `OriginInvoiceResolver` → tạo log mới → khóa series → `runIssuer`.

#### Cơ chế chống lỗi / điều khiển đồng thời

| Thành phần | Vai trò |
|------------|---------|
| `InvSeriesPublishLock` | Khóa theo ký hiệu hóa đơn `secomm_einvoice_inv_series_{series}` (120s) — đảm bảo không phát hành song song cùng một series (giữ tính liên tục số hóa đơn). |
| `PublishRetryPolicy` | Chặn tự động retry với mã lỗi `InvoiceDuplicated`, `InvoiceNumberNotContinuous` trừ khi cấp `RefID` mới (`ref_revision`). |
| `FirstShipmentGate` | `order->getShipmentsCollection()->getSize() === 1` — chỉ phát hành ở lần giao hàng đầu tiên. |
| `OrderStoreIdProvider` | Order → store ID qua `OrderRepositoryInterface`. |

### 2.2. Controllers Admin — `Controller/Adminhtml/Order/`

- **Front name route:** `secomm_einvoice` (`etc/adminhtml/routes.xml`).
- **URL pattern:** `admin/secomm_einvoice/order/{action}`.
- **ACL chung:** `Secomm_EInvoiceCore::issue`.

| Controller | HTTP | Action | Tham số | Gọi |
|------------|------|--------|---------|-----|
| `Issue` | POST | `issue` | `order_id`*, `invoice_template_id` | `issueNow($orderId, {template_id})` |
| `CancelInvoice` | POST | `cancelinvoice` | `order_id`*, `reason` | `cancelForOrder($orderId, $reason)` |
| `Replace` | POST | `replace` | `order_id`* | `issueReplace($orderId)` |
| `CommercialDiscount` | POST | `commercialdiscount` | `order_id`*, `list_no`, `list_date`, `invoice_note`, `amount`, `vat_amount` | `issueCommercialDiscount($orderId, $discount)` |
| `RefreshStatus` | GET | `refreshstatus` | `order_id`* | `refreshStatus($orderId)` |
| `SendEmail` | POST | `sendemail` | `order_id`*, `receiver_email`, `receiver_name` | `sendInvoiceEmailToCustomer(...)` |
| `ViewPublished` | GET | `viewpublished` | `order_id`* | `getPublishedView(transactionId, storeId)` → redirect URL ngoài |
| `Preview` | GET | `preview` | `order_id`* | `documentService->previewUnpublished()` (MISA-only) |
| `Download` | GET | `download` | `order_id`*, `type` (`pdf`/`xml`) | `downloadInvoice()` → stream file |

`*` = bắt buộc. Phần lớn controller redirect về `sales/order/view` với `active_tab=order_einvoice`. Xử lý lỗi: `LocalizedException` → flash error cụ thể; `Throwable` → flash error chung.

### 2.3. Cấu hình — `Model/Config.php`

| Hằng số | Đường dẫn config | Getter | Mặc định |
|---------|------------------|--------|----------|
| `XML_PATH_ENABLED` | `secomm_einvoice/general/enabled` | `isEnabled()` | `0` |
| `XML_PATH_PROVIDER` | `secomm_einvoice/general/provider` | `getProvider()` | `misa` |
| `XML_PATH_ISSUE_TRIGGER` | `secomm_einvoice/issuance/issue_trigger` | `getIssueTrigger()` | `on_shipment` |
| `XML_PATH_AUTO_ISSUE` | `secomm_einvoice/issuance/auto_issue` | `isAutoIssue()` | `1` |
| `XML_PATH_AUTO_CREDITMEMO_ADJUSTMENT` | `secomm_einvoice/issuance/auto_creditmemo_adjustment` | `isAutoCreditmemoAdjustment()` | `0` |
| `XML_PATH_COMMERCIAL_DISCOUNT_ENABLED` | `secomm_einvoice/issuance/commercial_discount_enabled` | `isCommercialDiscountEnabled()` | `0` |

- Provider constants: `PROVIDER_MISA = 'misa'`, `PROVIDER_VIETTEL = 'viettel'`.
- Trigger constants: `TRIGGER_MANUAL = 'manual'`, `TRIGGER_ON_SHIPMENT = 'on_shipment'`.
- Tất cả getter dùng scope `ScopeInterface::SCOPE_STORE`.

### 2.4. Observers — `Observer/`

| Observer | Event | Hành vi |
|----------|-------|---------|
| `OrderShipmentSaveAfter` | `sales_order_shipment_save_after` | Trigger `on_shipment`; qua `FirstShipmentGate` (đúng 1 shipment); gọi `schedule()` nếu auto-issue bật. |
| `OrderCreditmemoSaveAfter` | `sales_order_creditmemo_save_after` | Nếu bật + `auto_creditmemo_adjustment` → `issueAdjustmentSafe($creditmemoId)`. Lỗi chỉ log info, không ném. |
| `OrderCancelAfter` | `order_cancel_after` | `cancelForOrderSafe()` với lý do gồm increment id đơn. |

### 2.5. ViewModel & Block (UI trang đơn hàng)

**`ViewModel/Order/Einvoice.php`** — quy tắc hiển thị tab:

| Phương thức | Logic |
|-------------|-------|
| `canShow($storeId)` | EInvoice bật cho store |
| `canIssue($orderId, $storeId)` | bật + chưa có log `success` |
| `canCancel($orderId, $storeId)` | bật + có log đã phát hành |
| `hasIssuedInvoice($orderId)` | log đã phát hành có transaction id |
| `canIssueCommercialDiscount($orderId, $storeId)` | bật + cấu hình CKTM + có HĐ đã phát hành |
| `getTemplateOptions($storeId)` / `getDefaultTemplateId($storeId)` | từ `TemplateOptionsProviderPool` |

**`Block/Adminhtml/Order/View/Einvoice.php`** — implements `TabInterface`, tab id `order_einvoice`. Cung cấp URL các action, helper hiển thị (nhãn trạng thái, định dạng thời gian, nhãn reference type/action), email mặc định từ billing, JSON khởi tạo accordion.

### 2.6. Tích hợp UI Admin

- **Layout** `view/adminhtml/layout/sales_order_view.xml`: nạp CSS `einvoice-order.css`, inject init JS, thêm tab `order_einvoice` vào `sales_order_tabs`.
- **Template** `view/adminhtml/templates/order/einvoice.phtml`: chỉ render khi `canShow()`. Gồm header tóm tắt, cảnh báo lỗi theo action, và các section accordion (collapsible, lưu trạng thái localStorage): `details`, `issue`, `documents`, `email`, `commercial_discount`, `replacement`, `cancel`. Mọi form POST đều có `form_key`.
- **JS:**
  - `activate-einvoice-tab.js`: tự kích hoạt tab khi URL có `active_tab=order_einvoice` (xử lý vấn đề trùng id tab).
  - `einvoice-accordion.js`: widget lưu trạng thái mở/đóng theo đơn ở key `secomm_einvoice_accordion_order_{orderId}`.

### 2.7. Menu — `etc/adminhtml/menu.xml`

| Title | Action | ACL |
|-------|--------|-----|
| EInvoice (cha) | — | `Secomm_EInvoiceCore::einvoice` |
| EInvoice Logs | `secomm_einvoice_log/issuelog/index` | `Secomm_EInvoiceLog::issue_log` |
| Configuration | `adminhtml/system_config/edit/section/secomm_einvoice` | `Secomm_EInvoiceCore::config` |

---

## 3. Module `Secomm_EInvoiceMisa`

### 3.1. Đăng ký DI vào Core (`etc/di.xml`)

| Pool / Interface | Implementation MISA |
|------------------|---------------------|
| `OriginInvoiceResolverInterface` (preference) | `Model\Service\OriginInvoiceResolver` |
| `InvoiceIssuerPool` | `Model\Issuer\MisaInvoiceIssuer` |
| `IssueRequestBuilderPool` | `Model\Mapper\OrderIssueRequestBuilder` |
| `AdjustmentRequestBuilderPool` | `Model\Mapper\CreditmemoIssueRequestBuilder` |
| `CommercialDiscountRequestBuilderPool` | `Model\Mapper\CommercialDiscountIssueRequestBuilder` |
| `TemplateOptionsProviderPool` | `Model\Template\MisaTemplateOptionsProvider` |
| `InvoiceDocumentServicePool` | `Model\Document\MisaInvoiceDocumentService` |
| `Provider` source | thêm option `misa → MISA` |

Logger `Secomm\EInvoiceLog\Logger\Logger` được inject vào `MisaApiClient`, `MisaInvoiceIssuer`, `CertificateListProvider`, `InvoiceTemplateListProvider`, `MisaDownloadResponseParser`.

### 3.2. API Client — `Model/Client/`

**URL** (`MisaConfig`):

| Getter | URL |
|--------|-----|
| `getBaseUrl()` | `secomm_einvoice/misa/base_url`, mặc định `https://testapi.meinvoice.vn` |
| `getAuthTokenUrl()` | `{base}/api/integration/auth/token` |
| `getInvoiceApiBaseUrl()` | `{base}/api/integration/invoice` |

**Xác thực:**
- `getIntegrationToken()`: POST `/api/integration/auth/token` với `{appid, taxcode, username, password}` (password giải mã qua `EncryptorInterface`). Thành công khi `response['success'] === true`, token ở `data`.
- `MisaTokenProvider`: cache key `secomm_einvoice_misa_token_{storeId}`, TTL **82800s** (~23h). Khi HTTP 401 → `executeWithToken()` xóa cache và retry **1 lần**.

**Header request đã xác thực:**
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
CompanyTaxCode: {tax_code}
```

**Các endpoint chính** (base `{base}/api/integration/invoice`):

| Method | Endpoint | Mục đích |
|--------|----------|----------|
| POST | `/auth/token` | Lấy token |
| GET | `/templates` | Danh sách mẫu hóa đơn |
| GET | `/get-certificates` | Danh sách chứng thư HSM |
| POST | `/unpublishview` | Xem trước (preview) trước phát hành |
| POST | `/` (base) | **Phát hành HSM** — `{SignType, InvoiceData, PublishInvoiceData, CertificateSN?}` |
| POST | `/publishview` | Xem hóa đơn đã phát hành |
| POST | `/Download` | Tải file (PDF/XML) |
| POST | `/status` | Truy vấn trạng thái (`inputType` 1=TransactionID, 2=RefID) |
| GET | `/paging` | Phân trang đối soát |
| POST | `/cancel` | Hủy — `{TransactionID, InvSeries, CancelReason}` |
| POST | `/sendemail` | Gửi email |

`MisaApiQueryBuilder` dựng query: `invoiceWithCode` (mặc định `true`), `invoiceCalcu` (mặc định `false`). Timeout 30s (60s cho binary). Log debug ẩn (redact) `data`, `password`, `token`, `access_token`.

### 3.3. Issuer & luồng phát hành — `Model/Issuer/`

**`MisaInvoiceIssuer::issue()`:**
1. `InvoicePayloadTotalsValidator::assertConsistent()` — kiểm tra tổng tiền.
2. Preview tùy chọn (nếu `usePreviewBeforePublish`).
3. `publishInvoiceHsm` qua `executeWithToken`.
4. `PublishResponseValidator::assertPublishSuccess()`.
5. Chuẩn hóa kết quả → `TransactionID`, `InvNo`.
6. Poll trạng thái nếu cần (SignType 3/6, tối đa 5 lần, nghỉ 500ms).
7. Trả `IssueResult` (success, `externalId=TransactionID`, raw response kèm meta).

**`InvoicePayloadTotalsValidator`** (dung sai 0.01):
- Header: `TotalAmountOC == TotalAmountWithoutVATOC + TotalVATAmountOC`.
- Dòng: tổng `InvoiceDetail` (bỏ `ItemType=4`) + `FeeInfo` − `TotalDiscountAmountOC` phải khớp header.

**`PublishResponseValidator`:** kiểm `success` top-level; parse `publishInvoiceResult`; item có `ErrorCode` khác rỗng và khác `'0'` → ném lỗi.

### 3.4. Mappers — `Model/Mapper/`

| Mapper | Chuyển đổi | ReferenceType / Tên HĐ |
|--------|-----------|------------------------|
| `OrderToIssueRequest` | Order → `InvoiceData` | 0 — gốc; `mapReplacement()` → 1, "Hóa đơn thay thế" |
| `CreditmemoToIssueRequest` | Credit memo → HĐ điều chỉnh | 2 — "Hóa đơn điều chỉnh" |
| `CommercialDiscountToIssueRequest` | Chiết khấu → HĐ CKTM | 5 — "Hóa đơn chiết khấu thương mại" (1 dòng âm `CKTM`) |
| `OrderInvoiceAdjustments` | Căn chỉnh tổng tiền: dòng phí ship (`SHIPPING`), chiết khấu giỏ hàng, phí làm tròn `FeeInfo` "Điều chỉnh", dòng điều chỉnh/hoàn tiền cho credit memo | — |
| `SalesInvoiceContext` | Tiền tệ, tỷ giá → VND, phương thức thanh toán | — |
| `OrgInvoiceReferenceNormalizer` | Tách `InvSeries` → `OrgInvTemplateNo` + `OrgInvSeries`, gán các trường tham chiếu hóa đơn gốc (ND123/ND51) | — |

**Seed RefID** (GUID SHA-256 tất định):
- Gốc: `secomm-einvoice:{storeId}:{incrementId}:{refRevision}`
- Điều chỉnh: `secomm-einvoice-adj:{storeId}:{incrementId|entityId}`
- CKTM: `secomm-einvoice-cktm:{storeId}:{incrementId}:{refRevision}`

### 3.5. Chứng thư số / HSM — `Model/Certificate/`

- `HsmCertificate`: value object (`CertificateSN`, `UserName`, `AuthOrganizeName`, `EffectiveTime`, `ExpirationTime`); `getLabel()` = `{AuthOrganizeName} — {UserName} — {CertificateSN}`.
- `CertificateListProvider`: GET `/get-certificates`, cache `secomm_einvoice_misa_certificates_{storeId}` TTL **86400s** (24h); lỗi → trả `[]`.
- `Config\Source\CertificateSn`: dropdown `certificate_sn`, chỉ hiện khi `sign_type=2`. Khi phát hành, `CertificateSN` được đưa vào body POST khi SignType=2 và SN có cấu hình.

### 3.6. Cron — `etc/crontab.xml` + `Cron/ReconcileIssuedInvoices.php`

| Thuộc tính | Giá trị |
|------------|---------|
| Job | `secomm_einvoice_reconcile` |
| Lịch | `0 2 * * *` (02:00 hằng ngày, giờ server) |
| Group | `default` |

Logic: thoát ngay nếu `isInvoiceWithCode()=false` ở scope default; lấy token; gọi `/paging` với `fromDate=toDate=hôm nay`, `page=1`, `pageSize=100`; trích danh sách `TransactionID`; log `remote_count`.

> **Lưu ý kỹ thuật:** Cron hiện là **bản nháp đối soát** — `IssueLogRepositoryInterface` được inject nhưng **chưa dùng** để so khớp với log local. Đây là điểm cần hoàn thiện (xem §6).

### 3.7. Setup patch — `EnsureUsdVndCurrencyRate`

Đảm bảo có tỷ giá `directory_currency_rate` cho HĐĐT (MeInvoice `MainCurrency = VND`, đơn ngoại tệ cần tỷ giá quy đổi):
- `USD → VND = 25400.0`, `VND → USD = 1/25400` (chỉ set nếu thiếu hoặc ≤ 0).
- `currency/options/allow = USD,VND` ở scope default.
- Không ghi đè tỷ giá dương đã có.

---

## 4. Module `Secomm_EInvoiceLog`

### 4.1. Schema DB — `etc/db_schema.xml`

**Bảng `secomm_einvoice_issue_log`** (InnoDB) — mỗi dòng = **một lần thử phát hành** cho một đơn.

| Cột | Kiểu | Null | Mặc định | Ghi chú |
|-----|------|------|----------|---------|
| `entity_id` | int unsigned, identity | NO | auto | PK |
| `order_id` | int unsigned | NO | — | Magento Order ID |
| `order_increment_id` | varchar(50) | NO | — | Số đơn hàng |
| `store_id` | smallint unsigned | NO | 0 | Store ID |
| `status` | varchar(32) | NO | `pending` | `pending\|processing\|success\|failed\|cancelled` |
| `external_id` | varchar(255) | YES | — | Tham chiếu hệ thống ngoài |
| `ref_id` | varchar(64) | YES | — | RefID gửi đi |
| `transaction_id` | varchar(64) | YES | — | MeInvoice Transaction ID |
| `inv_series` | varchar(32) | YES | — | Ký hiệu hóa đơn |
| `inv_no` | varchar(32) | YES | — | Số hóa đơn MeInvoice cấp |
| `invoice_template_id` | varchar(64) | YES | — | ID mẫu hóa đơn |
| `sign_type` | smallint unsigned | YES | — | SignType khi phát hành |
| `reference_type` | smallint unsigned | YES | — | `0` gốc, `1` thay thế, `2` điều chỉnh, `5` CKTM |
| `parent_log_id` | int unsigned | YES | — | Log cha |
| `creditmemo_id` | int unsigned | YES | — | Credit memo liên quan |
| `invoice_status` | varchar(64) | YES | — | InvoiceStatus MeInvoice gần nhất |
| `request_payload` | text | YES | — | JSON request |
| `response_payload` | text | YES | — | JSON response |
| `error_message` | text | YES | — | Lỗi chính (mirror action lỗi gần nhất) |
| `last_action` | varchar(32) | YES | — | `issue\|cancel\|refresh_status\|adjustment\|send_email` |
| `last_action_at` | timestamp | YES | — | Thời điểm action gần nhất |
| `action_errors` | text | YES | — | JSON map action → lỗi |
| `issued_at` | timestamp | YES | — | Thời điểm phát hành thành công |
| `cancelled_at` | timestamp | YES | — | Thời điểm hủy |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP (on update) | |

**Index:** `order_id`; `(order_id, status)`; `transaction_id`. **Không có khóa ngoại** — quan hệ logic.

### 4.2. Model — `Model/IssueLog.php`

**Status:** `STATUS_PENDING|PROCESSING|SUCCESS|FAILED|CANCELLED`.
**Action:** `ACTION_ISSUE|CANCEL|REFRESH_STATUS|ADJUSTMENT|SEND_EMAIL`.
**Reference type:** `ORIGINAL=0`, `REPLACE=1`, `ADJUSTMENT=2`, `COMMERCIAL_DISCOUNT=5`.

Phương thức đáng chú ý:
- `getActionErrors(): array<string,string>` — giải mã `action_errors`.
- `setActionError($action, $message)` — cập nhật lỗi theo action + đồng bộ `error_message` (`syncPrimaryErrorMessage()`).
- `setLastAction($action, $at)`.
- `getReferenceTypeLabel()` / `getActionLabel()` — nhãn hiển thị (null → Original; không xác định → chuỗi số).

Phần lớn cột truy cập qua `getData()/setData()` kế thừa.

### 4.3. Repository — `Model/IssueLogRepository.php` (`Api/IssueLogRepositoryInterface`)

| Phương thức | Hành vi |
|-------------|---------|
| `save(IssueLog)` | Lưu qua resource model |
| `findLatestByOrderId($orderId)` | Log mới nhất (mọi status) |
| `findOpenByOrderId($orderId)` | Mới nhất `pending`/`processing` |
| `findSuccessfulByOrderId($orderId)` | Mới nhất `success` |
| `countByOrderId($orderId)` | Đếm số log của đơn |
| `findByRefId($refId)` | Mới nhất khớp RefID |

Tất cả sắp xếp `entity_id DESC`, page size 1.

### 4.4. UI admin

- **Grid** `secomm_einvoice_issue_log_listing.xml`: ACL `Secomm_EInvoiceLog::issue_log`, sort mặc định `entity_id DESC`, không có mass action. Cột: `entity_id`, `order_increment_id` (link sang đơn), `store_id`, `reference_type`, `parent_log_id`, `status`, `external_id`, `transaction_id`, `ref_id`, `inv_series`, `inv_no`, `invoice_template_id`, `last_action`, `error_message`, `issued_at`, `created_at`, action **View**.
- **Form** `secomm_einvoice_issue_log_form.xml`: chỉ đọc; fieldset General + Payload (`error_message`, `request_payload`, `response_payload`); nút **Back**.
- **Logger** (`etc/di.xml`): `Secomm\EInvoiceLog\Logger\Logger` ghi file `/var/log/secomm_einvoice.log`.

### 4.5. Ai ghi log?

**Chỉ duy nhất `IssueInvoiceService` (Core)** tạo/cập nhật dòng log (qua `IssueLogFactory` + `IssueLogRepository`). Các nơi đọc (không ghi): ViewModel/Block order tab, `OriginInvoiceResolver` (Misa), cron đối soát.

---

## 5. Điểm mở rộng (Extension Points)

Để **thêm provider mới** (ví dụ Viettel), thực hiện trong module provider mới:

1. Triển khai các interface trong `Secomm\EInvoiceCore\Api`:

| Interface | Phương thức | Pool |
|-----------|-------------|------|
| `IssueRequestBuilderInterface` | `build(int $orderId, array $context = []): IssueRequestInterface` | `IssueRequestBuilderPool` |
| `AdjustmentRequestBuilderInterface` | `build(int $creditmemoId, array $context = []): IssueRequestInterface` | `AdjustmentRequestBuilderPool` |
| `CommercialDiscountRequestBuilderInterface` | `build(int $orderId, array $discount, array $context = []): IssueRequestInterface` | `CommercialDiscountRequestBuilderPool` |
| `InvoiceIssuerInterface` | `issue(IssueRequestInterface $request): IssueResultInterface` | `InvoiceIssuerPool` |
| `InvoiceDocumentServiceInterface` | `getStatus`, `getPublishedView`, `download`, `cancel`, `sendEmail` | `InvoiceDocumentServicePool` |
| `TemplateOptionsProviderInterface` | `getOptions`, `getDefaultId` | `TemplateOptionsProviderPool` |
| `OriginInvoiceResolverInterface` | `resolve(IssueLog $originLog): array` | DI preference |
| `OrderStoreIdProviderInterface` | `getStoreIdByOrderId(int $orderId): int` | DI preference |

2. Đăng ký `<item name="{provider_code}">` vào từng pool trong `etc/di.xml`.
3. Thêm option provider qua `Secomm\EInvoiceCore\Model\Config\Source\Provider` (inject `providers`).
4. (Tùy chọn) Thêm group cấu hình riêng trong `system.xml`, hiển thị có điều kiện theo `provider`.

> Mẫu tham khảo chuẩn nhất: toàn bộ `Secomm_EInvoiceMisa/etc/di.xml`.

---

## 6. Hạn chế đã biết & việc cần hoàn thiện

1. **Cron đối soát (`ReconcileIssuedInvoices`)** mới đọc danh sách `TransactionID` từ MISA, **chưa** so khớp/đánh dấu các log local lệch trạng thái. Cần bổ sung logic so sánh với `issue_log` và cảnh báo.
2. **Comment cột `status`** trong `db_schema.xml` thiếu giá trị `cancelled` (PHP và UI có dùng).
3. **Không có khóa ngoại** giữa `issue_log` và `sales_order`/log cha — quan hệ chỉ ở mức logic.
4. **SignType 6** được tham chiếu trong code (poll trạng thái) nhưng **không** có trong dropdown admin (chỉ 1–5).
5. **Form admin log** ẩn một số cột có trong DB/grid (`reference_type`, `parent_log_id`, `last_action`, ...).

---

## 7. Kiểm thử (Unit Test)

Test nằm tại `Test/Unit/` của `EInvoiceCore` và `EInvoiceMisa` (ví dụ: `OrderToIssueRequestTest`, `MisaInvoiceIssuerTest`, `InvoicePayloadTotalsValidatorTest`, `MisaApiClientTest`, `PublishResponseValidatorTest`, `HsmCertificateTest`, `ConfigTest`...).

Chạy test:

```bash
# Toàn bộ unit test
bin/magento dev:tests:run unit

# Hoặc chạy trực tiếp phpunit cho module
vendor/bin/phpunit app/code/Secomm/EInvoiceMisa/Test/Unit
vendor/bin/phpunit app/code/Secomm/EInvoiceCore/Test/Unit
```

---

## 8. Phụ lục — Reference Type & trạng thái

| ReferenceType | Giá trị | Nhãn | Mapper / Nguồn |
|---------------|---------|------|----------------|
| Gốc | 0 | Hóa đơn gốc | `OrderToIssueRequest::map()` |
| Thay thế | 1 | Hóa đơn thay thế | `OrderToIssueRequest::mapReplacement()` |
| Điều chỉnh | 2 | Hóa đơn điều chỉnh | `CreditmemoToIssueRequest` |
| CKTM | 5 | Chiết khấu thương mại | `CommercialDiscountToIssueRequest` |

| Status | Ý nghĩa |
|--------|---------|
| `pending` | Log vừa tạo, chưa xử lý |
| `processing` | Đang gọi API provider |
| `success` | Phát hành thành công |
| `failed` | Phát hành/thao tác thất bại |
| `cancelled` | Hóa đơn đã hủy trên provider |
