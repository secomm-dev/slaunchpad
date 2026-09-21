# Task Spec: Secomm_Ghn — Initial GHN address dataset authoring (GHN-B.2)

Specification ID: SPEC-TASK-6TNKDH

> Filename: `SPEC-TASK-6TNKDH-secomm-ghn-initial-dataset-authoring.md` — chuẩn hóa từ TL/SA
> directive 2026-09-10 ("author the first real versioned GHN address datasets"). Follow-up của
> TASK-TBM30R (lifecycle) — KHÔNG redesign lifecycle trừ khi phát hiện defect thật.

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-6TNKDH |
| Feature ID | FEAT-FQWEQ3 (slice GHN-B.2 — KHÔNG phải GHN-C; GHN-C vẫn blocked external) |
| Specification Level | FULL |
| Author | Claude (AI-assisted normalization) — từ TL/SA directive |
| Status | **VALID** — lifecycle + policies được directive chỉ định; review spec text chạy cùng pre-review |
| Date | 2026-09-10 |
| Related Decision(s) | DEC-FEATFQWEQ3-002 (lifecycle — giữ nguyên) |
| Related Ticket(s) | TASK-TBM30R (nền lifecycle) · TASK-MZ2TCB (fetchers/schema) |
| Workflow Mode | A (production address dataset + shipping) |

## 1. Objective

Thay placeholder `v0.0.0-empty` của bundled dataset bằng dataset reviewed thật:

```text
GHN API → export provider datasets → combine với canonical Secomm_VietNamAddress datasets
→ offline assisted mapping → review unresolved/ambiguous → import → audit
→ commit reviewed master + mapping + manifest
```

Dataset phải reproducible, auditable, environment-independent, dùng được mọi Launchpad project.

## 2. Nguồn dữ liệu & identity

- Canonical SSOT = datasets versioned của `Secomm_VietNamAddress` (`Files/VN_ADMIN_2025_import.csv`,
  `Files/VN_ADMIN_PRE_2025_import.csv`) — chỉ ĐỌC, không sửa module (trừ blocking defect thật).
- Portable identity: `scheme_code + unit_code` ↔ `scheme_code + provider_key/id/code`. CẤM
  entity_id/region_id/city_id.
- GHN provider data: chỉ từ exporter GHN-B2 với credentials hợp lệ — **CẤM bịa provider rows**.
  Giữ nguyên verbatim mọi giá trị provider; WardCode giữ string.

## 3. Matching principles (authoring offline)

- Hierarchy-aware: Province → Ward (2025); Province → District → Ward (PRE_2025). Không match ward
  toàn cục không parent scope.
- Candidate evidence được phép: exact provider name, normalized exact, extension_names, curated
  alias, hierarchy, prefix hành chính. Fuzzy CHỈ để đề xuất review — không bao giờ là cơ chế approve.
- **Không over-normalize**: KHÔNG bỏ diacritics làm rule mặc định (cặp `Ia Bang`/`Ia Băng` phải
  khác nhau). Safe: NFC, trim, collapse-space, lowercase, prefix normalization, punctuation an toàn.
- 1:1 policy giữ nguyên fail-loud; nếu GHN thật chứng minh many-to-one bất khả kháng → STOP +
  material decision, không weaken importer.

## 4. Artifacts

```text
data/master/GHN_ADMIN_2025.csv          (GHN verbatim)
data/master/GHN_ADMIN_PRE_2025.csv
data/mapping/VN_ADMIN_2025_TO_GHN_ADMIN_2025.csv
data/mapping/VN_ADMIN_PRE_2025_TO_GHN_ADMIN_PRE_2025.csv
data/GHN_ADDRESS_MAPPING_REVIEW.csv     (mọi unresolved/ambiguous + context hierarchy 2 phía)
data/manifest.json                      (dataset_version, generated_at, counts, sha256, source env)
```

- Chỉ APPROVED được activate; không tự chuyển REVIEW_REQUIRED → APPROVED.
- Mỗi APPROVED non-trivial phải có note evidence giải thích vì sao đúng.

## 5. Verification

- Import sạch (không phụ thuộc auto-increment ids — same files khác entity ids vẫn đúng).
- Audit gate: unresolved=ambiguous=invalid=duplicate=dangling=0 → production_ready=true;
  unresolved thật → KHÔNG bịa — ghi nhận, dataset giữ non-production-ready tới khi review xong.
- Resolver 2 path: 2025 ward → verbatim names (`is_new_to_address=true`); PRE_2025 ward → đủ
  province/district/ward triple (district_id + ward_code). Không runtime guessing.
- Bootstrap verification (core acceptance): clean DB → setup:upgrade → bootstrap → KHÔNG gọi
  GHN API → master + approved mappings populated → audit production_ready → resolver chạy.
- Refresh-cycle: export mới → compare snapshot → unit mất → DISABLED (không delete); reviewed
  mapping không bị sync ghi đè.

## 6. Tests

Giữ tests hiện hữu; thêm dataset-integrity tests (file-to-file, không cần DB): manifest validation,
checksum, bootstrap từ non-empty dataset, counts khớp manifest, mapping chỉ portable identity,
mọi APPROVED canonical unit tồn tại trong canonical source, mọi provider key tồn tại trong bundled
master, không duplicate canonical/provider, resolver verbatim/triple. Ưu tiên integrity/audit tests —
không biến dataset thành hàng nghìn fixture giòn.

## 7. Governance & evidence

Task mint theo project idgen (GHN-B.2 intent); evidence tách bạch: provider facts / canonical data /
AI-assisted candidate findings / reviewed decisions. Không lưu credentials.

## 8. Blockers (ghi nhận tại thời điểm lập kế hoạch)

1. **GHN sandbox credentials** (Token/ShopId) — phải được cấu hình trong admin bởi owner (không
   paste vào chat/AI).
2. **MySQL** — local container đang tắt tại thời điểm bắt đầu; đã khởi động lại trong session.

## 9. Acceptance Criteria

AC-D1: placeholder replaced bởi dataset thật (2 master + 2 mapping + review artifact + manifest
version thật). AC-D2: import pass, không phụ thuộc entity ids. AC-D3: audit production_ready hoặc
unresolved được ghi nhận + dataset không production-ready (không bịa). AC-D4: resolver 2 path chạy
đúng verbatim/triple. AC-D5: clean bootstrap không gọi GHN API. AC-D6: không DB id trong artifacts.
AC-D7: không AI/fuzzy trong runtime. AC-D8: tests integrity + evidence + governance hoàn chỉnh.

## 10. Verification addendum — 2026-09-11 (phase 2 dev-complete, chờ TL review)

Dataset **v1.0.0** bundled và validated trên DB thật. AC-D1 ✓ (4 file + review artifacts +
manifest 1.0.0, 4/4 checksum khớp; mapping 3,355 + 10,794 APPROVED, coverage 100% cả 2 scheme).
AC-D2 ✓ (import portable identity; UPSERT re-run idempotent). AC-D3 ✓ (audit cả 2 scheme
100% mapped, production_ready, stale=0 — sau khi fix enumerate-defect, xem Progress Log task
record; không có unresolved nào bị bỏ). AC-D4 ✓ (resolver smoke 21/21: verbatim 2025 + legacy
triple + Kỳ Lừa/Đông Thành/2 curated case). AC-D5 ✓ (bootstrap không API — 0 config rows, import
chạy đủ khi chưa có credentials). AC-D6 ✓ (manifest/CSV chỉ scheme_code + unit_code/provider_key).
AC-D7 ✓ (writer duy nhất của mapping table = MappingImporter; matcher/suggester/alias read-only —
grep-audit trong evidence). AC-D8 ✓ (`BundledDatasetIntegrityTest` active, 0 skip; suite
Secomm_Ghn 129 tests / 210,339 assertions / 0F / 0E / 0S; evidence
`.ai/evidence/TASK-6TNKDH/phase2/`).

Defects phát hiện lần đầu chạy thật (đã fix trong scope): master bundled bị DISABLED hoá 2 provider
duplicate (khôi phục raw fidelity, curated choice thuộc mapping layer); `MappingCsv::METHODS` thiếu
taxonomy authoring; audit canonical enumeration = 0 do defect DB provider `parent_code=NULL`
(CanonicalCsvProvider snapshot-aware được wire vào audit path — GHN-scoped, không sửa
VietNamAddress; defect đó vẫn thuộc owning stream).
