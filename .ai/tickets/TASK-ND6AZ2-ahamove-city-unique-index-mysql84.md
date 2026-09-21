# TASK-ND6AZ2 — Ahamove: `ahamove_city.city_id` phải là UNIQUE constraint (MySQL 8.4 FK requirement)

**Type:** Schema fix (declarative schema)
**Priority:** High (block `setup:upgrade` trên MySQL 8.4)
**Estimate:** ~1h
**Mode:** A (chạm generic risk category: DB / data-migration — RM decision tree §9)
**Risk tier:** Tier 2 (schema change → **chờ TL review trước khi modify code / chạy `setup:upgrade`**)
**Author:** AI draft · **Date:** 2026-09-07
**Status:** Applied (local) · AC4 pre-check PASS (staging-parity 2026-09-08) · re-run R2 trên live staging trước `setup:upgrade` · TL approve: 2026-09-07
**Specification:** TASK-ND6AZ2 — Mini-Spec embedded (this file)
**Related:** `app/code/Secomm/Ahamove/etc/db_schema.xml` · db hiện trạng local: db `launchpad` @ mysql84:3307

## Description

MySQL 8.4 bỏ tolerance của 5.7: foreign key **chỉ** được tham chiếu cột cha có unique/PRIMARY key (leftmost prefix). Schema hiện tại của module vi phạm điều này:

- [db_schema.xml:19-21](../../app/code/Secomm/Ahamove/etc/db_schema.xml#L19-L21) khai báo `AHAMOVE_CITY_CITY_ID` là **index btree thường** trên `ahamove_city.city_id`
- [db_schema.xml:45-47](../../app/code/Secomm/Ahamove/etc/db_schema.xml#L45-L47) khai báo FK `ahamove_city_detail.city_id → ahamove_city.city_id` (ON DELETE CASCADE)

Hệ quả quan sát được (local dev 2026-09-07):

| Hiện tượng | Môi trường |
|---|---|
| Import/CREATE bảng fail `ERROR 6125: Failed to add the foreign key constraint. Missing unique key` | MySQL 8.4 (local `launchpad`) |
| `setup:upgrade` sẽ fail cùng lỗi nếu được chạy trên MySQL 8.4 | MySQL 8.4 |
| Db local đã patch tay UNIQUE (khắc phục khi import) → lệch với declarative schema → `setup:db:status` báo diff | local |
| Staging (5.7) vẫn chạy OK với index thường | staging 5.7 |

## Audit result (evidence, 2026-09-07)

| Fact | Giá trị | Nguồn |
|---|---|---|
| MySQL 5.7 | cho phép FK trỏ index thường | dump `fashion_launchpad` (đang chạy staging-parity) |
| MySQL 8.4 | `ERROR 6125` khi FK trỏ index non-unique | import log local |
| Whitelist FK `ahamove_city_detail` | `AHAMOVE_CITY_DETAIL_CITY_ID_AHAMOVE_CITY_CITY_ID` — khớp db, Magento đặt tên FK theo pattern `{table}_{column}_{refTable}_{refColumn}`; `referenceId` XML chỉ là id nội bộ | `db_schema_whitelist.json` |
| Data local | `ahamove_city` = 0 rows, `ahamove_city_detail` = 0 rows | query 2026-09-07 |
| Data staging | **chưa kiểm tra duplicate `city_id`** — bắt buộc pre-check trước khi apply | — |

> **Correction so với nhận định ban đầu của AI:** không có "lệch tên FK" giữa db và schema — whitelist khớp db. Diff duy nhất là **kiểu của `AHAMOVE_CITY_CITY_ID`** (index thường vs unique).

---

## Mini Spec: TASK-ND6AZ2 (embedded, MINI)

| Field | Value |
|-------|-------|
| Specification ID | TASK-ND6AZ2 — Mini-Spec embedded, identity = chính ticket |
| Specification Level | MINI |
| Ticket | TASK-ND6AZ2 (file này) |
| Mode | A |
| Author | AI draft |
| Date | 2026-09-07 |
| Estimate | ~1h |

### Goal

Declarative schema của `Secomm_Ahamove` khai báo `ahamove_city.city_id` là **unique constraint**, để FK từ `ahamove_city_detail` hợp lệ trên MySQL 8.4+ và schema-diff Ahamove khỏi `setup:db:status` = 0.

### Expected Behavior

1. `db_schema.xml`: `<index referenceId="AHAMOVE_CITY_CITY_ID" indexType="btree">` → `<constraint xsi:type="unique" referenceId="AHAMOVE_CITY_CITY_ID">` (cùng cột `city_id`, cùng tên index trong DB — giữ `AHAMOVE_CITY_CITY_ID`).
2. `db_schema_whitelist.json`: entry `"AHAMOVE_CITY_CITY_ID": true` chuyển từ `index` sang `constraint` của bảng `ahamove_city`.
3. **Local (8.4, db đã UNIQUE tay):** sau change, diff phần Ahamove = 0 — KHÔNG cần chạy `setup:upgrade` riêng cho ticket này.
4. **Staging/prod (5.7):** lần `setup:upgrade` kế tiếp sẽ `ALTER` KEY → UNIQUE KEY; hành vi app không đổi (cùng cột, cùng tên, chỉ thêm uniqueness — `city_id` là mã city Ahamove, về nghiệp vụ vốn duy nhất).
5. Fresh install trên MySQL 8.4: tạo đủ 2 bảng + FK không lỗi.

### Constraints / Rules

- **R1:** Chỉ được sửa `app/code/Secomm/Ahamove/etc/db_schema.xml` + `db_schema_whitelist.json`. Không đụng code PHP, module khác, hay data.
- **R2:** Trước khi chạy `setup:upgrade` trên MỌI môi trường có data, bắt buộc pre-check duplicate:
  `SELECT city_id, COUNT(*) c FROM ahamove_city GROUP BY city_id HAVING c > 1;` — nếu có kết quả → DỪNG, xử lý dedup là việc riêng (ngoài ticket này).
- **R3:** `setup:upgrade` là migration GLOBAL — sẽ kéo theo toàn bộ schema-diff pre-existing đã biết (backlog ~315 modify_column). Ticket này KHÔNG bao gồm việc chạy `setup:upgrade` trên staging/prod; chỉ local được chạy sau khi TL duyệt và review backlog diff.
- **R4:** Giữ nguyên tên index `AHAMOVE_CITY_CITY_ID` (tránh drop/recreate index thừa, khớp db đang có).
- **R5:** Không đổi `referenceId` của FK trong XML (đã khớp whitelist/db — đổi chỉ gây noise).

### Acceptance Criteria

- **AC1:** `bin/magento setup:db:status` trên local (MySQL 8.4): không còn bất kỳ diff nào liên quan `ahamove_city`/`ahamove_city_detail` (banner "not up to date" tổng có thể còn do backlog pre-existing — ngoài scope).
- **AC2:** Trên MySQL 8.4, test fresh-create 2 bảng theo schema mới (db tạm) → không `ERROR 6125`, FK tạo thành công.
- **AC3:** `bin/project-ai-validate --check-specs` và `--check-identity` PASS với record này.
- **AC4:** Kết quả pre-check duplicate `city_id` trên staging được ghi vào record này (section Open Questions) trước khi ticket được approve để apply lên staging.

### Out of Scope

- Dedup/seed data `ahamove_city` (0 rows local; data thật do sync Ahamove populate).
- Reconcile backlog ~315 schema-diff pre-existing của db.
- Nâng cấp staging/prod lên MySQL 8.x.
- Bất kỳ thay đổi logic shipping/Ahamove.

## Approach

```diff
--- app/code/Secomm/Ahamove/etc/db_schema.xml
-        <index referenceId="AHAMOVE_CITY_CITY_ID" indexType="btree">
+        <constraint xsi:type="unique" referenceId="AHAMOVE_CITY_CITY_ID">
             <column name="city_id"/>
-        </index>
+        </constraint>
```

```diff
--- app/code/Secomm/Ahamove/etc/db_schema_whitelist.json (table "ahamove_city")
-  "index": {
-    "AHAMOVE_CITY_CITY_ID": true
-  },
   "constraint": {
-    "PRIMARY": true
+    "PRIMARY": true,
+    "AHAMOVE_CITY_CITY_ID": true
   }
```

Steps: (1) TL review ticket → (2) apply diff → (3) AC2 fresh-create test → (4) AC1 `setup:db:status` → (5) AC3 validate → (6) AC4 staging pre-check khi deploy.

## Open Questions

- [x] Staging: duplicate `city_id` trong `ahamove_city`? → **KHÔNG có duplicate** (pre-check 2026-09-08 — xem Execution Log AC4). Chạy trên staging-parity snapshot `fashion_launchpad` @ docker `mysql` 5.7 (chưa có access live staging từ local) → luật R2 vẫn giữ nguyên: re-run query trên live staging ngay trước `setup:upgrade`.
- [ ] Backlog ~315 diff pre-existing: có gom 1 ticket reconcile riêng trước khi ai đó chạy `setup:upgrade` local không?

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Staging có duplicate `city_id` → `setup:upgrade` fail khi tạo unique | Low (mã city Ahamove về bản chất duy nhất) | R2 pre-check bắt buộc; dedup riêng nếu xảy ra |
| `setup:upgrade` kéo theo diff pre-existing ngoài Ahamove | Certain (backlog đã biết) | R3: tách bạch — ticket này chỉ audit diff Ahamove |
| UNIQUE thêm overhead ghi | Negligible (bảng ít row, write hiếm) | — |

---

## Execution Log

**2026-09-07 — Applied (local), TL approve cùng ngày:**

| AC | Kết quả | Evidence |
|----|---------|----------|
| AC1 | ✅ Pass (phần Ahamove) | Db `launchpad`@mysql84:3307: `AHAMOVE_CITY_CITY_ID` NON_UNIQUE=0, cols=`city_id`; PRIMARY(`entity_id`,`city_id`); FK pattern-name khớp whitelist. Declaration ↔ db = khớp. Banner "not up to date" tổng còn do backlog pre-existing (R3 — ngoài scope) |
| AC2 | ✅ Pass | Db tạm `_ac2_test` trên MySQL 8.4.7: CREATE 2 bảng theo schema mới → FK tạo thành công (fk_count=1), không ERROR 6125; db đã DROP sau test |
| AC3 | ✅ Pass | `project-ai-validate --check-specs` và `--check-identity`: 0 lỗi cho ND6AZ2 (repo còn 7 FAIL pre-existing nhóm FEAT-YA2C0W — đã có sẵn, ngoài scope) |
| AC4 | ✅ Pass (staging-parity) | Pre-check 2026-09-08 trên `fashion_launchpad` @ docker `mysql` (MySQL 5.7, :3306). R2 query `SELECT city_id, COUNT(*) c FROM ahamove_city GROUP BY city_id HAVING c > 1` → **0 row**. `ahamove_city`=0 rows, `ahamove_city_detail`=0 rows. Index hiện trạng khớp giả định ticket: `AHAMOVE_CITY_CITY_ID` Non_unique=1, PRIMARY(`entity_id`,`city_id`). Snapshot mang data thật (15 orders, order cuối 2026-09-03; import 2026-09-07) → bảng rỗng là hiện trạng staging, không phải dump strip data. **Caveat:** snapshot ≠ live staging — re-run R2 trên live staging ngay trước `setup:upgrade` |

Diff applied: `db_schema.xml` (index → unique constraint) + `db_schema_whitelist.json` (entry chuyển `index` → `constraint`). `setup:upgrade` KHÔNG chạy trên local (không cần — db đã unique sẵn từ import, đúng Expected Behavior #3).
