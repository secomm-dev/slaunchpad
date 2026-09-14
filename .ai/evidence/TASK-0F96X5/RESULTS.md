# TASK-0F96X5 (SLP-212) — Evidence Results

Ngày: 2026-09-14 · Mode C · HEAD `0dc1c0d1` · **0 code change trong scope**

## Phương pháp

Inventory **DOM-driven** (mạnh hơn template-driven — bắt cả string từ PHP Block/config/CMS không thấy qua scan template):

1. Fetch PLP local 2 store (category `men/tops-men/jackets-men.html`, local = `http://slaunchpad.localhost/`):
   - VI: `curl --resolve slaunchpad.localhost:80:127.0.0.1 …` → HTTP 200, 730,401 B
   - EN: `…?___store=launchpad_en` (thuần, không `___from_store` — memory store-switch-quirk) → HTTP 200, 716,313 B
   - Lưu ý: local category page **HTTP 200** — blocker 500 Smile/OpenSearch của BUG-JMJYMJ/BUG-KFJ49A **không còn** (config `catalog/search/engine` hiện = `elasticsuite`).
2. Extract text nodes + `aria-label`/`title`/`alt`/`placeholder` + `<option>`, decode HTML entities (LL-0005) — `slp212-extract.py` → `plp-vi.txt` (239 strings) / `plp-en.txt` (240 strings).
3. Template scan `__()` của 17 template PLP đối chiếu merged dict — `slp212-scan.php`: **0/54 missing**.
4. Dict check trực tiếp các key PHP-rendered (`Position`, `Product Name`, `Relevance`, `Sort By`, `Set * Direction`…) — đủ.
5. EN store check rò VI: 0 (chỉ store name "Tiếng Việt" + ™ product name — đúng).
6. Truy source từng chuỗi EN còn lại trên VI DOM để phân loại (xem record §Findings A/B/C/D).

## Kết quả

| Nhóm | Kết luận |
|---|---|
| Core PLP sections (toolbar/sorter/limiter/viewmode/filter/cards/pager) | **VI sạch cả DOM** — đã cover từ BUG-M33P7N (SLP-132) + follow-ups |
| Wording "Shop By" (annotation screenshot) | Đã sửa trong **working tree** (uncommitted, `vi_VN.csv:603` "Mua theo" → "Bộ lọc", session song song 09-14); demo render "MUA THEO" vì demo = HEAD → **commit + deploy demo là hết** |
| Attribute labels/options, category/product names, CMS links | **Store data → Admin** (demo đã config VI: "Màu sắc", "Chất liệu"…) |
| `Breadcrumb` (aria), `Grid`/`List` (title), `Close (Esc)` | **Code path không qua `__()`** — cần override template nếu muốn dịch (flag TL; file SocialLoginPro đang modified bởi TASK-7P5RJP) |
| `Language`, `Search engine powered by %1` | CSV gap thật NHƯNG footer global — **chờ TL/PM duyệt scope** (không tự thêm) |

## Bằng chứng kèm theo

- `plp-vi.txt` / `plp-en.txt` — DOM strings 2 store (raw evidence)
- `slp212-extract.py` — script extract (tái chạy được)
- `slp212-scan.php` — script scan dict (tái chạy được)
- Record: `.ai/records/tasks/TASK-0F96X5.md` (PASS `--check-records --check-identity`; 18 FAIL trong validator là stale-title pre-existing của record cũ — không thuộc record này)

## Việc còn open (chờ quyết, không phải dev)

1. TL/PM: duyệt extend scope +2 row CSV footer (nhóm C) hoặc tách ticket riêng.
2. TL: quyết nhóm B (override template cho screen-reader/tooltip strings) — làm hay bỏ qua.
3. Admin: store label VI cho attribute/category trên local (demo đã có).
4. Release: commit working-tree CSV (tách hunk `Shop By` vi-only / `Track your order` en-only) + deploy demo.
