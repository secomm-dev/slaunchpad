# DEC-TASKCG6BM7-006 — Round 4: claim/bind hai pha, stale-claim policy, backfill v2 (F17–F22)

Date: 2026-09-16. Status: accepted. Supersedes phần claim của DEC-005 ở khía cạnh credit_memo_id.

## D1 — Claim INSERT luôn `credit_memo_id = NULL`; bind riêng một bước UPDATE (F17)

Admin thật đưa CM **chưa save** (entity_id NULL) vào `CreditmemoService::refund`. Ghi `(int)getEntityId()` = 0 vào claim ⇒ INSERT fail (NOT NULL + FK). Chọn: claim mang NULL (claim cấp ORDER, invariant thống nhất "claim chưa bind"), sau đó `creditmemoRepository->save` (resource gán entity_id thật) rồi `bindCreditMemo` = UPDATE guard `entity_id = ? AND active_claim = 1`. Không chọn ghi có điều kiện entity_id khi đã có — một invariant duy nhất dễ chứng minh, gate provider mang tính cấu trúc. Bind là bước CUỐNGI trước `executePrepared`; bind false (cron nhả stale claim / row đã terminal) ⇒ plugin tự terminate `abandoned_before_provider_io`, KHÔNG gọi provider. Không DB transaction mở xuyên HTTP (INSERT/UPDATE autocommit riêng).

## D2 — Stale-claim (INITIATING + chưa bind) = `confirmed_fail` với evidence `abandoned_before_provider_io` (F18)

Provider gate = claim + m_refund_id + bound credit_memo_id; nếu bind chưa xảy ra thì HTTP **bất khả thi theo cấu trúc** ⇒ tiền chắc chắn chưa rời ⇒ fail-thật (nhả claim + nhả block, user retry được) thay vì UNKNOWN (block vô hạn cho điều đã-chắc). Ranh giới crash duy nhất: giữa claim-acquire và bind (cron step 0b xử lý). Trường hợp lose-race (bind thua cron-release) đi cùng nhánh này một cách an toàn.

## D3 — Cron state-driven theo `refund_state`, CM OPEN là state hợp lệ (F18)

Recovery phải chạy theo state machine bền của row, không theo state CM: INITIATING(bound)/PROCESSING/UNKNOWN query CÙNG m_refund_id dù CM OPEN hay PROCESSING. CM park PROCESSING ngay sau bind (`markProcessing`), park-fail chỉ critical log. Chỉ CANCELED (hủy tay ngoài vòng đời) = drift → terminal reconcile. CM REFUNDED → bookkeeping confirmed_success (không re-run accounting).

## D4 — Backfill v2: cohort evidence-ordered + ownership MIN(entity_id) (F19/F20)

Cohort: (1) `is_processed=1 AND last_error LIKE 'refund_failed:%'` → confirmed_fail; (2) `is_processed=1 AND last_error IS NULL` → confirmed_success (disjoint — hết đè fail); (3) `is_processed=1 AND last_error khác` → UNKNOWN (không suy success từ ambiguous); (4) `is_processed=0` → UNKNOWN. Ownership: row UNKNOWN entity_id MIN mỗi order nhận `active_claim=1` (UPDATE JOIN derived table — né MySQL 1093, deterministic re-run); row còn lại giữ NULL làm evidence. Unique `(order_id, active_claim)` giờ chặn claim mới cho order có refund lịch sử chưa resolved.

## D5 — Unique declarative bằng `<constraint xsi:type="unique">` (F21)

`<index xsi:type="unique">` không hợp lệ theo XSD 2.4.8-p5. Chuyển `ZALO_PAY_REFUND_ORDER_ACTIVE` / `ZALO_PAY_REFUND_M_REFUND_ID_ACTIVE` sang constraint + whitelist mục "constraint". `credit_memo_id` nullable + FK giữ. Chạy thật `setup:upgrade`/`setup:install` trên DB disposable: DEFER cho TL (stack /tmp/m2b + zt-mariadb + zt-opensearch dựng sẵn, P18).

## D6 — Duplicate-key detect chỉ theo driver code 1062 (F22)

`isDuplicateKey`: đi exception chain, PDOException → `errorInfo[1]` (fallback `getCode()`) === 1062; không có PDOException → message 'duplicate entry'. SQLSTATE 23000 generic hoặc substring "1062" KHÔNG đủ (FK 1452 cũng 23000) — FK violation phải là abort an toàn + logger error, không bị ngụy thành claim conflict.
