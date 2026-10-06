# ENV BLOCKER — local site 500 toàn bộ (06-10-2026)

Phát hiện khi chạy `probe-baseline-fonts.js` (baseline SLP-324): mọi trang local
(sllaunchpad.localhost qua 127.0.0.1 + Host header) trả **HTTP 500**.

## Root cause (2 tầng, cả hai đều pre-existing drift từ task khác, không phải SLP-324)

1. **Bootstrap error**: `Secomm_CurrencyPrecision` — module mới được commit vào
   `app/code/Secomm/CurrencyPrecision` (`setup_version="1.0.0"`, module.xml
   sequence `[Secomm_Base, Hyva_Theme]`) nhưng **không có row trong
   `setup_module`** → "Please upgrade your database... Run bin/magento setup:upgrade".
2. **`setup:upgrade` thất bại** tại `Secomm_Ahamove`:
   ```
   SQLSTATE[42000]: 1061 Duplicate key name 'AHAMOVE_CITY_CITY_ID'
   query: ALTER TABLE `ahamove_city` ADD CONSTRAINT `AHAMOVE_CITY_CITY_ID` UNIQUE KEY (`city_id`)
   ```

## Vì sao ALTER bị duplicate

- DB hiện tại: `SHOW INDEX FROM ahamove_city` → `AHAMOVE_CITY_CITY_ID` có
  **`Non_unique = 1`** → là **plain index**, không phải UNIQUE constraint.
- Schema đã commit (`db_schema.xml`, commit `ab51a598` "Fix index
  AHAMOVE_CITY_CITY_ID schema" + whitelist đã có key) khai báo
  `<constraint xsi:type="unique" referenceId="AHAMOVE_CITY_CITY_ID">`.
- Declarative schema diff so với **live DB** → thấy "thiếu" unique constraint
  (vì cái đang tồn tại chỉ là plain index trùng tên) → sinh ADD UNIQUE →
  MySQL từ chối vì tên index đã bị plain index chiếm.
- `setup_module` cũng **thiếu row** cho `Secomm_Ahamove` (chỉ còn 4 row
  Secomm_*: TiktokHyva/VietQr/VNPAY/ZaloPay).

## Repair đề xuất (chờ phê duyệt — chạm DB, ngoài scope SLP-324)

```sql
ALTER TABLE ahamove_city DROP INDEX AHAMOVE_CITY_CITY_ID;
```
```bash
sudo -u secomm php bin/magento setup:upgrade   # từ Magento root
```

- Sau `DROP INDEX`, `setup:upgrade` sẽ (a) đăng ký `Secomm_CurrencyPrecision`
  1.0.0 (module không có db_schema.xml/Setup → chỉ đăng ký version, không tạo
  bảng) và (b) tự dựng lại `AHAMOVE_CITY_CITY_ID` ở đúng dạng **UNIQUE**
  khớp schema đã commit.
- Kết quả end-state = chính xác schema đã commit; không sửa file code nào
  thuộc module khác.
- Rollback: không cần (upgrade tái tạo constraint; trước đó DB đã lệch schema
  commit — trạng thái hiện tại mới là "sai").

## Trạng thái task khi bị chặn

- Code change SLP-324 **hoàn tất** (osc-fonts.css + fonts + layout include +
  record + CHANGELOG) — xem `TASK-NJSGKM.md` Implementation Notes.
- Chưa chạy được: baseline probe (page không render), static rm/curl, font
  assertions, regression suites.
