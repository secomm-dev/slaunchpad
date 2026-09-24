# PLAN-BUG-S4VAAB — ZaloPay pending Return message

| Field | Value |
|---|---|
| Specification | Full Spec draft: [SPEC-BUG-S4VAAB](../specs/SPEC-BUG-S4VAAB-zalopay-pending-return-message.md) |
| Record | [BUG-S4VAAB](../records/bugs/BUG-S4VAAB.md) · external SLP-184 |
| Mode | A — Tier 2 payment/checkout/order-lifecycle |
| Status | Draft — chờ TL/SA xác nhận spec và approach; chưa implement |

## Approach

1. Thêm regression test vào `ReturnProcessorTest` trước khi sửa: code 3 có non-empty
   `return_message` vẫn trả message pending cố định, không mutation, không finalizer;
   giữ case code 3 không có message.
2. Bỏ nhánh staged đưa raw `return_message` vào `LocalizedException`. Giữ nhánh pending message
   cố định đã có; không thay paid/failure routing.
3. Bỏ translation key mới không dùng ở `en_US.csv` và `vi_VN.csv`; kiểm tra hai locale vẫn
   dùng cặp message pending hiện có.
4. Chạy focused test, full ZaloPay unit suite, PHP lint, PHPCS, DI compile; đối chiếu
   `git diff --cached` và `git diff` để không cuốn thay đổi unrelated vào patch.
5. QC Luma/Mageplaza OSC với trạng thái chưa thanh toán, thành công và thất bại trên
   môi trường có gateway, ghi bằng chứng. Chuyển TL/SA review trước merge/release.

## Scope Guard

Chỉ `ReturnProcessor.php`, unit test tương ứng, và hai CSV trong `Secomm_ZaloPay`.
Không sửa phần code unstaged của work item khác. Invariant của `TASK-EDS9T5` giữ nguyên:
Sales Order chỉ sau xác nhận thanh toán authoritative.
