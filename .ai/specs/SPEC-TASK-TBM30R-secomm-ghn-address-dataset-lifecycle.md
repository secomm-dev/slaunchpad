# Task Spec: Secomm_Ghn — Address dataset lifecycle (export → offline review → import/bootstrap)

Specification ID: SPEC-TASK-TBM30R

> Filename: `SPEC-TASK-TBM30R-secomm-ghn-address-dataset-lifecycle.md` — canonical behavioral
> contract của slice GHN-B2 (chuẩn hóa từ directive TL/SA 2026-09-10; supersedes một phần
> SPEC-FEAT-FQWEQ3 §7 theo DEC-FEATFQWEQ3-002).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-TBM30R |
| Feature ID | FEAT-FQWEQ3 (parent; slice GHN-B2) |
| Specification Level | FULL |
| Author | Claude (AI-assisted normalization) — từ TL/SA directive 2026-09-10 |
| Status | **VALID** — lifecycle được TL/SA chỉ định trực tiếp ("decisions already approved"); review spec text chạy cùng code pre-review |
| Date | 2026-09-10 |
| Related Decision(s) | DEC-FEATFQWEQ3-002 (lifecycle) · DEC-FEATFQWEQ3-001 (dual-scheme create) · DEC-FEATYA2C0W-004 (portable identity) |
| Related Ticket(s) | TASK-TBM30R · tiền nhiệm TASK-MZ2TCB (GHN-B — giữ nguyên fetch/persist/audit/resolver/schema) |
| Workflow Mode | A (shipping data lifecycle + import/export tooling) |

## 1. Objective

Chuyển mapping lifecycle của `Secomm_Ghn` từ auto-mapping (exact-name + curated alias → APPROVED)
sang **versioned export → offline reviewed mapping → import/bootstrap**. Runtime giữ deterministic
fail-closed. Scope CHỈ lifecycle dữ liệu address/mapping của `Secomm_Ghn` — KHÔNG rate, KHÔNG
ShippingCore, KHÔNG VietMap, KHÔNG checkout.

## 2. Lifecycle chuẩn

```text
GHN API
   ↓ sync/provider master data (giữ nguyên GHN-B fetchers)
export deterministic CSV (CLI; không chạy trong HTTP controller)
   ↓
OFFLINE mapping AI/manual  +  canonical source CSVs của Secomm_VietNamAddress
   ↓
reviewed mapping CSV
   ↓ import (validate fail-loud, chỉ activate APPROVED, UPSERT)
audit (mapped/unmapped/ambiguous/invalid/stale/disabled/duplicate/dangling/coverage%)
   ↓
runtime resolver (không đổi nguyên tắc)
```

Bootstrap first-install: module SHIP dataset reviewed/versioned trong source
(`Secomm/Ghn/data/master/*.csv`, `data/mapping/*.csv`, `data/manifest.json`) — cài mới KHÔNG cần
GHN API. Bootstrap deterministic, repeatable local/dev/staging/production/đa project.

## 3. Portable identity

```text
Secomm side: scheme_code + unit_code
GHN side:    scheme_code + provider_key / provider_id / provider_code (string)
```

CẤM trong mọi file dataset: `secomm_vietnam_address_unit.entity_id`,
`secomm_ghn_address_unit.entity_id`, Magento `region_id`, `city_id`. Local DB IDs chỉ resolve
bên trong importer/runtime persistence. Legacy GHN `WardCode` giữ nguyên giá trị dạng string.

## 4. Export CSV schema (master data)

Header cố định, UTF-8, deterministic ordering (scheme_code, level, provider_key ASC — strcmp
byte-wise), không entity_id, hierarchy reconstructable từ file, tên GHN verbatim, diacritics
nguyên vẹn, `extension_names` = JSON array (UNICODE escaped off):

```text
scheme_code,level,provider_key,provider_id,provider_code,parent_provider_key,name,extension_names,status
```

Exporter: GHN APIs → fetch full dataset (reuse GHN-B fetchers) → validate → normalize chỉ cấu trúc
→ CSV. CLI là đường ưu tiên; không sync dài trong HTTP controller. Cả 2 scheme.

## 5. Mapping CSV (import format)

```text
secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note
```

`mapping_status` trong file: `APPROVED | REVIEW_REQUIRED | UNRESOLVED | AMBIGUOUS`.
Production import CHỈ activate `APPROVED` (REVIEW_REQUIRED/UNRESOLVED/AMBIGUOUS chỉ sống offline,
import đếm + skip). `mapping_method` ∈ `EXACT_NAME | CURATED_ALIAS | MANUAL`.

## 6. Import behavior

Validate: file structure/header; scheme pair (chỉ 2 cặp được duyệt); canonical unit_code tồn tại
(`VnAddressUnitProviderInterface`); GHN provider identity tồn tại (`secomm_ghn_address_unit` theo
scheme+provider_key); duplicate (cùng canonical key xuất hiện 2 lần trong file); conflict (2+
canonical unit APPROVED trỏ cùng 1 GHN unit trong 1 scheme); dangling (APPROVED trỏ identity
không tồn tại). Invalid APPROVED row → reject TOÀN bộ import (fail loud, không partial).
UPSERT-based refresh; KHÔNG truncate (mapping table hoặc address table); provider unit mất khỏi
snapshot mới → DISABLED (không DELETE). Import master và import mapping là service riêng
(sync/refresh master data KHÔNG ghi đè quyết định mapping đã review).

## 7. Manifest

`data/manifest.json` (nhẹ): `dataset_version`, `generated_at`, `source_environment`,
per-file: `scheme_code`, `record_count`, `sha256`. Exporter sinh manifest cho dataset nó xuất;
importer validate checksum/count khi manifest hiện diện (thiếu manifest → cho phép, ghi warning).

## 8. Audit (adapted)

Báo ít nhất: mapped, unmapped, ambiguous, invalid, stale, disabled_provider_unit, duplicate,
dangling, coverage % (tổng + per level). Cờ `production_ready` = (unmapped=0 ∧ ambiguous=0 ∧
invalid=0 ∧ duplicate=0 ∧ dangling=0); không đạt → dataset không được coi là production-ready
(audit in rõ; import đã fail-closed từ trước).

## 9. Runtime resolver (không đổi)

Stable Secomm code → APPROVED mapping → stable GHN identity → address unit. Không fuzzy, không
extension_names guess, không AI, không closest-match. PRE_2025 → đủ legacy triple
(province/district/ward: district_id + ward_code); 2025 → verbatim names. Fail closed khi thiếu
mapping. `extension_names` chỉ dùng offline (candidate/diagnostics), KHÔNG có runtime tier nào
tự APPROVE.

## 10. CLI + Admin

CLI: `secomm:ghn:address:sync` (giữ nguyên) + `secomm:ghn:address:export` + `secomm:ghn:address:import`
(mặc định import bundled dataset = bootstrap; `--dir` cho dataset ngoài) + `secomm:ghn:address:audit`
+ `secomm:ghn:address:suggest` (workfile candidate-generation từ matcher — không bao giờ APPROVE).
CLI và Admin (khi có) gọi cùng application services. Admin lean: hiển thị trạng thái dataset
(installed version/counts) trong system config; import qua Admin deferred (CLI primary — được
directive §13 cho phép).

## 11. Legacy matcher classification (directive §15)

`MappingMatcher` + `NameNormalizer` + `AliasRepository`: **A — useful candidate-generation
tooling** → giữ nguyên (tests giữ nguyên), chỉ phục vụ suggester/audit; KHÔNG B (không chỉ-test),
KHÔNG C (không xóa). `MappingGenerator` (auto-write APPROVED): **C — redundant** → xóa an toàn,
thay bằng `MappingSuggester` (sinh workfile CSV: exact/alias → `REVIEW_REQUIRED` với note nguồn;
ambiguous → `AMBIGUOUS` + candidates trong note; unmapped → `UNRESOLVED`). Không matcher nào ghi
APPROVED runtime mapping.

## 12. Acceptance Criteria

- AC-L1: Export deterministic (2 lần chạy ra bytes giống nhau), schema/order/UTF-8/diacritics đúng,
  không entity_id, hierarchy reconstructable, tên verbatim; manifest sinh kèm (counts + sha256).
- AC-L2: Bootstrap bundled dataset import được không cần GHN API; deterministic/repeatable;
  manifest checksum verify khi hiện diện.
- AC-L3: Mapping import chỉ activate APPROVED; REVIEW_REQUIRED/UNRESOLVED/AMBIGUOUS được skip +
  đếm; duplicate/unknown canonical/unknown provider/conflict/dangling/malformed → fail loud,
  không partial write.
- AC-L4: Cùng bộ file import vào 2 DB có entity_id khác nhau → kết quả mapping tương đương
  (portable identity).
- AC-L5: Refresh master (import snapshot mới) → unit mất → DISABLED, không DELETE; mapping đã
  APPROVED không bị sync ghi đè.
- AC-L6: Runtime resolver chỉ dùng APPROVED import; PRE_2025 đủ triple; 2025 đủ names; fail closed.
- AC-L7: `extension_names` không tạo APPROVED tự động ở bất kỳ đường nào.
- AC-L8: Audit đủ 9 trạng thái + coverage % + production_ready; detected dangling/duplicate.
- AC-L9: Không còn đường code ghi APPROVED ngoài MappingImporter; MappingGenerator đã xóa;
  classification A/C được ghi vào spec/record.
- AC-L10: Tests: giữ nguyên GHN-B tests (trừ behavior đổi có chủ đích) + mới đủ ma trận directive
  §16 (export determinism, bootstrap, import stable-identity, cross-entity-id, duplicate, unknown
  codes, non-APPROVED skip, DISABLED-not-delete, resolver import-only, extension_names, round-trip).
- AC-L11: Validators không tăng FAIL mới; README/CHANGELOG/i18n cập nhật; evidence đầy đủ.
- AC-L12 (QC gate khi MySQL up): setup:upgrade → bootstrap → audit → export sandbox (cả 2 scheme
  khi có credentials) → compare counts/schema → import reviewed test mapping → audit → resolver
  test trên DB thật; schema/whitelist vẫn khớp.

## 13. Out of scope

Rate-provider, ShippingCore redesign, VietMap, NO_MATCH authoring, checkout, shipment orchestration,
carrier khác, admin import UI, versioning platform đầy đủ.
