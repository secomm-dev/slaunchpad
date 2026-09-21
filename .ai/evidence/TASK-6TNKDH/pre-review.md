# AI Pre-review: TASK-6TNKDH — Phase GHN-B.2 (giai đoạn 1 unblocked)

Date: 2026-09-10 · Reviewer: Claude (AI self-review per AGENTS §8.3) · Spec: SPEC-TASK-6TNKDH

### Summary

Giai đoạn 1 của dataset authoring: hoàn thiện tooling (suggester `suggestFromExport` + review
artifact §12 + CLI options), integrity gate file-to-file cho bundled `data/`, và — quan trọng nhất —
**phục hồi môi trường để schema Tier-2 của Ghn lần đầu được Magento validate thật**: phát hiện +
sửa 1 defect schema thật (`onDelete="RESTRICT"` không hợp lệ), `setup:upgrade` SUCCESS, bootstrap
e2e chạy trên DB sống không cần GHN API. Giai đoạn 2 (export/review/commit dataset thật) BLOCKED
do GHN sandbox credentials không tồn tại trong môi trường — không fake (SPEC §15).

### Findings

#### Critical (must fix)

- (không có trong phạm vi code giai đoạn 1)

#### Warnings (should fix / TL quyết định)

- **[BLOCKER — cần owner action]** GHN sandbox credentials không có trong DB (0 config rows).
  Giai đoạn 2 không thể bắt đầu cho tới khi owner cấu hình trong admin:
  **Sales → Delivery Methods → GHN Shipping (Secomm_Ghn)** (`carriers/secomm_ghn/*` — config đã
  chuyển vào section chuẩn theo TL directive 2026-09-10). Đây là input đúng người — credentials
  không đi qua AI.
- **[Cần TL duyệt approval rule]** cho offline review pass: đề xuất exact-normalized-name 1:1
  trong đúng parent scope → APPROVED (note provenance); còn lại giữ REVIEW_REQUIRED/UNRESOLVED.
  Rule này là quyết định review CÓ CHỦ ĐÍCH được ghi hồ sơ trước — khác auto-approve im lặng
  mà DEC-FEATFQWEQ3-002 cấm.
- **Defect schema GHN-B đã lộ + đã sửa**: `onDelete="RESTRICT"` → `NO ACTION`. Đây là bằng chứng
  cho giá trị của QC gate §17: schema chỉ thực sự validated khi DB sống. Whitelist không cần đổi
  (onDelete không nằm trong whitelist keys).

#### Notes (consider)

- Stale `generated/metadata/*.php` (từ stream song song Ghtk) phá CLI toàn cục — đã xử lý phiên;
  nếu CLI lại chết cùng lỗi đó, nguyên nhân là working tree đang dở của stream kia, không phải Ghn.
- `BundledDatasetIntegrityTest` chứa 1 SKIP visible (placeholder-gate) — sẽ tự chuyển thành assert
  cứng khi dataset 1.0.0 được commit (flip marker trong plan bước 7).
- suggester review artifact ghi per-scheme (`GHN_ADDRESS_MAPPING_REVIEW_<scheme>.csv`) thay vì 1
  file chung — tách bạch scheme pair, ghép khi authoring nếu cần.

### Scope Check

- [x] Changes match plan (giai đoạn 1 đúng plan; thêm schema fix — defect thật, được SPEC §1 cho phép "blocking defect")
- [x] No out-of-scope modifications — không đụng Secomm_VietNamAddress (chỉ đọc), không đụng code Ghtk (chỉ dọn generated/ cache của Magento), không rate/shipment

### High-risk area check (AGENTS §12)

- Schema Tier-2: setup:upgrade thật đã pass — bằng chứng mạnh hơn lint/XSD-local.
- Dataset production: integrity gate + fail-loud importers là đường duy nhất.
- Credentials: không được đọc/ghi/log bởi AI — owner cấu hình trong admin.

### Regression Risks

- `NO ACTION` thay `RESTRICT`: cùng hiệu lực enforce trong InnoDB; hành vi FK không đổi.
- Suggester mở rộng backward-compatible (tham số mới optional; report thêm keys mới).

### Suggested Tests (giai đoạn 2 — sau credentials)

Export counts vs GHN portal · workfile stats vs audit · import cross-entity-id thật trên 2 DB ·
resolver smoke: 1 ward 2025 (verbatim names) + 1 ward PRE_2025 (triple) · refresh-cycle DISABLED
bằng cách export thiếu 1 unit có kiểm soát.

### Recommendation

**PASS WITH WARNINGS** cho giai đoạn 1. Task chuyển trạng thái chờ 2 input người: (1) GHN sandbox
credentials (owner cấu hình trong admin), (2) TL duyệt approval rule. Sau đó giai đoạn 2 chạy
theo checklist evidence §5.
