# DEC-TASKCG6BM7-011 — Round 7: Credit Memo native STATE_OPEN là trạng thái duy nhất trước thành công; xóa state 4 (F27/A1 + F34/A3)

Date: 2026-09-17. Status: accepted.

## D1 — F27: CM được persist PRE-PROVIDER phải `STATE_OPEN`

`CreditmemoService::validateForRefund` (2.4.8-p5) đòi hỏi CM ĐÃ TỒN TẠI (`getId()` truthy) phải `STATE_OPEN`, ngược lại ném "We cannot register an existing credit memo." — round 6 persist CM pre-provider mà không set state ⇒ DB state NULL ⇒ synchronous SUCCESS không bao giờ chạy qua native flow được. Chọn: trước khi persist pre-provider, `setState(Creditmemo::STATE_OPEN)` — ngữ nghĩa "CM tồn tại cục bộ + accounting CHƯA áp + provider CHƯA chốt"; tuyệt đối KHÔNG đặt REFUNDED trước provider SUCCESS. Persist pre-provider chỉ ghi identity/metadata (entity_id, state, invoice_id) — KHÔNG đụng order totals / qty_refunded / invoice.base_total_refunded / payment amount_refunded (core tự áp đúng một lần trong `$proceed()`; cron finalize áp trong `finalizeSuccess`).

## D2 — F34: xóa custom `STATE_PROCESSING = 4` + plugin `aroundGetStates`

`Creditmemo::getStates()` là **static** trong 2.4.8-p5 ⇒ around plugin instance-method không bao giờ được interception ⇒ state 4 không phải hợp đồng Magento: Admin/integration hiển thị "Unknown State", các logic core so state thật không nhận. Chọn xóa sạch: class `Plugin\Model\Order\CreditmemoPlugin`, registration `zalopay_creditmemo_states` trong di.xml, mọi parking logic. Trạng thái hiển thị trong suốt lifecycle: PROCESSING/UNKNOWN/PSLP/confirmed-FAIL ⇒ CM giữ STATE_OPEN (row `zalo_pay_refund.refund_state` mới là source of truth bất đồng bộ); confirmed SUCCESS ⇒ native REFUNDED (đặt bởi core `$proceed()` hoặc `finalizeSuccess`). Cron không còn `releaseCreditmemoAfterFail` (không còn state nào cần trả về OPEN).

## D3 — Vì sao không dùng state tùy biến khác thay thế

Bất kỳ state non-core nào cũng phá hợp đồng `validateForRefund` (chỉ nhận OPEN cho CM tồn tại) và hợp đồng hiển thị; mở rộng enum state của core (plugin vào `getStates`) bất khả thi vì static. `refund_state` trên `zalo_pay_refund` đã mang đủ ngữ nghĩa blocking/terminal — trùng lặp sang CM chỉ thêm drift.

## D4 — Bằng chứng

Real-Magento Scenario 1/2: `before provider: id=NULL state=NULL → sau pre-provider persist: id>0 state=OPEN(1) → provider SUCCESS 1 lần → core validateForRefund PASS (không exception "existing credit memo") → sales_creditmemo.state=REFUNDED(2)`. Scenario 3/4/5: mọi nhánh PROCESSING/UNKNOWN/FAIL kiểm tra DB `sales_creditmemo.state=1 (OPEN)`. Grep: không còn tham chiếu `CreditmemoPlugin::STATE_PROCESSING` / state `4` trong module.
