#!/usr/bin/env python3
"""BUG-CW6KDK (SLP-205): insert the 2 missing theme-dictionary keys into
Secomm/launchpad i18n vi_VN.csv + en_US.csv (BR-001 mirror).

Phrases are extracted from source (byte-exact) instead of retyped:
  1. login error  — Magento_Customer LoginPost.php `__()` concat of 2 literals
  2. LAC tooltip  — Magento_LoginAsCustomerAssistance config.xml default value
Idempotent: skips a key already present in the CSV.
"""
import csv
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
CSV_DIR = ROOT / "app/design/frontend/Secomm/launchpad/i18n"

login_post = (ROOT / "vendor/magento/module-customer/Controller/Account/LoginPost.php").read_text()
m = re.search(
    r"\(\s*'([^']*account sign-in[^']*)'\s*\.\s*'([^']*)'\s*\)", login_post, re.S
)
if not m:
    sys.exit("FATAL: cannot extract login phrase from LoginPost.php")
LOGIN_EN = (m.group(1) + m.group(2))

config_xml = (
    ROOT / "vendor/magento/module-login-as-customer-assistance/etc/config.xml"
).read_text()
m = re.search(
    r"<shopping_assistance_checkbox_tooltip>(.*?)</shopping_assistance_checkbox_tooltip>",
    config_xml, re.S,
)
if not m:
    sys.exit("FATAL: cannot extract tooltip from config.xml")
TOOLTIP_EN = m.group(1).strip()

VI = {
    LOGIN_EN: "Thông tin đăng nhập không đúng hoặc tài khoản của bạn tạm thời bị vô hiệu hóa. "
              "Vui lòng đợi và thử lại sau.",
    TOOLTIP_EN: 'Điều này cho phép chủ cửa hàng "nhìn thấy những gì bạn thấy" và thao tác '
                'thay bạn nhằm hỗ trợ bạn tốt hơn.',
}

rows = [("login", LOGIN_EN), ("tooltip", TOOLTIP_EN)]
for locale in ("vi_VN", "en_US"):
    path = CSV_DIR / f"{locale}.csv"
    with path.open(newline="", encoding="utf-8") as fh:
        existing = {row[0] for row in csv.reader(fh) if row}
    with path.open("a", newline="", encoding="utf-8") as fh:
        w = csv.writer(fh, quoting=csv.QUOTE_ALL, lineterminator="\n")
        for label, en in rows:
            if en in existing:
                print(f"{locale}: EXISTS  {label}")
                continue
            value = VI[en] if locale == "vi_VN" else en  # BR-001 mirror identity
            w.writerow([en, value])
            print(f"{locale}: ADDED   {label} -> {value[:60]}...")
print("done")
