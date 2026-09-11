# BUG-JMJYMJ (SLP-206) — Evidence Summary

Ngày: 2026-09-11 · Change set: +6 phrase pairs/file vào `app/design/frontend/Secomm/launchpad/i18n/{vi_VN,en_US}.csv` · `cache:flush` as secomm.

## 1. Dictionary verify (framework-level) — ✅ 6/6 PASS (exit 0)

- Script: `verify-i18n.php` (pattern BUG-8K1TBB) — boot Magento thật, area frontend, theme `Secomm/launchpad`, locale vi_VN.
- Output: `dictionary-verify.txt` — dictionary nạp 1873 entries, cả 6 phrase (kể cả key chứa `<b>` + `%1`) resolve đúng bản dịch đề xuất.

## 2. Live QC (curl 3 trạng thái trang) — ⛔ chặn bởi env issue PRE-EXISTING (không do change set)

Raw: `live-verify.txt` (HTTP 500 cả 3 request).

- Exception: `LogicException: catalog_product index does not exist yet. Make sure everything is reindexed.` — `Smile\ElasticsuiteCore\Index\IndexOperation.php:136`, bắn từ `Smile\ElasticsuiteCatalog\...\Fulltext\Collection` trên trang `catalogsearch/result`. 212 lần trong `var/log/exception.log`, sớm nhất thấy được từ 2026-09-10 (flag tương tự "category page 500 OpenSearch" đã ghi trong BUG-KFJ49A ngày 09-09).
- **Root cause env (đã chẩn đoán, KHÔNG sửa — chạm §12 search-engine config):** DB `core_config_data`: `catalog/search/engine = opensearch` (core engine, prefix `magento2`) trong khi các module Smile ElasticSuite đang enabled và storefront search/category chạy qua Smile Fulltext Collection. Smile đọc index theo alias `magento2_<store>_catalog_product` — không tồn tại. Indices `magento2_default_catalog_category_20260828_020837` + thesaurus (28-08) chứng tỏ site từng chạy engine `elasticsuite`; sau đó config bị đổi về `opensearch` → mọi trang đọc product index qua Smile đều 500 (search + category).
- Demo site (`slaunchpad-demo.secomm.vn`) render search OK (screenshots SLP-206) → env prod/demo không ảnh hưởng; chỉ local env lệch cấu hình.
- **Đề xuất cho TL (Tier 2 ack):** đổi `catalog/search/engine` về `elasticsuite` + `indexer:reindex catalogsearch_fulltext elasticsuite_categories_fulltext` (as secomm) + `cache:flush`. Sau đó QC live 3 trạng thái (q=test / q thường / q rác) + sort dropdown.

## 3. Side effects ghi nhận

- Đã chạy `indexer:reindex catalogsearch_fulltext` (as secomm) khi unblock: báo success 4s nhưng ghi theo core-engine naming → tạo `magento2_product_1_v17` / `magento2_product_2_v6` trong OpenSearch dùng chung (vô hại với ElasticSuite read-path; để nguyên).
- OpenSearch `127.0.0.1:9200` là instance dùng chung nhiều project (31 indices, thấy cả `magento_product_*`, `opensearch-cluster_*`, `opensearch-node_*`).
- Concurrent session **BUG-CW6KDK (SLP-205)** append thêm 2 phrase khác vào cùng 2 CSV lúc 08:55 cùng ngày — khác key, không conflict; 2 dòng đó thuộc change set của BUG-CW6KDK (SLP-205), không phải record này.

## 4. Scope check

- `git diff` change set của BUG-JMJYMJ: đúng 6 dòng/file CSV, không file nào khác.
- CSV giữ format hiện có (`"source","translation"`, LF, không header); mỗi key đúng 1 lần/file (grep đếm = 1).
- `en_US.csv`: 6 dòng mirror key, giá trị English (BR-001).
