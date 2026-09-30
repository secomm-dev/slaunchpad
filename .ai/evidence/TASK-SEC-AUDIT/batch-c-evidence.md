# Evidence — Batch C (TASK-SEC-C1/C2/C3)

## C1 transaction/partial-save contract — Option B (recoverable partial save)

Lifecycle verified: vendor Save controller try{ `Method::save()` } — AbstractDb::save()
beginTransaction → ... → COMMIT bên trong save(); resource-seam plugin (`afterSave`) chạy
SAU commit → settings persist là POST-COMMIT → atomic-all không khả thi mà không wrap
vendor internals (cấm). Contract chọn: method đã commit; settings+members write NGHIỆT
TRONG MỘT transaction của persister (never partial rows); fail → throw vào catch vendor →
errorMessage + form session restore → retry trên đúng persisted method id, idempotent
(insertOnDuplicate + full member replace). Không success giả (success message chỉ sau cả
2 bước thành công — settings persist chạy TRƯỚC khi controller addSuccessMessage? KHÔNG:
persist trong save() → exception chặn addSuccessMessage ✓).

## afterDelete ownership — DB CASCADE là owner duy nhất

Cả 2 bảng (`…method_setting`, `…method_member`) FK ON DELETE CASCADE → plugin afterDelete
ĐÃ BỎ (đã từng thêm rồi lược theo review) — một ownership path duy nhất, không double-delete.

## C3 per-channel customer-group result

| Channel | Group authority | Kết quả |
|---|---|---|
| Storefront guest | customer session = NOT_LOGGED_IN | đúng |
| Storefront logged-in | session có group (login bridge) | đúng |
| REST guest | NOT_LOGGED_IN | đúng (guest method) |
| REST customer token | KHÔNG có session bridge → session NOT_LOGGED_IN — **đánh giá sai làm guest** | CONFIRMED defect → fix: group từ quote của RateRequest |
| GraphQL guest | NOT_LOGGED_IN | đúng |
| GraphQL customer | core bridge `AddUserInfoToContext` set session group | đúng (bridge sẵn) — fix quote-based cũng phủ |
| Admin order | backend quote session group | đúng (isAdmin branch) |
| Store scope | storeId từ RateRequest → isActive stores check | đúng |

Fix: `LaunchpadMethod::isActive($storeId, ?int $customerGroupId = null)` — provider resolve
group từ `RateRequest items → quote` (trustworthy mọi channel, kể cả plain REST); null →
parent session semantics (standalone Mageplaza nguyên vẹn; signature BC — thêm param optional).
Type-check concrete vendor class: LaunchpadMethod EXTENDS vendor Method → mọi instanceof OK.
Per-channel runtime E2E: BLOCKED_BY_ENVIRONMENT — static + characterization test (2) là
evidence hiện có; các channel chưa verify runtime liệt kê ở blocker.
