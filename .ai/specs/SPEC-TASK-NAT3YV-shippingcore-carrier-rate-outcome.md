# Task Spec: ShippingCore — Carrier rate outcome semantics (Phase E-C1)

Specification ID: SPEC-TASK-NAT3YV

> Filename: `SPEC-TASK-NAT3YV-shippingcore-carrier-rate-outcome.md` — standalone work-item spec
> (slice Phase E-C1 của FEAT-YA2C0W; contracts/domain-semantics only, thực thi SPIKE-YH439T
> failure model + E-C0 stage boundary).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-NAT3YV |
| Feature ID | FEAT-YA2C0W (parent; slice Phase E-C1) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ approved E-C1 directive; failure model pre-approved qua SPIKE-YH439T §8/§9/§10 |
| Status | **VALID** — outcome shape theo directive; TL review spec text chạy cùng code pre-review |
| Date | 2026-09-08 |
| Related Decision(s) | DEC-FEATYA2C0W-004 (D2 ownership · D9/D10 minimal abstraction) |
| Related Ticket(s) | TASK-NAT3YV · SPIKE-YH439T (failure model) · TASK-XXBN5X (E-SL0 fallback contracts) · TASK-T78YH6 (E-C0 handoff) |
| Workflow Mode | A (shipping shared-contract = generic risk category) |

## 1. Objective

Contract chung để realtime carrier integration báo kết quả tính giá về ShippingCore — chuẩn hóa
`return false / null / throw / fake-rate / log-and-continue` thành **domain outcome 3 trạng thái**:

```text
SUCCESS           — có rate realtime dùng được
UNAVAILABLE       — carrier không phục vụ request này vì lý do KHÔNG-tạm-thời (business/data/config)
TECHNICAL_FAILURE — carrier lẽ ra phục vụ được nhưng quote thất bại vì lỗi kỹ thuật TẠM THỜI
```

Phân biệt này là input cho service-level fallback (E-SL1 sau): chỉ TECHNICAL_FAILURE mới có thể
mở fallback. **KHÔNG** orchestration/fallback/carrier adoption trong E-C1.

## 2. Verified implementation basis

* ShippingCore **chưa có** rate DTO (grep Api/Model — chỉ Origin/ShippingContext/tracking/address).
* `FallbackRateInterface` (E-SL0) KHÔNG tái dùng được cho carrier rate: amount **strictly > 0**
  (no-match ⇒ null sentinel — fallback-specific) + mang label/deliveryEstimate. Carrier rate cần
  `amount >= 0` (zero hợp lệ — promotion/free shipping, directive §14) + currency ⇒ DTO riêng.
* Magento `RateResult\Method`/`Rate\Result` không được leak vào contract (§19) — carrier adapter
  translate sau.
* Precedent VO/invariants + reason constants: `ResolvedShippingAddress` (public constructor +
  guards), `CarrierAddressHandoffInterface::REASON_*`, `VnAddressResolutionInterface::STATUS_*`.
* Monetary convention project: `float` (`Method::setPrice(float)` ở GHN/GHTK/Ahamove).

## 3. Scope — `Api\Rate` + `Model\Rate`

### 3.1 CarrierRateInterface (+ VO CarrierRate)

```php
interface CarrierRateInterface
{
    public function getAmount(): float;      // >= 0 (zero HỢP LỆ — promotion; negative → LogicException)
    public function getCurrency(): ?string;  // null = store currency mặc định
}
```

Asymmetric với `FallbackRate` (strictly positive) **có chủ đích**, documented: fallback dùng
zero làm cấm-sentinel vì "no match" phải là null; realtime carrier có thể quote 0đ hợp lệ.

### 3.2 CarrierRateOutcomeInterface (+ VO CarrierRateOutcome)

```php
interface CarrierRateOutcomeInterface
{
    public const STATUS_SUCCESS           = 'SUCCESS';
    public const STATUS_UNAVAILABLE       = 'UNAVAILABLE';
    public const STATUS_TECHNICAL_FAILURE = 'TECHNICAL_FAILURE';

    // Shared ShippingCore-owned reasons (giá trị CANONICAL_UNRESOLVED / UNSUPPORTED_DESTINATION
    // cố ý trùng CarrierAddressHandoffInterface::REASON_* — carrier translate handoff → outcome
    // giữ nguyên reason). Carrier-specific chi tiết (GHN_LOCATION_NOT_FOUND…) là string tự do
    // của carrier — KHÔNG centralize (directive §8).
    public const REASON_UNSUPPORTED_DESTINATION = 'UNSUPPORTED_DESTINATION';
    public const REASON_CANONICAL_UNRESOLVED    = 'CANONICAL_UNRESOLVED';
    public const REASON_PROVIDER_MAPPING_MISSING = 'PROVIDER_MAPPING_MISSING';
    public const REASON_SERVICE_UNAVAILABLE     = 'SERVICE_UNAVAILABLE';
    public const REASON_TECHNICAL_ERROR         = 'TECHNICAL_ERROR';

    public function getStatus(): string;
    public function getRate(): ?CarrierRateInterface;
    public function getFailureReason(): ?string;
    public function isSuccessful(): bool;    // convenience hard-guard (mirror isResolved() E-A)
}
```

### 3.3 Invariants + factories (mirror ResolvedShippingAddress precedent)

Public constructor `(status, rate, reason)` + guards + named factories tiện call-site:

```text
SUCCESS:           rate BẮT BUỘC non-null, reason BẮT BUỘC null
UNAVAILABLE:       rate bắt buộc null, reason TÙY CHỌN (empty string normalize → null)
TECHNICAL_FAILURE: rate bắt buộc null, reason TÙY CHỌN (normalize như trên)
unknown status / SUCCESS thiếu rate / non-SUCCESS có rate → LogicException (fail-fast)
CarrierRate::success(rate) · CarrierRateOutcome::success(rate)
  · ::unavailable(?reason) · ::technicalFailure(?reason)
```

### 3.4 Classification ownership (không đổi từ SPIKE-YH439T)

* **Carrier adapter sở hữu** việc phân loại raw provider errors → top-level outcome:
  timeout/ConnectException/HTTP 5xx/upstream outage/malformed-temporary → `TECHNICAL_FAILURE`;
  route-not-supported/dimension-invalid/not-serviceable/mapping-missing → `UNAVAILABLE`.
  ShippingCore KHÔNG hiểu raw error codes của GHN/GHTK/Ahamove.
* **§9**: provider mapping missing → `UNAVAILABLE` + `REASON_PROVIDER_MAPPING_MISSING` — data/
  config state deterministic, KHÔNG phải outage, KHÔNG trigger fallback.
* **§10**: handoff unresolved (không fallback-eligible) → `UNAVAILABLE` + `REASON_CANONICAL_UNRESOLVED`.
* **§13 auth/config failure** (invalid token/missing credential/sai shop config) → `UNAVAILABLE`
  (reason tự do carrier, gợi ý `SERVICE_UNAVAILABLE` hoặc carrier-specific config code) —
  KHÔNG BAO GIỜ `TECHNICAL_FAILURE`: fallback không được che lỗi cấu hình merchant vô hạn;
  fail loudly ở log/admin ops.

## 4. Decisions (report theo directive §6/§7)

* **Service level: Option B — NGOÀI outcome.** Caller/orchestration đã biết bucket nào đang
  collect (nó chọn carrier theo level); nhét serviceLevel vào outcome = trùng lặp ngữ cảnh.
* **Carrier identity: NGOÀI outcome** (carrierCode/title/provider name không vào DTO) — outcome
  tái sử dụng được, collector đã biết ai sinh ra nó.

## 5. Out of scope

Carrier adoption (GHN/GHTK/Ahamove/GiaoHangNhanh) · service-level aggregation/selection ·
fallback trigger + `FallbackRateProvider` invocation · Mageplaza bridge · checkout methods/
showmethod policy · external resolver · OrderOperations · logging trong VO (§16 — VO chỉ domain
state; log ở carrier/API operation boundary) · Magento RateResult wrap.

## 6. Acceptance Criteria

* **AC-1**: 3 STATUS constants + 5 shared REASON constants; giá trị 2 reason address-stage trùng
  handoff constants (documented).
* **AC-2**: Invariants §3.3 — impossible combinations (SUCCESS thiếu rate; UNAVAILABLE/
  TECHNICAL_FAILURE có rate; status lạ) → `LogicException`; factories tiện dụng.
* **AC-3**: `CarrierRate` amount >= 0 (zero valid, negative reject) + currency nullable — khác
  biệt có chủ đích với `FallbackRate` (documented).
* **AC-4**: `isSuccessful()` true ⇔ SUCCESS; không metadata array; không logger trong VO; không
  Magento RateResult dependency (grep).
* **AC-5**: DI preference 2 interface (mirror E-A VO precedent); compile + validator 0 new
  finding + phpunit pass.
* **AC-6**: README/CHANGELOG: outcome 3 trạng thái + rule "chỉ technical failure mở fallback"
  + auth/config non-technical + Stage 1/2/3; working memory sync; 0 carrier code đổi.

## 7. Test plan (unit, AAA)

`CarrierRateTest`: positive/zero valid · negative reject · currency nullable · transport.
`CarrierRateOutcomeTest`: SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE happy (factories + constructor) ·
impossible combos (SUCCESS thiếu rate; SUCCESS có reason; UNAVAILABLE/TECHNICAL có rate; status
lạ) · `isSuccessful` matrix · reason empty-string normalize → null · constructor non-magic.
Mixed-outcome document-only examples (§22) nằm ở README, không test orchestration.
