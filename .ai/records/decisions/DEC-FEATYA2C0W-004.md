---
id: DEC-FEATYA2C0W-004
title: 'Vietnam address/shipping dependency chain + module ownership (AddressDropdown → VietNamAddress → ShippingCore → carriers) + carrier API profile + operational↔canonical bridge + carrier reference guard extension'
status: accepted             # approved 2026-09-03 (user acting as SA/TL)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-03
created: 2026-09-03
last_verified: 2026-09-03
verified_against_commit:
supersedes: [DEC-TASKNDASAD-001, DEC-TASK3F6QWZ-001]   # cả hai MỘT PHẦN — xem §Supersession
superseded_by:
work_items: [FEAT-YA2C0W, TASK-9394A9, TASK-J9AVGK, TASK-3F6QWZ]
---

# Decision Record: Vietnam Address / Shipping Dependency & Ownership

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-03 (user acting as SA/TL).
     Nguồn: architecture audit 2026-09-03 — 5 sweep read-only, evidence file:line trên 7 module + .ai records. -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Clarify/supersede MỘT PHẦN: DEC-TASKNDASAD-001 (stance "core trả regionId + names thô; carrier tự
     normalize VN" → ShippingCore consume VietNamAddress contracts); DEC-TASK3F6QWZ-001 (GhnAddressMapper
     dedicated module → target merge về Secomm_GiaoHangNhanh).
     GIỮ NGUYÊN: swap model DEC-FEATYA2C0W-002, scheme identities + reference layer + resolver semantics
     DEC-FEATYA2C0W-003, "ShippingCore does not know carrier internals". -->

## Status

Accepted (2026-09-03 — user acting as SA/TL)

## Decision Type

Architecture

## Context

Secomm Launchpad đang chuẩn hóa architecture cho address và các shipping carrier tại Việt Nam.

Current implementation đã hình thành ba layer chính:

```text
Secomm_AddressDropdown
Secomm_VietNamAddress
Secomm_ShippingCore
```

và các carrier:

```text
Secomm_GiaoHangNhanh
Secomm_Ghtk
Secomm_Ahamove
```

Audit source 2026-09-03 cho thấy:

* `AddressDropdown` đã có recursive hierarchy dựa trên `directory_region_city.parent_city_id`.
* `VietNamAddress` đã có:

  * versioned administrative schemes;
  * historical/reference data layer (`secomm_vietnam_address_unit` — đa scheme, không FK runtime);
  * cross-scheme mapping (directed edges, SAME_AS|RENAMED_TO|MERGED_INTO|SPLIT_INTO);
  * scheme resolver với EXACT / MAPPED / AMBIGUOUS / UNMAPPED.
* `ShippingCore` hiện mới ownership:

  * shipping context;
  * origin provider;
  * common tracking pipeline.
* Các carrier vẫn tự implement nhiều phần address resolution khác nhau (GHTK: name→id bridge + vi_VN
  resolver raw-SQL riêng; GHN: mapping module riêng đọc raw directory tables; Ahamove: raw text, không
  mapping table).
* Không carrier nào hiện consume `VietNamAddress` scheme-resolution contracts; không module nào depend
  `Secomm_VietNamAddress`.
* Một số carrier reconstruct canonical identity từ address name (name-first-match, không AMBIGUOUS).
* GHN mapping hiện nằm trong `Secomm_GhnAddressMapper`, tạo circular dependency chưa khai báo với
  `Secomm_GiaoHangNhanh` (mapper sequence carrier, carrier type-hint mapper API).
* `VietNamAddress` hiện hard-code tên 2 bảng carrier trong scheme-swap guard (`VnAddressSchemeImporter`).
* Existing architecture records chưa chính thức ratify dependency: `ShippingCore → VietNamAddress`
  (DEC-TASKNDASAD-001 hiện nói ngược lại).

Architecture cần được chốt trước khi implement Phase E carrier framework.

---

# Decision 1 — Canonical dependency direction

Dependency chính thức:

```text
Secomm_AddressDropdown
        ↓
Secomm_VietNamAddress
        ↓
Secomm_ShippingCore
        ↓
Carrier modules
```

Carrier examples:

```text
Secomm_GiaoHangNhanh
Secomm_Ghtk
Secomm_Ahamove
future SPX / J&T / Ninja Van / ...
```

Không cho phép dependency ngược; không cho phép circular dependency.

---

# Decision 2 — Module ownership

## Secomm_AddressDropdown

Owns:

```text
generic address hierarchy engine
profile/depth definitions
generic hierarchy CRUD
generic GraphQL/address-schema contracts
generic rendering infrastructure
```

It MUST NOT own:

```text
Vietnam administrative semantics
VN_ADMIN_* scheme knowledge
carrier address mapping
carrier API knowledge
Vietnam-specific labels or behavior
```

Vietnam-specific frontend behavior hiện còn trong generic module (Luma checkout cascade JS hard-code
`'VN'`, label 'Ward/Commune', locale 'vi') phải được migrate dần sang country adapter hoặc chuyển thành
profile-driven behavior.

## Secomm_VietNamAddress

Owns Vietnam administrative domain:

```text
VN_ADMIN_2025
VN_ADMIN_PRE_2025

reference administrative datasets
scheme registry
canonical VN unit identity
scheme relationships
scheme translation
administrative normalization
operational ↔ canonical identity bridge
administrative ambiguity representation
```

Rule:

```text
Vietnam → Vietnam
```

belongs here.

Examples:

```text
runtime region_id/city_id
    ↔
scheme_code/unit_code

VN_ADMIN_2025 unit
    ↔
VN_ADMIN_PRE_2025 unit(s)
```

Carrier-specific identifiers MUST NOT live here.

## Secomm_ShippingCore

Owns the common process every Secomm Vietnam carrier must follow.

ShippingCore is the Vietnam carrier integration framework, not the owner of Vietnamese administrative
master data.

It owns:

```text
ShippingContext
Origin/Destination orchestration
Carrier API/profile contracts
Required address-scheme orchestration
Normalized shipping address
Carrier mapper contracts
Common failure policy contracts
Common tracking pipeline
Shared shipping integration contracts
```

Rule:

```text
ShippingCore owns PROCESS / CONTRACT / ORCHESTRATION.
```

ShippingCore MAY consume:

```text
Secomm_VietNamAddress APIs
```

but MUST NOT directly query or maintain Vietnamese administrative datasets.

ShippingCore MUST NOT contain:

```text
GHN IDs
GHTK-specific names
Ahamove-specific codes
carrier endpoints
carrier-specific API payload formats
```

(Theo Decision 10, phần semantic VN 2-level hiện nằm trong `ShippingOriginProvider`
(`ward = config city`, `district: null`) được xem là nợ migrate — chuyển sang VietNamAddress
normalizer khi Decision 5 vào code.)

## Carrier modules

Carrier modules own only carrier-specific behavior.

Rule:

```text
Vietnam → Carrier
```

belongs to carrier.

Examples:

### GHN

```text
GHN API profiles
GHN API adapters
GHN ProvinceID/DistrictID/WardCode mappings
GHN request/response mapping
GHN status mapping
GHN-specific validation
```

### GHTK

```text
GHTK API profile
canonical VN → GHTK accepted text mapping
GHTK request/response mapping
pickup-address-id policy
GHTK-specific validation
```

### Ahamove

```text
Ahamove API profiles
Ahamove-specific location/service identities
Ahamove request/response mapping
Ahamove-specific fallback/geocoding behavior where required
```

(Ahamove geocoding chỉ phục vụ payload Ahamove — geocoding cho administrative disambiguation thuộc
common strategy, Decision 9.)

---

# Decision 3 — Carrier API Profile

Each carrier may support multiple API generations.

Address version MUST NOT be treated as an isolated config flag.

A selected API profile represents a complete capability bundle:

```text
Carrier API Profile
├── required VN address scheme
├── API adapter/endpoints
├── address representation
├── carrier address mapper
├── request mapper
├── response mapper
└── validation/fallback policy
```

Examples:

```text
GHN_PRE_2025
required_scheme = VN_ADMIN_PRE_2025
representation = CARRIER_ID

GHN_2025
required_scheme = VN_ADMIN_2025
representation = CARRIER_ID

GHTK_2025
required_scheme = VN_ADMIN_2025
representation = TEXT
```

Admin may select the profile/version supported by that carrier.

Changing profile must switch all related behavior atomically.

It must never produce a state where:

```text
address_scheme = 2025
API adapter = legacy
```

or equivalent mismatched configuration.

---

# Decision 4 — Common address pipeline

All Vietnam carrier integrations MUST follow:

```text
Magento operational address
        ↓
resolve canonical VN administrative identity
        ↓
resolve selected carrier API profile
        ↓
determine required VN scheme
        ↓
translate scheme if necessary
        ↓
AMBIGUOUS?
   ├── no
   └── yes → disambiguation strategy
        ↓
canonical address in carrier-required scheme
        ↓
carrier-specific address mapper
        ↓
carrier-specific API adapter
```

Carrier modules MUST NOT independently:

```text
translate VN_ADMIN_2025 ↔ VN_ADMIN_PRE_2025
infer canonical city/unit identity from names
query directory_region_city to reconstruct VN hierarchy
implement their own Vietnam scheme-resolution algorithm
```

unless explicitly implemented behind a VietNamAddress-owned contract.

---

# Decision 5 — Operational ↔ canonical identity bridge

`VietNamAddress` must provide the missing bridge between:

```text
Magento runtime identity
(region_id / city_id / hierarchy path)
```

and:

```text
Vietnam canonical identity
(scheme_code / unit_code)
```

The cross-scheme resolver continues to operate only on canonical identities.

Do NOT modify the existing resolver to accept arbitrary names.

Required conceptual flow:

```text
region_id/city_id
      ↓
OperationalIdentityResolver
      ↓
scheme_code/unit_code
      ↓
VnAdminAddressResolver
```

Reverse resolution is also required where needed:

```text
scheme_code/unit_code
      ↓
runtime location identity
```

No text-based identity should be considered canonical when stable codes exist.

Bridge entry points and clarifications:

* **Id-based entry** là chính: `(region_id, city_id)` → `(scheme_code, unit_code)`; reverse
  `(scheme_code, unit_code)` → runtime `city_id` (khi unit đó đang nằm trong runtime).
* **Name-based entry tạm thời vẫn cần thiết**: cho đến khi §23 snapshot (`vn_scheme_code`/`vn_unit_code`
  trên quote/sales/customer address) được triển khai, runtime chỉ có `(region_id, ward-name)` (ward là
  TÊN trong native `city`). Bridge nhận name-based entry region-scoped và trả **AMBIGUOUS khi nhiều
  match — KHÔNG first-match** (thay thế chính sách first-match + warning của `WardIdBridge`/`getCityIdByName`
  hiện tại). Name chỉ là input chuyển tiếp; canonical output luôn `(scheme_code, unit_code)`.
* Công cụ import/validate của carrier mapping tables tra cứu canonical identity QUA bridge này, không
  raw-SQL directory tables (khử pattern hiện có trong GhnAddressMapper).

---

# Decision 6 — Scheme swap safety

Runtime address tables may contain only the active VN scheme.

Reference tables may contain multiple schemes simultaneously.

Carrier mapping must therefore not rely exclusively on runtime primary keys that are unstable across
scheme swaps.

Existing carrier mappings keyed by:

```text
region_id
city_id
```

must eventually migrate to scheme-aware canonical keys, for example:

```text
scheme_code
unit_code
```

or an equivalent stable carrier mapping identity.

No production scheme swap is considered safe until persisted/saved addresses and carrier mapping
identities are scheme-aware.

---

# Decision 7 — Carrier reference guard

`VietNamAddress` MUST NOT hard-code carrier table names.

Current scheme-swap protection must become an extension contract.

Conceptual API:

```text
DirectoryReferenceGuardInterface
```

Each carrier that stores runtime directory references registers its own guard via DI.

`VietNamAddress` orchestrates all registered guards before destructive scheme operations.

Benefits:

```text
no reverse carrier knowledge
new carrier automatically participates
country module remains carrier-independent
```

---

# Decision 8 — GHN mapping ownership

`secomm_ghn_address_mapping_location` is carrier-specific data.

Preferred target:

```text
Secomm_GiaoHangNhanh
```

owns GHN mapping.

`Secomm_GhnAddressMapper` should either:

1. be merged into `Secomm_GiaoHangNhanh`, preferred for Launchpad simplicity; or
2. become an explicit lower-level dependency of `Secomm_GiaoHangNhanh` without depending back on
   `Secomm_GiaoHangNhanh`.

Circular module dependency is not allowed.

Điều kiện của option 2: `Secomm_GhnAddressMapper` phải ngừng đọc raw các bảng
`secomm_giaohangnhanh_{province,district,ward}` (hiện 8+ vị trí raw SQL) — ngược lại implicit coupling
chỉ đổi tên chứ không mất. Option 1 (merge) không có điều kiện này vì bảng GHN master-data và mapping
cùng một owner.

---

# Decision 9 — Ambiguity

Existing VietNamAddress behavior remains authoritative:

```text
EXACT
MAPPED
AMBIGUOUS
UNMAPPED
```

AMBIGUOUS MUST NEVER automatically select the first candidate.

Disambiguation is an extension of the common process.

Conceptual interface:

```text
AddressDisambiguationStrategyInterface
```

Initial implementation may simply preserve `AMBIGUOUS`.

External services such as VietMap may later implement this strategy.

VietNamAddress MUST NOT hard-depend on VietMap.

---

# Decision 10 — No unnecessary abstraction

Not part of this decision:

```text
global ShippingCore
microservice address engine
common HTTP client rewrite
carrier-independent universal mapping table
new canonical VN unit table
```

Existing:

```text
secomm_vietnam_address_unit
```

is already the canonical multi-scheme reference layer and must be reused.

HTTP stacks may remain carrier-specific until duplication creates a demonstrated maintenance problem.

---

# Consequences

Positive:

* one Vietnam administrative source of truth;
* carrier modules share one deterministic address flow;
* API version changes become config-driven;
* adding future carriers does not require modifying VietNamAddress;
* scheme changes do not require duplicating migration logic across carriers;
* ambiguity becomes explicit rather than silently guessed.

Migration work required:

* operational ↔ canonical identity bridge;
* carrier capability/profile contracts;
* ShippingCore destination orchestration;
* GHN mapper ownership cleanup (Decision 8);
* GHTK removal of private VN address resolution (`WardIdBridge`, `BestEffortViVnResolver` → VietNamAddress bridge);
* scheme-aware carrier mapping migration (Decision 6);
* persisted-address scheme snapshot before production scheme swaps (§23, DEC-FEATYA2C0W-003);
* GHN dev-mode hardening: default `is_develop_mode = 0`, loại bỏ fallback district/ward cứng
  (`1456`/`21511`/`'Phường 17'`) khỏi rate/order paths (audit P0-3 — silent wrong destination).

---

# Supersession / clarification

This decision clarifies/supersedes any previous assumption that:

```text
ShippingCore must not depend on VietNamAddress
```

or that:

```text
each carrier independently normalizes Vietnam addresses.
```

Chi tiết:

* **DEC-TASKNDASAD-001 (một phần)**: origin contract (`ShippingContext`/`OriginInterface`/
  `OriginProviderInterface`) GIỮ NGUYÊN; phần stance "core trả regionId + names thô; carrier tự
  normalize VN names" được thay bằng ShippingCore consume VietNamAddress contracts (Decision 1).
  `OriginInterface` sẽ cần bổ sung ward/unit identity (id-based) theo Decision 5.
* **DEC-TASK3F6QWZ-001 (một phần)**: "decouple GHN address mapping into dedicated
  Secomm_GhnAddressMapper module" → target ownership là `Secomm_GiaoHangNhanh` (Decision 8, option 1
  preferred).
* **DEC-TASKYJENM2-001 (lộ trình, chưa supersede ngay)**: canonical mapping key
  `(country_id, region_id, ward_id)` + best-effort fallback là trạng thái CHUYỂN TIẾP; migrate lên
  scheme-aware keys theo Decision 6, và "best-effort first-match" bị thay bằng policy AMBIGUOUS
  (Decision 5/9).
* Giữ nguyên: swap model + scheme identities + reference layer + resolver semantics
  (DEC-FEATYA2C0W-002/003); resolver KHÔNG được sửa để nhận tên tự do (Decision 5).
* Retained principle:

```text
ShippingCore does not know carrier internals.
```

The new explicit architecture is:

```text
ShippingCore consumes VietNamAddress contracts
and carriers consume ShippingCore contracts.
```

---

# Verification

* `grep -rEi 'ghn|ghtk|ahamove|secomm_ghn|secomm_ghtk' app/code/Secomm/VietNamAddress --include='*.php'`
  → chỉ còn contract/guard interface; 0 tên bảng carrier hardcode.
* Module graph acyclic; sau Decision 8 không còn type-hint `Secomm\GhnAddressMapper` trong
  `Secomm_GiaoHangNhanh` (hoặc edge đã khai báo đúng 1 chiều).
* Mỗi carrier có bảng tham chiếu runtime directory đăng ký ≥ 1 guard qua DI; `--dry-run` của scheme
  import liệt kê toàn bộ guards trước khi purge.
* 0 raw-SQL runtime vào `directory_region_city` trong carrier modules ngoài contract VietNamAddress.
* Profile switch = 1 config point → adapter + mapper + required scheme đổi đồng bộ (covered by test).
