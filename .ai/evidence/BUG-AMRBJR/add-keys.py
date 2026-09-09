#!/usr/bin/env python3
"""BUG-AMRBJR (SLP-187) — append OSC checkout i18n keys to Launchpad_MageplazaTranslate CSVs.
Wording source: theme dict (app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv) where the
key already exists (wording SSOT), else new wording. en_US gets identity values (BR-001 mirror).
Idempotent: skips keys already present in the target file.
"""
import csv
import sys

KEYS = [
    # (en source, vi wording, note)
    ("Already have an account? Click here to login", "Đã có tài khoản? Nhấp vào đây để đăng nhập", "new"),
    ("You already have an account with us.", "Bạn đã có tài khoản với chúng tôi.", "new"),
    ("You already have an account with us. Sign in or continue as guest.", "Bạn đã có tài khoản với chúng tôi. Đăng nhập hoặc tiếp tục với khách.", "new"),
    ("Shipping Address", "Địa chỉ giao hàng", "theme"),
    ("Email Address", "Địa chỉ email", "theme"),
    ("My billing and shipping address are the same", "Địa chỉ thanh toán và giao hàng của tôi giống nhau", "theme"),
    ("Billing Address", "Địa chỉ thanh toán", "theme"),
    ("Create an account", "Tạo tài khoản", "theme"),
    ("Shipping Methods", "Phương thức vận chuyển", "theme"),
    ("Payment Methods", "Phương thức thanh toán", "theme"),
    ("Payment Information", "Thông tin thanh toán", "theme"),
    ("Discount Code", "Mã giảm giá", "theme"),
    ("Order Summary", "Tóm tắt đơn hàng", "theme"),
    ("Item in Cart", "Sản phẩm trong giỏ hàng", "theme"),
    ("Items in Cart", "Các sản phẩm trong giỏ hàng", "theme"),
    ("Product Name", "Tên sản phẩm", "theme"),
    ("Quantity", "Số lượng", "theme"),
    ("Subtotal", "Tổng phụ", "theme"),
    ("Action", "Thao tác", "theme"),
    ("Cart Subtotal", "Tổng phụ giỏ hàng", "new"),
    ("Shipping", "Giao hàng", "new"),
    ("Register for newsletter", "Đăng ký nhận bản tin", "theme"),
    ("Place Order", "Đặt hàng", "theme"),
    ("Save in address book", "Lưu vào sổ địa chỉ", "theme"),
    ("Please specify a payment method.", "Vui lòng chọn phương thức thanh toán.", "new"),
    ("Selected shipping method is not available. Please select another shipping method for this order.", "Phương thức giao hàng đã chọn không khả dụng. Vui lòng chọn phương thức giao hàng khác cho đơn hàng này.", "new"),
    ("The selected shipping method is not applicable to your order. Please contact us for more details.", "Phương thức vận chuyển đã chọn không áp dụng cho đơn hàng của bạn. Vui lòng liên hệ với chúng tôi để biết thêm chi tiết.", "theme; live render is raw config errmsg — not dict-translated (F4 BUG-NY0M3S)"),
    ("Leave a message with the extra fee.", "Để lại lời nhắn về khoản phí phát sinh.", "new; corrects key mismatch vs SLP-146 'for'-variant"),
    ("You will be charged for", "Bạn sẽ thanh toán", "new; Magento_Tax basicCurrencyMessage"),
    ("Tooltip", "Trợ giúp", "new; wording pending TL"),
    ("We'll send your order confirmation here.", "Chúng tôi sẽ gửi xác nhận đơn hàng của bạn tại đây.", "new; OSC layout config description"),
    ("For delivery questions.", "Dành cho câu hỏi giao hàng.", "new; OSC layout config description"),
]

VI = "app/code/Launchpad/MageplazaTranslate/i18n/vi_VN.csv"
EN = "app/code/Launchpad/MageplazaTranslate/i18n/en_US.csv"


def existing_keys(path):
    with open(path, newline="", encoding="utf-8") as f:
        return {row[0] for row in csv.reader(f) if row}


def append(path, rows):
    with open(path, "a", newline="", encoding="utf-8") as f:
        w = csv.writer(f, lineterminator="\n", quoting=csv.QUOTE_MINIMAL)
        for r in rows:
            w.writerow(r)


for path, vi_col in ((VI, True), (EN, False)):
    have = existing_keys(path)
    rows = []
    for en, vi, note in KEYS:
        if en in have:
            print(f"SKIP (already present): {en!r} in {path}")
            continue
        rows.append((en, vi if vi_col else en))
    append(path, rows)
    print(f"{path}: +{len(rows)} rows")
