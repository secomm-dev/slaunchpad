#!/usr/bin/env python3
"""BUG-AMRBJR (SLP-187) residual — append the 8 KO-i18n keys missed by the 09-09 batch
(baseline was captured with the summary item collapsed; expanded/conditional states missed). Final deploy: rebuilt from HEAD verbatim + append (see RESULTS.md CSV hygiene).
Wording source: theme dict (app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv) — ALL 8 keys
already exist there (SLP-128/133 batch), so wording is a straight SSOT mirror, no new wording.
en_US gets identity values (BR-001 mirror).
Idempotent: skips keys already present in the target file.
"""
import csv

KEYS = [
    # (en source, vi wording from theme dict, note)
    ("View Details", "Xem chi tiết", "theme; OSC summary item options toggle (screenshot QC)"),
    ("Options Details", "Chi tiết tùy chọn", "theme; same template, subtitle when expanded"),
    ("Forgot an item?", "Quên một sản phẩm?", "theme; conditional — not rendered local, dict-level"),
    ("No Payment method available.", "Không có phương thức thanh toán nào khả dụng.", "theme; conditional — not rendered local, dict-level"),
    ("Sorry, no quotes are available for this order at this time", "Rất tiếc, hiện không có phương thức vận chuyển nào cho đơn hàng này", "theme; conditional — not rendered local, dict-level"),
    ("You can create an account after checkout.", "Bạn có thể tạo tài khoản sau khi thanh toán.", "theme; conditional — auth block hidden local, dict-level"),
    # spotted live 09-16 (residual probe, expanded summary): totals row renders EN when a cart
    # price rule applies — row absent in the 09-09 session (no rule matched then). Same LL-0011
    # class; theme dict has both variants (wording SSOT).
    ("Discount", "Chiết khấu", "theme; totals row label when cart rule applies"),
    ("Discount (%1)", "Chiết khấu (%1)", "theme; totals row label variant"),
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
