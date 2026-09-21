---
id: TASK-6TNKDH
type: task
title: 'Phase GHN-B.2 — Initial GHN address dataset authoring: real versioned master + reviewed mappings for bundled bootstrap'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-TASK-6TNKDH — chuẩn hóa từ TL/SA directive 2026-09-10 (follow-up TASK-TBM30R)
specification_ref: ../../specs/SPEC-TASK-6TNKDH-secomm-ghn-initial-dataset-authoring.md
risk: high                    # production address dataset + first-install bootstrap correctness
status: in_progress           # activated 2026-09-10 — directive + Mini-Spec + plan đủ (DEC-TASKZ132WA-002)
priority: high
decision_assessment: material   # approval rule cho exact-name review cần TL ack (xem plan §Open questions)
decisions: [DEC-FEATFQWEQ3-002, DEC-FEATFQWEQ3-001]
components:
  - CMP-GHN
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/Ghn/
  - .ai/scripts/
created: 2026-09-10
updated: 2026-09-11
owner: [dev]
related_tickets: [TASK-TBM30R, TASK-MZ2TCB]
changes_project_state: true
amendments: |
  2026-09-10 — TL directive: carrier config chuyển từ tab riêng `secomm_ghn/general/*` sang section
  chuẩn Delivery Methods (`carriers/secomm_ghn/*`) — system.xml (group `secomm_ghn` dưới section
  `carriers`), config.xml defaults, Model\Config constants đồng bộ; acl.xml xóa (section owned by
  Magento_Sales); ConfigTest + README guard path mới; system.xml XSD VALID. Scope: TASK-RJFTPZ
  output amended (GHN-A), không đụng lifecycle GHN-B/B2.
---

# [SLP][FEAT-FQWEQ3][TASK-6TNKDH] Phase GHN-B.2 — Initial GHN address dataset authoring: real versioned master + reviewed mappings for bundled bootstrap

## Embedded Mini-Spec

*(behavioral contract — đầy đủ tại specs/SPEC-TASK-6TNKDH-secomm-ghn-initial-dataset-authoring.md, FULL)*

### Goal

Thay placeholder `v0.0.0-empty` bằng dataset reviewed thật trong `data/`: 2 GHN master CSV (export
từ GHN API với credentials thật — KHÔNG bịa rows), 2 reviewed mapping CSV (offline hierarchy-aware
matching giữa canonical `Secomm_VietNamAddress` CSVs và GHN export), review artifact cho
unresolved/ambiguous, manifest version thật. First-install bootstrap không cần GHN API.

### Expected Behavior

1. Export cả 2 scheme bằng exporter GHN-B2 với credentials hợp lệ; values verbatim; WardCode string.
2. Offline matching hierarchy-aware (p→w / p→d→w), candidate evidence gồm exact/normalized/
   extension_names/alias/hierarchy; fuzzy chỉ suggest; KHÔNG bỏ diacritics mặc định; chỉ APPROVED
   được activate; 1:1 fail-loud giữ nguyên.
3. Review artifact `GHN_ADDRESS_MAPPING_REVIEW.csv` đủ context 2 phía (hierarchy + name + reason).
4. Import → audit → resolver 2 path; bootstrap clean-DB không gọi GHN API; refresh cycle DISABLED-
   not-delete; reviewed mapping không bị sync đè.
5. Tests integrity file-to-file (manifest/checksum/counts/portable identity/approved-resolves/
   provider-key-exists/no-duplicate); giữ tests hiện hữu.

### Constraints / Rules

- Scope: `app/code/Secomm/Ghn/**` + `.ai/**` (đọc `Secomm_VietNamAddress` Files làm input, không
  sửa module đó trừ blocking defect).
- Không invent provider rows; không fake completion cho unresolved thật (§15 — dataset giữ
  non-production-ready + ghi nhận).
- Không credentials trong chat/logs/artifacts; owner cấu hình trong admin.
- Không mở rộng sang GHN-C/rate/shipment/VietMap/checkout/carrier khác.

### Out of Scope

Rate/leadtime, shipment, VietMap, NO_MATCH authoring ngoài unresolved của dataset này, checkout,
carrier khác, thay đổi schema CSV đã duyệt (nếu defect → material decision trước).

### Acceptance Criteria

AC-D1..AC-D8 của SPEC-TASK-6TNKDH (tóm tắt: dataset thật thay placeholder · import pass portable ·
audit production_ready HOẶC unresolved được ghi nhận trung thực · resolver verbatim/triple · clean
bootstrap không API · không DB id · không AI/fuzzy runtime · tests + evidence + governance đủ).

## Plan

`../plans/TASK-6TNKDH-implementation-plan.md`

## Progress Log

- **2026-09-10 — phase 1 done** (tooling authoring): suggest `--export-dir` + review artifact 2 phía;
  `BundledDatasetIntegrityTest` (manifest checksum/count, portable identity, hierarchy
  parent-complete, APPROVED resolvable cả 2 phía, 1:1); schema fix `onDelete` RESTRICT → NO ACTION;
  bootstrap e2e placeholder trên DB sống, không gọi GHN API.
- **2026-09-11 — phase 2 dev-complete (chờ TL review)** — authoritative **v1.0.0** dataset bundled:
  master 2025 = 3,355 (34 tỉnh + 3,321 ward), master legacy = 12,772 (65/726/11,981, trung thực
  API gồm cả provider duplicate), mapping 2025 = 3,355 APPROVED (100% coverage), mapping legacy =
  10,794 APPROVED (100% coverage, keyed `VN_ADMIN_PRE_2025_SNAPSHOT_2024`), manifest
  `dataset_version=1.0.0`, 4/4 checksum khớp. Integrity findings đã xử lý: (1) bundled master bị
  sửa 2 provider-duplicate ACTIVE→DISABLED (vi phạm §4 raw-master fidelity — đã khôi phục byte-identical
  với export; curated choice thuộc mapping layer `PROVIDER_DUPLICATE_CURATED` → `w:1953:910116` Hòa
  Bình + `w:1745:910044` Yên Sơn); (2) `MappingCsv::METHODS` chỉ có 3 giá trị placeholder — mở rộng
  thêm taxonomy authoring 13 giá trị (NORMALIZED_EXACT…HISTORICAL_MERGE_PRIMARY, giữ 3 giá trị cũ
  cho suggester workfile); (3) audit enumerate canonical qua DB provider `getChildren` = 0 rows
  (defect `parent_code=NULL` VietNamAddress-side, first real run) → toàn bộ mapping false-STALE →
  wire `CanonicalCsvProvider` (snapshot-aware, PRE_2025 = file SNAPSHOT_2024) vào
  MappingMatcher/MappingAuditor, gộp virtual `SuggesterMatcher` redundant. DB live: unit 16,127,
  mapping 14,149 APPROVED, integrity 0 dup/0 dangling/0 null; audit 2 scheme 100% mapped,
  production_ready, stale=0; resolver smoke 21/21 PASS (Kỳ Lừa→Tân Thanh; Đông Thành→Nhân Thành
  district 1846/province 235; Hòa Bình→910116; Yên Sơn→910044); bootstrap không API (0 config rows).
  Suite Secomm_Ghn 129 tests / 210,339 assertions / 0 fail / 0 error / **0 skip** (placeholder-gate
  skip đã gỡ, gate active). Evidence: `.ai/evidence/TASK-6TNKDH/phase2/`.
