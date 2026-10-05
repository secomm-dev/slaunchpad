# Hướng dẫn sử dụng — Hóa đơn điện tử (cho Admin & Kế toán)

> Đối tượng đọc: Nhân viên vận hành Admin, Kế toán.
> Phạm vi: thao tác phát hành, tra cứu, xử lý hóa đơn điện tử (HĐĐT) trên trang quản trị.
> Không yêu cầu kiến thức kỹ thuật.

---

## 1. Giới thiệu

Hệ thống tự động tích hợp **hóa đơn điện tử MISA MeInvoice** vào quy trình bán hàng Magento. Với mỗi đơn hàng, bạn có thể:

- Phát hành hóa đơn điện tử (tự động hoặc thủ công).
- Xem, tải (PDF/XML), gửi email hóa đơn cho khách.
- Hủy, thay thế, điều chỉnh hóa đơn.
- Lập hóa đơn chiết khấu thương mại (CKTM).
- Tra cứu lịch sử và trạng thái mọi hóa đơn.

Mọi thao tác nằm gọn trong **tab EInvoice** của trang đơn hàng — bạn **không** cần đăng nhập riêng vào MeInvoice cho thao tác hằng ngày.

---

## 2. Truy cập

### 2.1. Tab EInvoice trên đơn hàng

1. Vào **Sales → Orders**.
2. Mở một đơn hàng (View).
3. Chọn tab **EInvoice** ở menu trái.

> Tab chỉ hiển thị khi tính năng HĐĐT được bật cho cửa hàng tương ứng.

### 2.2. Trang tra cứu log

Vào menu **EInvoice → EInvoice Logs** để xem toàn bộ lịch sử phát hành của tất cả đơn hàng.

---

## 3. Các loại hóa đơn

| Loại | Khi nào dùng |
|------|--------------|
| **Hóa đơn gốc** | Hóa đơn đầu tiên phát hành cho đơn hàng |
| **Hóa đơn thay thế** | Thay thế hóa đơn gốc bị sai (sau khi đã phát hành) |
| **Hóa đơn điều chỉnh** | Điều chỉnh khi có trả hàng / hoàn tiền (gắn với credit memo) |
| **Hóa đơn chiết khấu thương mại (CKTM)** | Lập hóa đơn chiết khấu theo chương trình khuyến mại |

---

## 4. Phát hành hóa đơn

### 4.1. Phát hành tự động

Tùy cấu hình, hệ thống **tự phát hành** hóa đơn khi **tạo phiếu giao hàng (shipment) lần đầu** cho đơn. Bạn không cần thao tác gì; kiểm tra kết quả ở tab EInvoice hoặc trang Logs.

### 4.2. Phát hành thủ công

1. Mở tab **EInvoice** trên đơn hàng.
2. Mở mục **Issue (Phát hành)**.
3. Chọn **mẫu hóa đơn (Invoice Template)** phù hợp.
4. Bấm **Issue**.
5. Hệ thống gọi MISA và hiển thị kết quả:
   - **Thành công:** xuất hiện thông tin Số hóa đơn (InvNo), Mã giao dịch (Transaction ID), trạng thái chuyển sang **Success**.
   - **Thất bại:** hiển thị thông báo lỗi — xem mục Xử lý lỗi (§8).

> Nút **Issue** chỉ hiện khi đơn **chưa có** hóa đơn phát hành thành công.

---

## 5. Sau khi phát hành — các thao tác

Khi đơn đã có hóa đơn thành công, mục **Documents** và các mục liên quan sẽ xuất hiện:

| Thao tác | Mô tả |
|----------|-------|
| **Preview** | Xem trước bản nháp hóa đơn (trước/đang phát hành) |
| **View Published** | Mở hóa đơn đã phát hành chính thức (link MeInvoice) |
| **Refresh Status** | Cập nhật lại trạng thái hóa đơn mới nhất từ MISA |
| **Download PDF** | Tải hóa đơn bản PDF |
| **Download XML** | Tải hóa đơn bản XML (file gốc pháp lý) |
| **Send Email** | Gửi/gửi lại email hóa đơn cho khách |

### 5.1. Gửi email cho khách

1. Mở mục **Email** ở tab EInvoice.
2. Hệ thống tự điền **tên** và **email** người nhận từ thông tin thanh toán của đơn. Có thể chỉnh lại.
3. Bấm gửi. Kết quả gửi được ghi nhận trong log.

---

## 6. Hủy hóa đơn

1. Mở mục **Cancel (Hủy)** ở tab EInvoice (chỉ hiện khi đã có hóa đơn phát hành).
2. Nhập **lý do hủy** (nếu để trống sẽ dùng mặc định).
3. Bấm **Cancel**.
4. Hóa đơn chuyển trạng thái **Cancelled**, ghi nhận thời điểm hủy.

> **Lưu ý kế toán:** việc hủy hóa đơn trên hệ thống đồng thời gửi yêu cầu hủy tới MISA. Hãy cân nhắc đúng quy định trước khi hủy.

---

## 7. Thay thế & Điều chỉnh

### 7.1. Hóa đơn thay thế

Dùng khi hóa đơn gốc **sai thông tin** và cần thay bằng hóa đơn mới.

1. Mở mục **Replacement (Thay thế)**.
2. Xác nhận và bấm phát hành thay thế.
3. Hệ thống lập hóa đơn mới tham chiếu hóa đơn gốc, ghi loại **Hóa đơn thay thế**.

### 7.2. Hóa đơn điều chỉnh (trả hàng / hoàn tiền)

- **Tự động:** nếu được cấu hình bật, khi bạn tạo **Credit Memo** (hoàn tiền/trả hàng), hệ thống tự lập hóa đơn điều chỉnh tương ứng.
- Trường hợp tự động thất bại, lỗi được ghi log (không chặn việc tạo credit memo); kế toán có thể kiểm tra ở trang Logs.

### 7.3. Hóa đơn chiết khấu thương mại (CKTM)

Chỉ hiện khi tính năng CKTM được bật.

1. Mở mục **Commercial Discount** ở tab EInvoice.
2. Nhập thông tin:
   - **Số bảng kê (List No)**
   - **Ngày bảng kê (List Date)**
   - **Ghi chú hóa đơn (Invoice Note)**
   - **Số tiền chiết khấu (Amount)**
   - **Tiền VAT (VAT Amount)**
3. Bấm phát hành. Hệ thống lập hóa đơn loại **Chiết khấu thương mại**.

---

## 8. Xử lý lỗi thường gặp

Khi phát hành lỗi, tab EInvoice hiển thị cảnh báo kèm thông báo lỗi. Một số lỗi phổ biến:

| Thông báo / Tình huống | Ý nghĩa | Hướng xử lý |
|------------------------|---------|-------------|
| Hóa đơn bị trùng (Invoice Duplicated) | Đã phát hành hóa đơn cho RefID này | Dùng **Replace** nếu cần hóa đơn mới; hoặc kiểm tra hóa đơn đã có |
| Số hóa đơn không liên tục | Vướng quy định liên tục số hóa đơn | Báo người quản trị kiểm tra cấu hình ký hiệu; thử lại sau |
| Lỗi tổng tiền không khớp | Số liệu trên hóa đơn lệch so với đơn hàng | Báo kỹ thuật kiểm tra cấu hình (phí ship, chiết khấu, định dạng số) |
| Dropdown mẫu hóa đơn trống | Chưa kết nối được MISA | Báo quản trị kiểm tra cấu hình kết nối |
| Lỗi tỷ giá (đơn ngoại tệ) | Thiếu tỷ giá quy đổi sang VND | Báo quản trị cấu hình **Currency Rates** |

> Sau khi xử lý nguyên nhân, bạn có thể **phát hành lại** trực tiếp ở tab EInvoice (hệ thống tự xử lý số tham chiếu mới khi cần).

---

## 9. Tra cứu lịch sử — EInvoice Logs

Menu **EInvoice → EInvoice Logs** hiển thị bảng tất cả lần phát hành.

### 9.1. Các cột chính

| Cột | Ý nghĩa |
|-----|---------|
| ID | Mã dòng log |
| Order Increment ID | Số đơn hàng (bấm để mở đơn) |
| Store | Cửa hàng |
| Invoice Type | Loại HĐ: Gốc / Thay thế / Điều chỉnh / Chiết khấu |
| Parent Log ID | Log cha (với HĐ thay thế/điều chỉnh/CKTM) |
| Status | Trạng thái: Pending / Processing / Success / Failed / Cancelled |
| Transaction ID | Mã giao dịch MeInvoice |
| Ref ID | Mã tham chiếu yêu cầu |
| Inv Series | Ký hiệu hóa đơn |
| Inv No | Số hóa đơn |
| Error | Thông báo lỗi (nếu có) |
| Issued At / Created At | Thời điểm phát hành / tạo log |

### 9.2. Ý nghĩa trạng thái

| Trạng thái | Ý nghĩa |
|------------|---------|
| **Pending** | Đã ghi nhận yêu cầu, chưa xử lý |
| **Processing** | Đang gửi tới MISA |
| **Success** | Phát hành thành công |
| **Failed** | Thất bại — xem cột Error |
| **Cancelled** | Hóa đơn đã hủy |

### 9.3. Xem chi tiết một log

Bấm **View** ở cột thao tác để xem chi tiết: thông tin chung, thông báo lỗi, và dữ liệu request/response (phục vụ kế toán/đối chiếu khi cần).

> Bảng log **chỉ đọc** — không sửa trực tiếp. Mọi thay đổi thực hiện qua thao tác ở tab EInvoice của đơn hàng.

---

## 10. Quy trình khuyến nghị cho Kế toán

1. **Hằng ngày:** mở **EInvoice Logs**, lọc `Status = Failed` để phát hiện hóa đơn lỗi cần xử lý.
2. Với mỗi đơn lỗi: mở đơn → tab EInvoice → đọc thông báo lỗi → xử lý theo §8 → phát hành lại.
3. Với đơn trả hàng/hoàn tiền: kiểm tra đã có **hóa đơn điều chỉnh** tương ứng chưa.
4. Định kỳ đối chiếu số liệu hóa đơn (InvNo, ngày) giữa hệ thống và MeInvoice.
5. Khi cần bản gốc pháp lý cho khách: dùng **Download XML** (file gốc), kèm **Download PDF** để xem.

---

## 11. Câu hỏi thường gặp (FAQ)

**Hỏi: Tôi không thấy tab EInvoice?**
Tính năng chưa được bật cho cửa hàng này — liên hệ quản trị viên.

**Hỏi: Nút Issue bị mờ/không có?**
Đơn đã phát hành hóa đơn thành công rồi (không phát hành lại được; dùng Replace nếu cần).

**Hỏi: Đã giao hàng nhưng hóa đơn chưa tự phát hành?**
Có thể cấu hình đang để phát hành thủ công, hoặc đơn không ở lần giao hàng đầu tiên. Hãy phát hành thủ công ở tab EInvoice.

**Hỏi: Khách chưa nhận được email hóa đơn?**
Dùng mục **Send Email** ở tab EInvoice để gửi lại, kiểm tra lại địa chỉ email.

**Hỏi: Hủy hóa đơn rồi có phát hành lại được không?**
Có — sau khi hủy, có thể phát hành hóa đơn mới cho đơn theo quy định.
