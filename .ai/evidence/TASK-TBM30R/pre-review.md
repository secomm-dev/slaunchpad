# AI Pre-review: TASK-TBM30R — Phase GHN-B2 address dataset lifecycle

Date: 2026-09-10 · Reviewer: Claude (AI self-review per AGENTS §8.3) · Spec: SPEC-TASK-TBM30R

### Summary

Đổi mapping lifecycle theo DEC-FEATFQWEQ3-002: xóa auto-APPROVED (`MappingGenerator` + `rebuildScheme`),
thêm dataset layer (exporter deterministic CSV + manifest, master/mapping importers fail-loud
APPROVED-only UPSERT, suggester workfile), bundled `data/` bootstrap, audit +dangling/duplicate/
production_ready, CLI `export|import|suggest`, admin read-only dataset info. `UnitPersister` dùng
chung cho API-sync và file-import. Resolver/fetchers/schema không đổi. 121 tests / 345 assertions OK.

### Findings

#### Critical (must fix)

- (không có)

#### Warnings (should fix / TL quyết định)

- **Bundled dataset là placeholder v0.0.0-empty** (header-only + manifest): bootstrap hoạt động
  deterministic (no-op 0 rows) nhưng mapping production THỰC chỉ có sau chu trình export → offline
  review → import → commit lại `data/`. Đây là chủ đích spec (§17 QC gate cần credentials + MySQL),
  nhưng cần TL ack rằng release đầu tiên của module KHÔNG có mapping dùng được cho tới khi dataset
  thật được author + commit. README đã ghi rõ.
- **Conflict policy**: importer từ chối 2 canonical units APPROVED trỏ cùng 1 GHN unit (1:1 enforced
  per scheme). Nếu sau này cần many-to-one (vd 2 canonical ward cùng trỏ 1 GHN ward sau merge),
  phải nới policy + spec (hiện tại fail-loud).
- **`testSameFileImportsAcrossDifferentEntityIdDatabases`** dùng mock resource "DB thứ hai" thay vì
  2 DB thật — bằng chứng portability ở mức unit; AC-L12 (MySQL) sẽ chứng minh trên DB thật.

#### Notes (consider)

- PHP `fputcsv` quote mọi field chứa space (deterministic, ok) — documented trong test.
- Importer map large file đọc toàn bộ vào memory (10k rows CSV ≈ vài MB — ok; không streaming vì
  cần fail-loud toàn bộ trước khi write).
- Admin chỉ có read-only dataset info (§13 cho phép CLI-primary); export/audit admin buttons
  deferred — nếu TL muốn, làm task nhỏ kế tiếp.
- Full-suite 44 errors: 37 Ghtk (stream song song TASK-7AJ3K8 in-flight) + 7 Tracking baseline;
  Secomm_Ghn 0.

### Scope Check

- [x] Changes match implementation plan (TASK-TBM30R plan; thêm `rebuildScheme` removal + admin
  info block — thu hẹp đúng spec)
- [x] No out-of-scope modifications — git: chỉ `app/code/Secomm/Ghn/**` + `.ai/**` (config.php
  entry từ GHN-A); không đụng Ghtk/ShippingCore/VietNamAddress/legacy

### High-risk area check (AGENTS §12)

- Production mapping activation path: giờ là MỘT đường duy nhất (`MappingImporter`) với fail-loud
  validation + APPROVED-only — giảm bề mặt rủi ro so với auto-mapping trước đây.
- Không schema change, không thay đổi runtime resolver, không đụng checkout/rate.

### Regression Risks

- Behavior cũ "generator tự ghi APPROVED" bị xóa CÓ CHỦ ĐÍCH — ai đang rely trên generator CLI-less
  flow (không có command nào gọi) không bị ảnh hưởng runtime.
- Full suite: Secomm_Ghn 0 lỗi; các lỗi Ghtk/Tracking thuộc dòng work khác (evidence §4).

### Suggested Tests (QC khi DB up — AC-L12)

1. `setup:upgrade` → 2 bảng vẫn đúng (không đổi schema) → bootstrap `secomm:ghn:address:import`
   (no-op, manifest v0.0.0-empty).
2. Export sandbox 2 scheme (cần credentials) → counts khớp GHN portal → compare schema/file.
3. Offline review workfile (`suggest`) → import bản reviewed test → audit production_ready →
   resolver resolve theo DB thật → cache flush sau import.

### Recommendation

**PASS WITH WARNINGS** — sẵn sàng cho TL review. Cần ack: (1) placeholder bundled dataset policy,
(2) 1:1 conflict policy, (3) AC-L12 QC khi MySQL/credentials sẵn sàng.
