# TASK-EDS9T5 — Real-DB integration proof: `getBlockingAttemptByQuoteId()`

Corrective round 5 continuation (2026-09-11). Phiên trước ghi nhận
`DB_INTEGRATION=ENVIRONMENT_BLOCKED`; phiên này MariaDB thật đang chạy trong
stack Docker (`slaunchpad-db-1`, MariaDB 10.4) nên integration thật đã thực
hiện được. **Kết quả: 15/15 PASS, exit 0** (`final-output.txt`).

## Mức thật đạt được

Production method **`Secomm\ZaloPay\Model\PaymentAttemptRepository::
getBlockingAttemptByQuoteId()`** chạy END-TO-END trên database thật:

- Class production load **verbatim từ worktree** tại HEAD `aabe4802`
  (`classes/Secomm/ZaloPay/{Api,Model}/...` — không sửa gì).
- Adapter **`Magento\Framework\DB\Adapter\Pdo\Mysql` THẬT** — renderer wiring
  copy từ `app/etc/di.xml` (10 part renderer, đủ from/where/order/limit/...).
- Collection **`PaymentAttemptCollection` THẬT** — chỉ `_construct()` được thay
  (bản production sẽ qua `ObjectManager::create()`); mọi call
  `addFieldToFilter`/`setOrder`/`setPageSize`/render/execute/hydrate là code
  thật, thực thi SQL thật trên MariaDB thật.
- Query thật mà repository tự sinh (capture từ collection do nó tạo ra qua
  factory stub, sau `load()`):

```sql
SELECT `main_table`.* FROM `secomm_zalopay_payment_attempt` AS `main_table`
WHERE (`quote_id` = '990101') AND ((`payment_status` IN('paid', 'finalized'))
OR (`requires_reconciliation` = 1)) ORDER BY entity_id DESC LIMIT 1
```

## Ma trận 15/15 PASS

| # | Case | Kết quả |
|---|---|---|
| 1 | PAID chặn second payment | PASS |
| 2 | Row PAID hydrate bằng typed getter thật (round-trip DB) | PASS |
| 3 | FINALIZED chặn | PASS |
| 4 | `requires_reconciliation=1` (status thường FAILED bên dưới) chặn | PASS |
| 5 | Row quarantine hydrate `flag=true` (round-trip DB) | PASS |
| 6 | FAILED/EXPIRED/STALE thường KHÔNG chặn (retry hợp lệ) | PASS |
| 7 | EXPIRED/STALE thường KHÔNG chặn | PASS |
| 8 | `recovery_exhausted=1` đơn thuần (marker operational, KHÔNG nằm trong filter) KHÔNG chặn | PASS |
| 9 | Quote mixed (FAILED trước, PAID sau) trả về blocking row | PASS |
| 10 | Latest entity thắng (entity_id DESC + LIMIT 1) | PASS |
| 11 | Fixture sanity (row tồn tại thật trong DB) | PASS |
| 12 | Quote không có attempt → null | PASS |
| 13 | `quote_id=0` → null, không đụng DB | PASS |
| 14 | Assembled SQL chứa đủ 2 nhánh OR | PASS |
| 15 | Query repository render `ORDER BY entity_id DESC LIMIT 1` | PASS |

## Hạ tầng & phạm vi

- Database **throwaway** `zalopay_r5_it`: `run.sh` CREATE → DDL (dịch 1:1 từ
  `etc/db_schema.xml`, **bỏ FK** sang quote/sales_order vì bảng đó không tồn
  tại trong DB throwaway và query không tham chiếu) → test → **DROP**. DB
  `magento` (shared) KHÔNG bị đọc/ghi.
- Chạy trong `slaunchpad-phpfpm-1` (PHP 8.3.20) — file chỉ trong `/tmp` của
  container; worktree KHÔNG bị thay đổi (`git status` sạch trước/sau).
- Docker note: daemon đã được restart lúc bắt đầu phiên; stack slaunchpad
  start lại **không kèm tunnel** (`--scale tunnel=0`) vì project `layup`
  đang giữ container-name `cloudflared-tunnel` và host ports 3306/6379/…
  (dùng override `ports: !reset []` trong `/tmp` để 2 stack cùng sống).
  Không đụng layup.

## Substitutions công khai (tối thiểu, ngoài app boot)

- Generated factories (không nằm trong git) → stub tối thiểu cùng FQCN:
  `Model/PaymentAttemptFactory.php`,
  `Model/ResourceModel/PaymentAttempt/PaymentAttemptCollectionFactory.php`
  (trong `classes/` — CHỈ 2 file này + `It/*` là scaffolding; các file
  production khác là bản verbatim của worktree).
- Hydrated item là `It\ItAttempt extends PaymentAttempt` với ctor rỗng
  (ctor `AbstractModel` cần Context/Registry DI của app đã boot) + gán
  `_idFieldName = 'entity_id'` — đúng kết quả mà `_init()` production tạo ra.
- `It\ItResource extends PaymentAttemptResource`: trả adapter thật + tên
  bảng thật (hằng số như production `_construct()`); `getTable()` là identity
  (DB throwaway không có prefix — đúng hành vi `getTable()` thật khi không
  cấu hình prefix).
- FetchStrategy = `Magento\Framework\Data\Collection\Db\FetchStrategy\Query`
  THẬT (`$select->getConnection()->fetchAll($select)`).

## Warning đã truy vết (không thuộc production)

`Warning: Array to string conversion @ zend-db/.../Pdo/Abstract.php:79`
phát sinh trong `_dsn()` của Zend khi lần đầu connect: harness tự dựng config
array có `driverOptions` (array) → interpolation DSN. Backtrace (probe
`set_error_handler`) KHÔNG có frame `Secomm\*` nào; connection vẫn thành công
và toàn bộ query chạy đúng. Trên runtime Magento thật, config đi qua DI và
PDO dsn builder xử lý riêng — không phát sinh warning này.

## Tái lập

```bash
# yêu cầu: stack slaunchpad đang chạy (phpfpm + db)
.ai/evidence/TASK-EDS9T5/db-integration/run.sh
```
