# Spec: Payment Core — Pending Payment Lifecycle & Retry Checkout (VNPAY first adapter)

Specification ID: SPEC-FEAT-CSWYEJ
Feature ID: FEAT-CSWYEJ
Specification Level: FULL

> **Status:** VALID — approved 2026-08-25 bởi user acting as TL/SA (`/approve` D1–D6 theo khuyến nghị) → [DEC-FEATCSWYEJ-001](../records/decisions/DEC-FEATCSWYEJ-001.md).
> **Mode A** · Tier 2 (payment VNPAY, order lifecycle, DB schema, integration mới VNPAY querydr) · Ticket nguồn: user request 2026-08-25.
> Phiên bản inspect: Magento 2.4.8-p5, Vnpayment_VNPAY (source `app/code/`, active=0) @ 140a83e8.

---

## 1. Feature Overview

| Field | Value |
|---|---|
| **Feature name** | Payment Core — Manage Pending Payment Lifecycle & Retry Checkout |
| **Feature type** | New (new module `Secomm_PaymentCore` + VNPAY adapter integration) |
| **Priority** | P1 (High) — chạm payment/order lifecycle nhưng không chặn go-live hiện tại |
| **Source** | User request (LC queue) 2026-08-25 |
| **First adapter** | VNPAY (`Vnpayment_VNPAY`); Mollie adapter là Phase 2 (out of scope) |

**Vấn đề hiện tại:** mỗi payment method redirect (VNPAY, Mollie…) tự xử lý timeout/retry/cancel riêng lẻ hoặc không xử lý gì. Order pending payment không ai quản: tồn kho bị giữ vô thời hạn (reservation không bao giờ được compensate), customer đóng trang thanh toán thì mất luôn đơn (phải đặt lại). Không có cơ chế tập trung.

**Giải pháp:** module `Secomm_PaymentCore` quản lý tập trung lifecycle `pending → payable → expired → canceled` cho các payment method được admin assign. Provider (VNPAY, Mollie…) tích hợp qua **adapter contract** — core không biết provider nào. Cron expiry dùng **Magento order lifecycle chuẩn** để cancel (không SQL trực tiếp, không set state tay), release inventory/reservation theo standard flow, và có **race guard** chống cancel nhầm order đã thanh toán.

---

## 2. User Stories

- **US-001** — As a customer, tôi muốn quay lại đơn chưa thanh toán trong **My Account > Order Detail** và bấm **Continue Payment** để hoàn tất thanh toán trước khi hết hạn, thay vì phải đặt đơn mới.
- **US-002** — As a customer, tôi không được phép continue payment khi đơn đã hết hạn / đã thanh toán / đã cancel.
- **US-003** — As a merchant/ops, tôi muốn order pending payment hết hạn được **tự động cancel đúng Magento flow** và inventory/reservation được release, không cần thao tác tay.
- **US-004** — As a merchant, đơn đã thanh toán thành công **không bao giờ** bị cron cancel (kể cả khi record đã quá hạn).
- **US-005** — As a developer, tôi muốn tích hợp payment provider mới bằng cách implement adapter contract, **không sửa code core**.
- **US-006** — As an admin, tôi muốn cấu hình: enable/disable core, chọn managed methods, expiry time mặc định + override theo method, bật/tắt Continue Payment theo method.

---

## 3. Acceptance Criteria

> Mỗi AC độc lập, testable. AC-016/017 là QC e2e, còn lại unit/integration hoặc QC thủ công có hướng dẫn.

### Assignment & snapshot
- [ ] **AC-001**: Admin assign/unassign payment methods vào Payment Core từ **Stores > Configuration** (multiselect managed methods, scope default/website). Unassign method không tạo record mới cho order sau đó.
- [ ] **AC-002**: Order được place bằng managed method → payment record được tạo với `expires_at` **snapshot tại thời điểm place order**. Thay đổi config expiry sau đó **không** ảnh hưởng order đã tạo (verify bằng integration/unit test với config change giữa 2 orders).
- [ ] **AC-003**: Order place bằng method **không** được manage (COD/offline/braintree/vnpay-chưa-assign) → không có record, cron bỏ qua, không bao giờ bị core cancel.

### Continue Payment
- [ ] **AC-004**: Trong My Account > Order Detail, nếu payment chưa hết hạn + order vẫn payable (state `new`/status pending payment, `canCancel() === true`) + method có bật Continue Payment → hiển thị button **Continue Payment**.
- [ ] **AC-005**: Click Continue Payment → customer được redirect tới checkout URL **hợp lệ mới** từ provider (VNPAY: URL signed fresh, `vnp_TxnRef` = increment id như hiện tại — xem D6).
- [ ] **AC-006**: Sau khi **expired / paid / canceled** → button ẩn **và** controller từ chối (redirect về order view + message), không chỉ ẩn UI. Verify bằng gọi trực tiếp URL.
- [ ] **AC-007**: Controller Continue Payment enforce **ownership** (order thuộc customer đang login) + **CSRF form_key**. Truy cập order của người khác → 404/redirect, log warning.

### Expiry & cancel
- [ ] **AC-008**: Cron quét record `active` có `expires_at < now`, với mỗi order: reload order + check state + verify provider → cancel qua **`OrderManagementInterface::cancel()`** (Magento lifecycle chuẩn). Inventory/reservation được release (verify salable qty khôi phục sau cancel, MSI compensation chạy).
- [ ] **AC-009**: Order đã payment success (IPN/webhook đã cập nhật state) **không** bị cron cancel kể cả khi record quá hạn. Cron chỉ nhận diện qua order state + `canCancel()`.
- [ ] **AC-010** (race guard, D3): Trước khi cancel, adapter verify provider state (VNPAY `querydr`). Kết quả `PAID` → skip + log warn (IPN có thể delay/lost); `UNKNOWN` (API lỗi/timeout) → **skip, retry run sau** — *never cancel when uncertain*; chỉ `NOT_PAID` mới cancel.
- [ ] **AC-011**: Hai process (cron + IPN) đua trên cùng order → chỉ một thắng: cron giữ DB lock per-order (`LockManagerInterface`), IPN/ORDER state check trước cancel; không dead-lock, không double-cancel exception leak (unit test mô phỏng state flip giữa check và cancel).
- [ ] **AC-012**: Cancel fail (exception từ lifecycle) → order được skip, log error, **cron không chết**, record giữ `active` để retry run sau.

### Architecture & config
- [ ] **AC-013**: `Secomm_PaymentCore` **không có bất kỳ import/reference nào** tới `Vnpayment_*` hay `Mollie_*` (verify static grep trong review). Provider chỉ kết nối qua `PaymentProviderAdapterInterface` + DI wiring nằm trong module provider.
- [ ] **AC-014**: Admin config đầy đủ: enable/disable core, managed methods, default expiry (phút), per-method expiry override, per-method Continue Payment toggle. Disable core → không tạo record mới, cron no-op, button không hiển thị (record cũ giữ nguyên, xem D5).
- [ ] **AC-015**: Mọi lifecycle event (assign / continue / expire-check / skip-reason / cancel / error) có log vào channel riêng `secomm_paymentcore.log` với increment id + method + kết quả. **Không log hash secret / TmnCode / PII**.

### Tests & QC
- [ ] **AC-016**: Unit tests pass (`vendor/bin/phpunit app/code/Secomm/PaymentCore/Test/Unit`): expiry calculator, config resolver, adapter pool, cron processor (happy + race + uncertain-provider + cancel-fail).
- [ ] **AC-017**: QC e2e matrix (VNPAY sandbox) pass — đặt đơn không pay → record tạo đúng expiry; Continue Payment; đợi/cron trigger expiry → cancel + stock trả; đơn pay success không bị cancel; COD/đơn không manage không bị động; log trace đủ.

---

## 4. Technical Notes

### 4.1 Kiến trúc

```
Place order (managed method)
   └─ observer: sales_order_payment_place_end
        └─ AssignManagedPayment → insert record {order_id, method, expires_at snapshot, status=active}

My Account > Order Detail (sales/order/view)
   └─ CanContinuePayment check → [Continue Payment] button (Hyvä phtml)
        └─ POST paymentcore/payment/retry {order_id, form_key}
             ├─ guard: ownership + CSRF + active + chưa expired + canCancel + config
             └─ AdapterPool(method)->getCheckoutUrl(order) → redirect provider

Cron (group secomm_paymentcore, mỗi 5 phút)
   └─ ExpirePayments::execute
        for each record {status=active, expires_at < now} (batch, index (status, expires_at)):
          lock paymentcore_cancel_{order_id}            ← race guard #1
          reload order                                  ← race guard #2
          ├─ !canCancel()/state paid|canceled → close record (completed|canceled)
          ├─ adapter->isPaymentCompleted() == PAID    → log warn, skip (chờ IPN)   ← race guard #3 (D3)
          ├─ == UNKNOWN                                 → skip, retry_count++, log   ← conservative
          └─ == NOT_PAID → OrderManagementInterface::cancel(orderId)               ← Magento std flow
                          → record canceled, log; MSI reservation compensate tự chạy
```

### 4.2 Module layout (mới — `app/code/Secomm/PaymentCore/`)

```
Api/Data/PaymentInterface, Api/PaymentRepositoryInterface
Model/Payment.php, Model/PaymentRepository.php (+SearchCriteria)
Model/Config.php                       # scoped config resolver (managed methods, expiry, toggles)
Model/Adapter/PaymentProviderAdapterInterface.php   # CONTRACT — xem 4.3
Model/Adapter/VerifyResult.php         # {PAID, NOT_PAID, UNKNOWN} + message
Model/Adapter/AdapterPool.php          # method_code → adapter (DI array, provider tự đăng ký)
Model/Lifecycle/AssignManagedPayment.php   # observer sales_order_payment_place_end
Model/Lifecycle/ExpirePayments.php         # cron action
Model/Lifecycle/CancelExpiredOrder.php     # wrap OrderManagementInterface::cancel + lock + verify
Model/Lifecycle/CanContinuePayment.php     # guard service cho button + controller
Controller/Payment/Continue.php            # frontend customer-area
etc/{module,config,system,di,events,crontab,cron_groups,db_schema}.xml, etc/frontend/routes.xml
view/frontend/layout/sales_order_view.xml + templates/order/continue-payment.phtml
Test/Unit/...  ·  i18n/{vi_VN,en_US}.csv  ·  README.md + CHANGELOG.md  ([WARN] §7.2)
```

### 4.3 Adapter contract (provider-neutral)

```php
interface PaymentProviderAdapterInterface
{
    public function getMethodCode(): string;                 // 'vnpay'
    public function getCheckoutUrl(OrderInterface $order): ?string;   // URL signed mới mỗi lần gọi
    public function isPaymentCompleted(OrderInterface $order): VerifyResult; // query provider state
}
```

- Core **chỉ** biết interface. `AdapterPool` nhận map `method_code => adapter` qua `di.xml` argument — module provider (Vnpayment_VNPAY) tự đăng ký adapter của mình trong `di.xml` của provider. Không có adapter đăng ký cho method được manage → cron log error + skip (config sai).
- `VerifyResult::UNKNOWN` là **first-class**: mạng lỗi, API timeout, parse fail → cron nhất quyết không cancel.

### 4.4 VNPAY adapter (trong `Secomm_PaymentCore/Model/Provider/` — DEC-FEATCSWYEJ-002, D1-rev)

> **Rev 2026-08-25 (DEC-FEATCSWYEJ-002):** `Vnpayment_VNPAY` là extension third-party — **zero changes**. Adapter + URL builder nằm trong Payment Core (`Model/Provider/VnpayAdapter` + `VnpayCheckoutUrl`), đọc config paths `payment/vnpay/*` của extension như string contract (không import class), tự triển khai VND conversion qua `Magento_Directory` (tương đương `Helper\Rate`).

- `VnpayCheckoutUrl::build()`: replicate URL contract của extension (params, HMAC-SHA512, ksort/urlencode, TxnRef = increment id — D6) từ 3 config sẵn có `payment/vnpay/{payment_url,tmn_code,hash_code}`. **Semantics token VNPAY (verified sandbox 2026-08-25):** URL signed Magento gửi sang chỉ là *khởi tạo payment session*; VNPAY tự sinh `paymentv2/Transaction/PaymentMethod.html?token=…` hạn **15 phút, thuộc sở hữu VNPAY** — Magento không lưu token, không tái sử dụng link cũ. Continue Payment = build URL signed mới (TxnRef giữ nguyên) → VNPAY cấp token mới → IPN vẫn match theo `vnp_TxnRef` = increment id (Ipn.php:78). `vnp_Amount` khi retry = `total_due` hiện tại (order pending chưa invoice nên = grand total).
- `VnpayAdapter::isPaymentCompleted()`: gọi VNPAY **querydr** (`vnp_Command=querydr`). Endpoint là config của **Payment Core**: `secomm_paymentcore/vnpay/querydr_url` (admin Secomm > Payment Core > VNPAY — nhập tay từ docs integration VNPAY cấp; extension không có sẵn). **Trống = verify UNKNOWN mọi lần = cron không bao giờ cancel** (auto-expiry off an toàn cho đến khi nhập). `vnp_TransDate` lấy từ payment `additional_information['vnpay_initiated_at']` adapter ghi khi generate URL (querydr chỉ nhìn thấy ~24h gần nhất; fallback order created_at; quá hạn → UNKNOWN).
- Mapping querydr: `rsp 00 + txnStatus 00` → PAID; `rsp ∈ {01,02,04}` → NOT_PAID; còn lại (mạng/timeout/parse/http ≠ 200/rsp khác) → UNKNOWN — **never cancel when uncertain** (D3).
- **Không tái dùng** endpoint `paymentvnpay/order/info` cho Continue Payment — nó load order theo id không enforce ownership (Info.php:49-50; known risk logged — extension pristine nên không sửa tại chỗ; Continue controller của Payment Core có guard riêng AC-007 nên bề mặt mới không kế thừa lỗ hổng).
- IPN hiện tại vẫn là nguồn xác nhận payment chính; querydr chỉ là guard trước cancel. Signing/IPN của extension không bị đụng.

### 4.5 DB schema (`etc/db_schema.xml` — Tier 2)

Table `secomm_paymentcore_payment`:

| Column | Type | Notes |
|---|---|---|
| entity_id | int PK auto | |
| order_id | int UNIQUE | FK sales_order_grid-less (thường规 no FK constraint trong Magento custom table, index unique) |
| store_id | smallint | index (lọc cron theo scope) |
| method_code | varchar(32) | index |
| expires_at | timestamp | **snapshot**, index kết hợp (status, expires_at) |
| status | varchar(16) | `active` \| `completed` \| `canceled` \| `error` |
| retry_count | smallint default 0 | đếm lần skip UNKNOWN |
| created_at / updated_at | timestamp | |

Không column nào trên `sales_order*` — không đụng core tables. Whitelist `db_schema_whitelist.json` kèm theo. Criptom — không, **không có PII** trong table này.

### 4.6 Config paths (section `secomm_paymentcore`, tab Secomm — Secomm_Base)

```
secomm_paymentcore/general/enabled                # 0 default
secomm_paymentcore/general/managed_methods        # multiselect payment methods
secomm_paymentcore/general/default_expiry_minutes # default 120 (D4)
secomm_paymentcore/general/expiry_overrides       # textarea "method_code:minutes" mỗi dòng (D4)
secomm_paymentcore/general/continue_disabled      # multiselect methods tắt Continue Payment
secomm_paymentcore/cron/batch_size                # default 50
```

### 4.7 Race condition strategy (AC-011)

1. **Lock per-order** (`Magento\Framework\Lock\LockManagerInterface`, tên `paymentcore_cancel_{order_id}`, timeout 0 — không chờ, skip nếu held) chống 2 cron instance / cron-vs-something.
2. **Reload + state check ngay trước cancel** (`canCancel()`, state không phải processing/complete) — chống IPN commit giữa lúc cron scan tới lúc cancel.
3. **Provider verify (querydr)** — chống trường hợp tệ nhất: customer đã pay ở provider nhưng IPN chưa tới (delay/lost). Đây là guard mà state-check trong DB không bao giờ bắt được.
4. Cancel qua `OrderManagementInterface::cancel()` — bản thân nó validate state transitions; nếu throw → catch, log, giữ record `active`.

### 4.8 Frontend (Hyvä)

- Button trong customer order view: layout `sales_order_view.xml` của module chèn block vào area thông tin order; template `.phtml` dùng Alpine-light + Tailwind class có sẵn trong theme (không class động ngoài `@source` — §7.2). Guard service là ViewModel.
- Button là **POST form** (form_key) tới `paymentcore/payment/retry`, không phải GET link (CSRF, AC-007).
- Strings mới → `vi_VN.csv` + `en_US.csv` của module (BR-001).

### 4.9 Logging

Channel Monolog riêng ghi `var/log/secomm_paymentcore.log` (pattern như Secomm_Tracking): event `assign` / `continue` / `continue_denied{reason}` / `expire_candidate` / `provider_verify{result}` / `cancel_success` / `cancel_skipped{reason}` / `cancel_failed{error}` — mỗi line có increment_id + method_code. Mask: không log full URL có signature khi ở INFO (chỉ host + txnRef), không log hash_code/TmnCode.

---

## 5. Dependencies

| Dependency | Type | Status | Notes |
|---|---|---|---|
| `Vnpayment_VNPAY` | code (Tier 2) | installed, active=0 | Cần thêm adapter + PaymentUrlBuilder refactor + field querydr_url (SA review — §12) |
| `Magento_Sales` cancel lifecycle | platform | available | `OrderManagementInterface::cancel()` chuẩn |
| MSI `Magento_InventorySales` reservation compensation | platform | available | Tự chạy trên order cancel — không code thêm |
| `Secomm_Base` admin tab | code | available | Config section nằm trong tab Secomm Extensions |
| VNPAY sandbox (querydr) | external | [TBD QC] | QC cần tài khoản sandbox + endpoint querydr |
| Hyvä theme order view | code | available | Verify không conflict override theme hiện có |

---

## 6. Risks & Unknowns

| Risk | L/H/M | Impact | Mitigation |
|---|---|---|---|
| Customer đã pay ở provider nhưng IPN delay/lost → cron cancel nhầm | M | H (tiền vào nhưng đơn cancel) | D3: querydr verify bắt buộc trước cancel; UNKNOWN → skip (AC-010) |
| VNPAY từ chối re-pay cùng `vnp_TxnRef` ("Giao dịch đã tồn tại" rsp 01) | L (đã giảm: sandbox verify 2026-08-25 — link token 15' vẫn trả tiền được, nhiều session cùng TxnRef OK) | M (continue fail) | D6: giữ TxnRef = increment id (IPN match bắt buộc); QC còn phải verify **regenerate URL mới cùng TxnRef sau khi token cũ hết 15'** và hành vi khi 2 session cùng sống; nếu VNPAY từ chối → escalate thiết kế khác |
| querydr yêu cầu `vnp_TransDate` ≤ ~24h — đơn quá cũ không query được | M | M | Adapter trả UNKNOWN cho đơn quá cũ → record giữ active + log; thêm guard force-close sau N ngày (config) [TBD D3] |
| Cron chết/leak lock | L | M | Lock timeout ngắn + idempotent record; cron group riêng không chặn default group |
| Refactor Info.php (payment module, Tier 2) làm vỡ checkout hiện tại | L | H | Refactor behavior-preserving, không đổi signing; QC e2e checkout VNPAY toàn vẹn (rule §7.1) |
| db_schema migration trên prod | L | M | Declarative schema, table mới không đụng core; Tier 2 review trước deploy |
| Override layout order view đụng customize theme | L | L | Kiểm tra `sales_order_view.xml` hiện có trong theme trước khi chèn |
| Mageplaza OSC đặt order state khác chuẩn | L | M | QC verify state thực tế sau place order (dự kiến STATE_NEW + status pending theo config `payment/vnpay/order_status`) |

**[ASSUMPTION: Continue Payment chỉ cho customer đăng nhập (My Account) — guest order không có surface xem đơn. Reorder/copy link email là Phase 2.]**
**[ASSUMPTION: Một order chỉ có một payment record active duy nhất (unique order_id) — không hỗ trợ re-order trong scope này.]**

---

## 7. Out of Scope

- **Mollie adapter** (adapter thứ 2, Phase 2 — Mollie đã có webhook riêng; chỉ implement `PaymentProviderAdapterInterface` khi cần).
- Sửa logic IPN/Pay/signature hiện có của VNPAY (ngoài refactor extract URL builder).
- Reminder email / notification cho customer sắp hết hạn.
- Admin grid quản lý payment records (log + DB là đủ Phase 1; grid nếu ops cần).
- Guest checkout continue-payment link (qua email token).
- Re-order / partial payment / multi-attempt UX ngoài generate URL mới.
- Force-cancel override cho đơn UNKNOWN kéo dài (chỉ log + config force-close TBD ở D3).

---

## 8. Test Notes (QC)

- **Unit** (AC-016): `Test/Unit` — ExpiryCalculator (snapshot, override resolution), Config (managed methods, toggles, disabled), AdapterPool (missing adapter), ExpirePayments (paid-skip / unknown-skip / cancel-success / cancel-throw, race: state flip sau check), CanContinuePayment (mọi nhánh guard).
- **QC e2e** (AC-017, VNPAY sandbox — cần `active=1` + tmn/hash sandbox):
  1. Đặt đơn VNPAY, không pay → check record + expires_at đúng config.
  2. My Account > Order Detail → Continue Payment → tới trang VNPAY.
  3. Đổi config expiry nhỏ hơn → order cũ không đổi (AC-002).
  4. Cron trigger (hoặc `bin/magento cron:run --group=secomm_paymentcore`) sau expiry → order canceled, salable qty trả, log đủ.
  5. Đơn pay success (IPN rsp 00) → cron chạy → không cancel (AC-009).
  6. Race simulation: pay thành công ở sandbox nhưng chặn IPN tạm (hosts) → cron chạy → querydr PAID → skip + log warn (AC-010).
  7. COD order + VNPAY unassign → cron no-op trên các đơn này (AC-003).
  8. Disable core → đơn mới không có record, button biến mất (AC-014).
- **Env constraint**: shell AI không chạy được php — user chạy `bin/magento`/`phpunit` qua `! <cmd>`, AI xử lý output.

---

## 9. Open Decisions (BLOCKING — cần TL/SA approve trước `spec_status: VALID`)

| # | Câu hỏi | Khuyến nghị | Alternatives |
|---|---|---|---|
| **D1** | VNPAY adapter đặt ở đâu? | **Trong `Vnpayment_VNPAY`** (`PaymentCore/VnpayAdapter`) — core không có reference provider, provider tự đăng ký qua di.xml của mình. Đúng layered architecture. | (b) Trong `Secomm_PaymentCore` — core sẽ phải phụ thuộc config VNPAY, vi phạm AC-013. (c) Module riêng `Secomm_PaymentCoreVnpay` — over-modularize cho 1 class. |
| **D2** | Lưu expires_at ở đâu? | **Table riêng `secomm_paymentcore_payment`** — không đụng sales tables, index (status, expires_at) cho cron, mở rộng audit (retry_count) | (b) Column extension attribute trên `sales_order_payment` — đụng core table, khó index theo lifecycle query. (c) `additional_information` JSON — không query/index được cho cron. |
| **D3** | Race guard trước cancel: mức nào? | **3 lớp: lock + state check + adapter querydr bắt buộc**; UNKNOWN → skip + retry, force-close record sau N ngày (config) để không dồn | (b) Chỉ lock + state check — không bắt được "paid ở provider, IPN chưa về" → rủi ro cancel nhầm đơn đã thu tiền. (c) Querydr optional theo config — tăng rủi ro ops cấu hình sai. |
| **D4** | Default expiry + cơ chế override per-method? | **Default 120 phút**; override qua **textarea `method_code:minutes`** (validate server-side) — không cần dynamic-rows UI phức tạp | (b) Default 24h — giữ inventory quá lâu cho VN fashion. (c) Dynamic rows config UI — phức tạp UI cho lợi ích thấp Phase 1. Giá trị default chốt bởi TL/SA (có thể 60/120/1440). |
| **D5** | Unassign/disable core khi đang có record active? | **Snapshot semantics**: record đã tạo tiếp tục được quản lý đến khi resolve (đúng tinh thần snapshot expires_at — AC-002); disable core chỉ dừng tạo record mới + cron có flag đọc record tồn tại hay không [TL chốt]. | (b) Disable core = cron no-op hoàn toàn — đơn active kẹt vĩnh viễn không cancel tự động. |
| **D6** | Continue Payment với VNPAY: TxnRef? | **Giữ `vnp_TxnRef` = increment id** (generate URL mới, CreateDate mới) — IPN hiện tại match theo TxnRef=incrementId; đổi suffix sẽ vỡ IPN match (Ipn.php:76-78). QC verify sandbox chấp nhận re-pay cùng TxnRef chưa thành công. | (b) TxnRef mới mỗi lần (suffix `-R1`) — IPN không còn match được, phải sửa IPN (Tier 2, scope lớn hơn nhiều). |

---

## 10. Traceability — Requirement → AC

| Requirement (user prompt) | AC |
|---|---|
| Assign/unassign methods từ admin | AC-001, AC-014 |
| Expiry rõ ràng, snapshot khi tạo | AC-002 |
| Continue Payment trước hết hạn | AC-004, AC-005 |
| Không continue sau expired/paid/canceled | AC-006 |
| Cron xử lý order expired, cancel đúng flow, release inventory | AC-008, AC-012 |
| Order đã success không bị cancel | AC-009, AC-010, AC-011 |
| VNPAY là adapter đầu tiên | AC-005, AC-017, §4.4 |
| Core không phụ thuộc VNPAY | AC-013, §4.3 |
| Log trace | AC-015 |
| Unit/integration test lifecycle + race | AC-016, AC-011 |
| Không cancel COD/offline/unmanaged | AC-003 |
| Không SQL trực tiếp status/stock | AC-008 (cancel qua service), §4.7 |