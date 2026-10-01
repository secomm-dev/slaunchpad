# Evidence — TASK-S52DGA (Generic Offline Shipment P1)

Ngày: 2026-09-30 · Branch: development · Layer trên working tree TASK-W5BW4F (uncommitted).

## 1. Unit suites (phpunit-secomm.xml)

| Suite | Kết quả | Ghi chú |
|---|---|---|
| Secomm_ShippingCore | **594 OK** (555 baseline + 39 mới) | pool 5, resolver 6, metadata persister 5, session 4, intent observer 7, fulfillment observer 5, OfflineControl VM 4, FulfillmentStatus 2, PackagingBlockPlugin +1 |
| Secomm_Ghn | **446 OK** (438 baseline + 8 mới) | save-validation observer +3 (skip-on-intent, stash+hint, non-eligible-no-stash), create observer +2 (intent skip, metadata re-save skip), GhnOfflineCapability 3 |
| Secomm_Cod | **48 OK** | không đổi (offline = 0 call vào Secomm_Cod) |

Pre-existing failures (không liên quan, chứng minh bằng `git status` trống + không ref
ShippingCore/Ghn từ test/subject): Secomm_CodRisk PhoneNormalizer 1 FAIL; Secomm_FulfillmentCore
StatusMapResolver/InboundUpdateApplier 3 FAIL.

## 2. Compile + validator

- `php bin/magento setup:di:compile` — OK (chạy lại sau khi thêm `OfflineRecordingState`).
- `bash .ai/bin/project-ai-validate --check-records --check-specs` — **56 FAIL = baseline
  stash-verified 2026-09-30**, 0 FAIL/WARN trỏ tới TASK-S52DGA/DEC-TASKS52DGA-001/file mới.

## 3. Invariant greps (task §32)

- `CodCollectionResolver` trong ShippingCore: chỉ doc mention USER_GUIDE (0 trong code) ✓
- `RateSourceMode|FallbackEligibility` trong seam mới: chỉ docblock nêu rõ "không liên quan" ✓
- Không `extension_attributes.xml` mới ✓ · không ACL mới · không controller mới · không bảng mới
- `insertPending` chỉ trong GHN/Ghtk modules (anchor provider không đụng) ✓
- Không GHN limit số cứng trong ShippingCore ✓
- `fulfillment_mode` param chỉ trong seam files + template ✓
- `PackagingBlockPlugin` strip cả 2 marker (`secomm_physical`, `secomm_fulfillment`) ✓

## 4. Code-level E2E (dev DB thật — `e2e_offline_shipment.php`)

Chạy cơ chế save native giống hệt core Save controller (ShipmentDocumentFactory → register()
→ DB\Transaction, offline intent trong request):

```
PASS order canShip
PASS A1 Magento shipment created — shipment_id=19
PASS A2 fulfillment marker persisted            (secomm_fulfillment.mode = OFFLINE)
PASS A2b physical facts persisted — [[48600,300,300,300]]   (facts, KHÔNG validate)
PASS A3 no GHN provider anchor — rows=0
PASS A4 no COD ledger claim — rows=0
PASS A5 no tracking row — rows=0
PASS A6 offline history comment — "Offline shipment created — no secomm_ghn order was requested (reason: INVALID_PARCEL)…"
PASS B1 re-save creates no GHN anchor — rows=0   (C1: metadata-arm chặn re-save)
PASS C1 resolver reads persisted OFFLINE
PASS C2 request intent detected
PASS C3 absent intent is ONLINE
E2E RESULT: ALL PASS
```

Run 1 (trước fix) để lại shipment 18 (order 13) thiếu marker — artifact dev-db, marker đã fix,
dữ liệu vô hại (không anchor/track); dọn tay nếu cần.

## 5. Defect tìm thấy trong quá trình dev (đã fix + test)

- **Persist-tự-chặn (E2E bắt được)**: persist metadata re-save shipment → save_before re-fire
  → intent observer thấy `entity_id>0` + intent → từ chối chính persist của mình. Fix:
  `OfflineRecordingState` (guard per-process) — intent observer đứng xuống khi đang recording;
  fulfillment observer dùng nó làm in-flight guard (thay static riêng). Test:
  `testIntentOnAResaveDuringRecordingIsAllowed` + `testPersistReFireIsGuarded`.
- Observer thiếu inject `HttpRequest` (test bắt TypeError) — fix ctor.
- `OfflineEligibilitySession` ban đầu type-hint `Session\Proxy` (class generated, không
  mockable được) — đổi sang `Magento\Backend\Model\Session` (setData đi qua SessionManager
  `__call` magic, production-verified; test mô phỏng `__call`).
- Gate hint dùng `getRawMessage()` (template %N chưa render) — đổi sang
  `(string) getMessage()` (đã render).

## 6. ADMIN_SMOKE (task §29)

**BLOCKED_BY_ENVIRONMENT** — môi trường agent không có browser. Đã thay thế bằng code-level
E2E ở trên (cùng cơ chế save). Case A/B/C cho người chạy tay (WSL: `127.0.0.1` + Host header):

- **Case A**: order GHN → tạo shipment, package 300×300×300 → Submit bị chặn + hint → nút
  "Create Offline Shipment" (section note tuỳ chọn) → confirm → shipment tạo; shipment view
  hiện "Fulfillment" (Offline / secomm_ghn / Not Created / reason) + banner offline trong
  "GHN Shipment" + bảng packages.
- **Case B**: package hợp lệ → Submit thường → GHN create như cũ, KHÔNG marker fulfillment.
- **Case C**: flatrate order → nút ẩn; POST crafted `fulfillment_mode=OFFLINE` → bị từ chối
  "not available for this shipping method"; add comment vào shipment offline → không GHN
  create mới (log `var/log/system.log` "Offline shipment recorded." chỉ 1 dòng/shipment).
