---
id: DEC-TASK7AJ3K8-001
title: 'GHTK alignment amendments — shared HTTP primitive (siết D10), alias table giữ runtime key (hoãn D6 re-key), name-bridge sibling contract, GHTK textual-fallback semantics, origin locality-slot'
status: accepted             # user acting as SA/TL — directive 2026-09-10 (task request); Tier-2 review trước merge
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-10
created: 2026-09-10
last_verified: 2026-09-10
verified_against_commit:
supersedes: [DEC-FEATYA2C0W-004]   # MỘT PHẦN — xem §Supersession
superseded_by:
work_items: [TASK-7AJ3K8]
---

# Decision Record: GHTK alignment amendments

## Status

Accepted (2026-09-10 — user directive trong task request; user acting as SA/TL).
Tier-2 (CTO/SA) review toàn bộ change set trước merge.

## Decision Type

Architecture

## Context

TASK-7AJ3K8 thực thi DEC-FEATYA2C0W-004 trên `Secomm_Ghtk` (consumer runtime đầu tiên của pipeline
E-A/E-B/E-C0). Audit code 2026-09-10 (SPEC-TASK-7AJ3K8 §2) làm bật 5 điểm mà DEC-004 chưa chốt
cách làm cụ thể, cần quyết định trước khi viết code.

# Decision 1 — Shared HTTP primitive VÀO NGAY (siết D10 một phần)

DEC-004 D10: "HTTP stacks may remain carrier-specific until duplication creates a demonstrated
maintenance problem." Chốt ngược lại cho transport primitive: build ngay trong task này, vì
(1) directive user yêu cầu rõ, (2) ≥2 carrier đang sắp dùng chung shape (GHTK + GiaoHangNhanh),
(3) safety rule "create-order không auto-retry" cần một nơi định nghĩa retry policy thống nhất.

Scope primitive (lean, trong `Secomm_ShippingCore`):
`CarrierHttpClientInterface` (send/sendJson) + `CarrierHttpRequest/Response` DTO +
`CarrierHttpException` + 6 error categories (`TIMEOUT|NETWORK|RATE_LIMIT|SERVER_ERROR|CLIENT_ERROR|
INVALID_RESPONSE`) + `RetryPolicy`/`RetryExecutor`. Auth headers, endpoint, payload, business
retry semantics VẪN nằm ở carrier. Giữ nguyên D10 cho mọi abstraction KHÁC (không universal
mapping table, không microservice, không registry).

Retry semantics chuẩn hóa: safe read (fee GET, status GET) được retry trên
NETWORK/SERVER_ERROR/TIMEOUT theo config carrier; create-shipment KHÔNG auto-retry khi carrier
chưa chứng minh idempotency (GHTK: single attempt — giữ behavior hiện tại; RATE_LIMIT + 4xx
không retry).

# Decision 2 — `secomm_ghtk_address_map` giữ runtime key; responsibility chính thức thu hẹp

Bảng KHÔNG phải canonical mapping (không duplicate `secomm_vietnam_address_mapping` — bảng đó
giữ cross-scheme relations, bảng GHTK giữ GHTK-accepted TEXT names). Chốt:

1. Responsibility chính thức: **GHTK name alias map** — KHÔNG là source of truth cho VN
   administrative identity (identity chỉ đến từ bridge + resolver VietNamAddress).
2. Key `(country_id, region_id, ward_id)` giữ nguyên trong task này. Lookup CHỈ qua reverse
   bridge (`resolveFromCanonical` → runtime ids) — không bao giờ dùng bảng này để suy ra identity.
3. Re-key scheme-aware (DEC-004 D6) HOÃN lại follow-up DB migration riêng (Tier-2), vì là schema
   change + data migration, không thuộc refactor boundary này. Guard registration (D7) giữ nguyên.

# Decision 3 — Name-based bridge = sibling contract, không sửa interface đã freeze

`VnOperationalAddressResolverInterface` (TASK-Q4B98P) giữ nguyên. Name-based entry (region-scoped
ward name → canonical identity) vào interface mới `VnOperationalNameResolverInterface` +
`VnOperationalNameResolutionInterface`, implement trên cùng resolver family. Statuses REUSE
`VnAddressResolutionInterface::STATUS_*` (không parallel constants). Match theo `name_vi` HOẶC
`name_en` (exact sau trim) trên reference layer — sửa luôn lỗi latent F4 (match `default_name`
= name_en trong khi storefront submit tên vi). AMBIGUOUS trả candidates, không pick (D9).

# Decision 4 — GHTK textual-fallback semantics ở Stage-2

Pipeline canonical trả 4 trạng thái; GHTK (TEXT representation, DEC-004 D3) map như sau:

- EXACT/MAPPED → alias hit (exact) hoặc canonical `name_vi` (best-effort, exact=false);
- AMBIGUOUS → **không gửi request gì** (hide/abort) — thay cho first-match cũ; đây là intentional
  behavior change được directive bắt buộc ("không được âm thầm chọn first match khi ambiguous");
- UNMAPPED + `supportsTextualFallback()=true` → best-effort payload: province = `name_vi` của
  region unit (bridge region-only), ward = text customer submit; exact=false + log warning
  (giữ đúng graceful degradation cũ, nguồn tên giờ là VietNamAddress thay vì raw directory tables).

# Decision 5 — Origin: locality-slot framing; hoãn VN canonical origin enrichment

`ShippingOriginProvider` không đọc gì ngoài generic Magento origin config (đã đúng). Phần semantic
"ward = config city" được định nghĩa lại thành platform convention: `OriginInterface::getWard()`
là **locality slot** dưới province mà AddressDropdown profile engine lưu ở native `city` field
cho mọi profile (không riêng VN). Ghi chú migrate "chuyển sang VietNamAddress normalizer" của
DEC-004 D2 được thay bằng: canonical origin enrichment (unit_code metadata cho origin) chỉ làm
khi xuất hiện consumer thực (MSI source origin / §23 snapshot) — hiện không có → không build
decorator (D10). Không tạo dependency ngược ShippingCore → carrier/VN enrichment.

## Consequences

* Carrier đầu tiên (GHTK) không còn raw-SQL directory, không first-match; GHN/Ahamove/SPX/Grab có
  sẵn HTTP + reconciliation + handoff shape để tái dùng.
* Địa chỉ ambiguous (trùng tên ward trong region) mất rate GHTK thay vì nhận rate sai — chấp nhận
  theo directive; operational fail-closed.
* Behavior GHTK thay đổi duy nhất ở nhánh ambiguous + nguồn tên fallback (canonical `name_vi` thay
  cho directory locale table); mọi nhánh khác giữ semantics cũ.
* Nợ còn lại được ghi rõ: alias re-key D6 (follow-up DB), external disambiguation, canonical origin.

## Supersession

Siêu DEC-FEATYA2C0W-004 MỘT PHẦN: D10 (HTTP stacks → primitive chung ngay, scope §Decision 1) và
ghi chú D2 về origin normalizer (→ Decision 5 hoãn-until-consumer). Toàn bộ phần còn lại của
DEC-004 (dependency direction, ownership, D3 profile, D5 bridge direction, D6 roadmap, D7 guard,
D9 ambiguity) GIỮ NGUYÊN và được thực thi chặt hơn.

## Verification

* Grep gates AC-1 (0 directory-table/locale knowledge trong Ghtk).
* Test AC-3/AC-4/AC-6/AC-7/AC-10 chứng minh 4 decisions trên ở mức behavior.
* `bin/project-ai-validate --check-records --check-specs` sạch.
