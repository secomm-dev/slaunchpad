# AI Pre-review: TASK-MZ2TCB — Phase GHN-B dual-scheme master data + mapping

Date: 2026-09-10 · Reviewer: Claude (AI self-review per AGENTS §8.3) · Spec: SPEC-FEAT-FQWEQ3 §3/§5/§6/§7/§8/§42

### Summary

GHN-B hoàn tất tầng address provider của `Secomm_Ghn`: 2 bảng Tier-2 (`secomm_ghn_address_unit`,
`secomm_ghn_address_mapping`), sync CLI độc lập 2 scheme (fetcher legacy + fetcher v3 new-model —
endpoints/fields đã verify docs 2026-09-10), mapping pipeline deterministic (exact name + curated
alias CSV; ambiguity KHÔNG auto-pick; rebuild TX), audit 5-trạng-thái + coverage %, runtime resolver
fail-closed (PRE_2025 ward → legacy triple; 2025 ward → verbatim names) với cache hits-only.
94 unit tests / 260 assertions OK; full scoped suite sạch (7 errors = Tracking baseline).

### Findings

#### Critical (must fix)

- (không có)

#### Warnings (should fix / TL quyết định)

- **[Tier-2 schema — TL review point chính]** Thiết kế bảng: (a) `provider_key` là column
  app-filled (`COALESCE(provider_id, provider_code)`) phục vụ `UNIQUE(scheme_code, provider_key)`
  vì MySQL coi NULL là distinct trong UNIQUE; (b) self-FK `parent_id` onDelete RESTRICT + mapping
  FK onDelete CASCADE (re-sync generator rebuild không đụng unit table nên CASCADE chỉ nổ khi unit
  bị xóa trực tiếp — hiện không có đường xóa nào); (c) `depth` SMALLINT UNSIGNED 1..3. Cần TL
  ack 3 điểm này trước khi `setup:upgrade` lên staging.
- **Coverage map phụ thuộc data thật**: alias CSV seed hiện rỗng (chỉ header); lần sync/audit đầu
  trên sandbox sẽ cho coverage % thật + danh sách AMBIGUOUS/UNMAPPED (dự kiến tương tự audit spike
  §15: một phần ward merge sẽ UNMAPPED/AMBIGUOUS ở chiều 2025→mapping). Export JSON của audit là
  input cho data-authoring task ngoài FEAT.
- **`MappingMatcher` chỉ match `name` ↔ GHN `name`** — `extension_names` của GHN v3 cố ý KHÔNG dùng
  auto-approve (chỉ phục vụ human curation). Đây là quyết định an toàn (exact deterministic theo
  §7) nhưng coverage exact-match có thể thấp hơn kỳ vọng; điều chỉnh sau nếu TL muốn thêm tier
  "extension-name exact" có review.

#### Notes (consider)

- Sync fetch legacy: 1 request/xa (province × district × ward) ≈ hàng nghìn GET khi sync PRE_2025
  (10.6k ward → ~63+699+10.6k ≈ 11.4k requests) — chỉ chạy CLI, cần sandbox rate-limitaware; nếu
  GHN chậm, cân nhắc batch endpoint ở GHN-C (ghi nhận, không block).
- `Admin2025MasterDataFetcher` skip GHN status=10 (deleted) hoàn toàn — không lưu row DISABLED cho
  deleted; nếu muốn audit thấy cả deleted units cần đổi (chỉ 1 dòng).
- `rebuildScheme` delete-then-insert trong transaction; cache flush bằng tag sau commit.
- 6 PHPUnit deprecations (mock-generator) — non-blocking như GHN-A.

### Scope Check

- [x] Changes match implementation plan — khớp TASK-MZ2TCB plan (thêm: `inspectStoredRow` audit
  stale-rows; fetcher 2-pass parent-first — đều thu hẹp gap so với plan)
- [x] No out-of-scope modifications — `git status`: chỉ `app/code/Secomm/Ghn/**` (new files) +
  `app/etc/config.php` (entry enable từ GHN-A) + `.ai/**` artifacts

### High-risk area check (AGENTS §12)

- **DB schema mới → Tier-2**: whitelist đầy đủ (verify bằng script); chưa chạy `setup:upgrade` vì
  DB local down — **TL review schema + chỉ chạy upgrade khi DB khả dụng**.
- **Third-party API contract**: endpoints chỉ dùng path/fields đã verify (bằng chứng evidence §1).
- Không đụng payment/checkout/order; không đụng code ShippingCore/VietNamAddress/legacy.

### Regression Risks

- 0 behavior hiện hữu bị chạm: module mới chưa wire vào rate/carrier; bảng mới, không alter bảng cũ.
- Full suite 1170 tests: Secomm_Ghn 0 error/failure; Tracking baseline 7 không đổi.

### Suggested Tests (QC khi DB up)

1. `setup:upgrade` → 2 bảng + FK + UNIQUE tồn tại (SHOW CREATE TABLE); `db:status` không tăng entry lạ.
2. Sync sandbox từng scheme (`--dry-run` rồi thật): counts khớp GHN portal (34 tỉnh / ~3.3k ward 2025;
   63/699/10.6k legacy); re-sync idempotent (0 new, disabled=0).
3. `audit` 2 scheme: coverage % ghi lại; export JSON unresolved cho data task.
4. Generator dry-run vs thật: rows APPROVED khớp decisions; cache flushed (resolver sau generator
   trả mapping mới).

### Recommendation

**PASS WITH WARNINGS** — sẵn sàng cho TL review. Điểm cần quyết: (1) ack schema Tier-2 3 điểm,
(2) xác nhận policy extension_names không auto-approve, (3) QC chạy khi DB up (checklist ở trên).
