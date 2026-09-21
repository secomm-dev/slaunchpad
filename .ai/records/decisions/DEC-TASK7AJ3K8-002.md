---
id: DEC-TASK7AJ3K8-002
title: 'GHTK address mode TEXT_NATIVE — canonical name_vi là default representation; secomm_ghtk_address_map hạ cấp thành exception/override table (canonical-keyed); bỏ textual-fallback guess'
status: accepted             # user acting as SA/TL — directive 2026-09-10 (task r1); setup:upgrade + E2E sandbox chờ evidence
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-10
created: 2026-09-10
last_verified: 2026-09-10
verified_against_commit:
supersedes: [DEC-TASK7AJ3K8-001]   # MỘT PHẦN — Decision 2 (alias lookup-first) + Decision 4 (textual fallback); phần còn lại giữ nguyên
superseded_by:
work_items: [TASK-7AJ3K8]
---

# Decision Record: GHTK address mode TEXT_NATIVE (canonical-first, override-only)

## Status

Accepted (2026-09-10 — user directive r1 trong task request; user acting as SA/TL).
Tier-2 review toàn bộ change set trước merge; `setup:upgrade` (schema re-key) + E2E sandbox
chạy khi DB/env available.

## Decision Type

Architecture

## Context

DEC-TASK7AJ3K8-001 chốt Stage-2 GHTK theo thứ tự "alias hit (exact) → canonical name_vi
(best-effort)" — bảng `secomm_ghtk_address_map` là runtime dependency chính. Directive r1
sửa premise: GHTK API fee/order nhận TEXT address fields và canonical Vietnamese names là
representation hợp lệ first-choice. Lookup-first một bảng map toàn bộ 3k+ ward chỉ để ghi lại
gần đúng cùng text = unnecessary duplication; đồng thời nhánh "UNMAPPED → best-effort text"
của r0 vi phạm rule mới "không gửi guessed address". Bảng dữ liệu + DB local không khả dụng
tại thời điểm quyết định → evidence sandbox chưa có, không thể chứng minh "không cần override".

# Decision 1 — Address mode `TEXT_NATIVE`, canonical-first

GHTK profile khai báo `addressMode = TEXT_NATIVE`: province/ward gửi bằng canonical `name_vi`
của scheme carrier yêu cầu (`VN_ADMIN_2025`), district nullable. Luồng mặc định:

```text
canonical identity resolved (EXACT/MAPPED)
  → ward name_vi (unit) + province name_vi (region unit) — VnAddressUnitProviderInterface
  → optional override by canonical identity (scheme_code, province_code, ward_code)
  → GhtkAddress(province, district?, ward)
```

Canonical name_vi là NGUỒN CHÍNH. Không mandatory carrier mapping lookup ở bất kỳ path nào
(rate + create order + pickup name-path dùng chung adapter).

# Decision 2 — Bảng `secomm_ghtk_address_map` = exception/override table

Giữ table name (tránh migration vô ích), đổi responsibility + key:

```text
scheme_code + province_code + ward_code  (canonical identity — UNIQUE)
ghtk_province / ghtk_district / ghtk_ward  (nullable overrides; ≥1 phải khác null)
is_active + note + timestamps
```

* Bảng KHÔNG phải source of truth VN administrative identity; KHÔNG chứa runtime ids
  (`country_id/region_id/ward_id` DROP) — khắc phục luôn nợ D6 (runtime-key unstable across
  scheme swaps) mà không cần follow-up riêng.
* Dataset mặc định RỖNG; chỉ import exception rows khi evidence sandbox cho thấy unit cụ thể
  cần text khác. Không populate full dataset chỉ để duplicate canonical names.
* Table không còn tham chiếu runtime directory rows → `DirectoryReferenceGuard` (D7 registration)
  XOÁ — guard chỉ cần cho runtime-keyed references.
* ⚠️ Declarative schema change → `setup:upgrade` bắt buộc trước deploy; bảng dev-stage
  (chưa có data production) — existing rows phải re-import bằng CSV format mới (replace-all là
  workflow thiết kế sẵn).

# Decision 3 — No-guess: bỏ textual fallback

AMBIGUOUS hoặc UNMAPPED → adapter trả null → rate hide / submit fail-fast. KHÔNG gửi address
đoán (nay là cả "province name_vi + ward text submit" của r0). `GhtkAddressCapability::
supportsTextualFallback()` đổi thành **false** — declaration phải khớp behavior mới. Đây là
behavior change so với r0 (địa chỉ unmapped trước đây vẫn nhận rate best-effort): fail-closed,
theo đúng directive "Không gửi một guessed address sang GHTK".

# Decision 4 — Một adapter duy nhất cho destination + pickup

`GhtkDestinationResolver` → rename **`GhtkAddressAdapter`** (model `Model/Address/`):
`Resolved canonical VN address → GHTK textual representation`. Destination (rate + create
order) và pickup name-path dùng CÙNG adapter; `pick_address_id` vẫn là carrier optimization
hợp lệ, ưu tiên cao nhất trong pickup flow (DEC-021 chain giữ nguyên).

# Decision 5 — Import lifecycle thu hẹp

CSV import giữ cho exception rows: header `scheme_code,province_code,ward_code,ghtk_province,
ghtk_district,ghtk_ward,is_active,note` (DB-column-aligned; §7 example dùng tên minh hoạ —
không tạo translation layer). Validator kiểm tra QUA VietNamAddress contracts (không raw SQL):
scheme thuộc catalog; canonical province + ward tồn tại trong reference layer; ward thuộc
province; ≥1 override non-empty; không duplicate canonical key. Validator/class naming =
`GhtkAddressOverrideImport`; repository = `GhtkAddressOverrideRepository::findActive(scheme,
province, ward)`.

# Decision 6 — Observability

Rate-hidden context phân biệt: `CANONICAL_ADDRESS_UNRESOLVED` (AMBIGUOUS/UNMAPPED) và
`GHTK_ADDRESS_INVALID` (resolved nhưng name_vi thiếu/rỗng). `GHTK_OVERRIDE_APPLIED` là
debug-level info. Log chỉ carries region_id/unit codes — không street/telephone.

## Consequences

* Rate path không còn query bảng mapping theo default; adapter không cần reverse bridge
  (canonical → names trực tiếp qua reference layer) — ít 1 query DB mỗi lookup, ổn định qua
  scheme swap.
* Địa chỉ UNMAPPED mất rate GHTK thay vì nhận best-effort rate — fail-closed chấp nhận
  (operator fix data hoặc thêm override row sau khi có sandbox evidence).
* Vendor unit names có thể khác GHTK-expected text cho một số unit → override row là cơ chế
  khắc phục (import khi có evidence, không đoán trước).
* Schema migration + re-import required khi deploy (dev-stage risk thấp).

## Supersession

Siêu DEC-TASK7AJ3K8-001 MỘT PHẦN: Decision 2 (alias lookup-first → override-only; re-key D6
hoãn → thực hiện trong task này) và Decision 4 (textual-fallback nhánh UNMAPPED → bỏ).
DEC-004 (target architecture), DEC-001 Decision 1 (HTTP primitive), 3 (name-bridge sibling),
5 (origin locality-slot), 6 (observability basis) GIỮ NGUYÊN.

## Verification

* Adapter test: canonical name_vi là default; override chỉ áp khi row active tồn tại;
  AMBIGUOUS/UNMAPPED → null; no fuzzy/first-match.
* Validator test: 6 checks §7 (qua unit provider, không SQL).
* Grep: không còn "mapping miss → no rate" semantics; capability `supportsTextualFallback()=false`.
* E2E sandbox (PENDING evidence): fee-only representative addresses khi dev sandbox/token
  available — xem evidence runbook.
