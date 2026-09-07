# Implementation Plan: Generic hierarchical address profiles — FEAT-2PZQKJ

> Mode A/B — Plan only, chưa viết code (Hard Gate: No Code Without Plan; No Code Without Valid Spec).
> Gate đầu tiên **ĐÃ MỞ 2026-08-25**: DEC-FEAT2PZQKJ-001 accepted + Spec Approved (D2 location_path / D4 XML / D5 subtree / D6 code). Task tiếp theo kích hoạt được: TASK-9AEAQQ (Step 2).

## Metadata

| Field | Value |
|-------|-------|
| Ticket / Spec | FEAT-2PZQKJ (sub-tickets TASK-9AEAQQ…TASK-ZHFVRH) / SPEC-FEAT-2PZQKJ |
| Specification | [SPEC-FEAT-2PZQKJ-generic-hierarchical-address-profiles](../specs/SPEC-FEAT-2PZQKJ-generic-hierarchical-address-profiles.md) — Status **Draft** (phải thành Approved trước code) |
| Author | Claude (AI-assisted draft) — 2026-08-25 |
| Reviewer (TL) | User acting as SA/TL (chat; formal name [TBD]) — approved 2026-08-25 |
| Workflow Mode | A (feature) — task-level mode ghi trong từng record |
| Date | 2026-08-25 |

## 1. Approach

Incremental 3 phase (theo DEC-FEAT2PZQKJ-001 proposed):

- **Phase 1 — Additive** (deploy độc lập, zero behavior change): schema `parent_city_id`/`code`/membership; profile+schema XML + contracts; hierarchy provider + GraphQL mới. Renderer cũ vẫn là default.
- **Phase 2 — Cutover**: renderer mới behind flag per-store → default; import v2 re-key; security/perf fixes; carrier + GraphQL consumer alignment; admin surfaces switch. Luma/KO stack + sub_city infra còn nguyên (BC window).
- **Phase 3 — Removal (gated)**: pre-flight prod/staging scan → drop sub_city tables/columns/EAV/observers/KO stack.

Lý do: 4 module consumer (VietNamAddress, GhnAddressMapper, GiaoHangNhanh, Ghtk) + GraphQL surface + checkout Tier-2 không thể big-bang; local DB sub_city rỗng nhưng prod chưa chứng minh — cửa sổ BC cho phép verify từng bước. Plan derives behavior từ Spec; nếu implementation phát hiện conflict → quay lại spec, không âm thầm đổi requirement.

## 2. Files affected (tổng quan — chi tiết trong mini-spec từng task)

| File / Area | Change type | Task |
|------|-------------|------|
| `Secomm/AddressDropdown/etc/db_schema.xml` + `db_schema_whitelist.json` | modify | 9AEAQQ, K09G8Y |
| `Secomm/AddressDropdown/etc/address_profiles.xsd` + config reader | new | NW66H9 |
| `Secomm/AddressDropdown/Api/{AddressProfileResolverInterface,AddressSchemaProviderInterface,LocationHierarchyProviderInterface}` + Data DTO | new | NW66H9, J49PRZ |
| `Secomm/AddressDropdown/Model/{Profile,Schema,Hierarchy}/…` | new | NW66H9, J49PRZ |
| `Secomm/AddressDropdown/etc/schema.graphqls` + `Model/Resolver/Address{Locations,Schema}Graphql.php` | new/modify | J49PRZ |
| `Secomm/AddressDropdown/etc/{di.xml,frontend/di.xml,adminhtml/di.xml,system.xml,events.xml,extension_attributes.xml}` | modify | NW66H9 (di/system), CR4D1V (ext attr), K09G8Y (events/di gỡ) |
| `Secomm/AddressDropdown/view/frontend/templates/hyva/address/` (renderer mới) | new | 3T3NSV |
| `Secomm/AddressDropdown/Helper/{Address,Data}.php`, `CustomerData/CityData.php`, `Plugin/Quote/SaveToQuote.php` | modify | CR4D1V |
| `Secomm/AddressDropdown/Model/Import/*` + `Files/Sample/address_dropdown.csv` | modify | 4F1K3N |
| `Secomm/VietNamAddress/etc/address_profiles.xml` + data patch re-key + 4 JS callers + i18n | new/modify | 4F1K3N, YQSS3M |
| `Secomm/GhnAddressMapper/Controller/Adminhtml/Ajax/CascadingOptions.php` | modify | YQSS3M |
| `Secomm/GiaoHangNhanh/Model/Service/Request/{ShippingDetails,SynchronizeOrder}DataBuilder.php` + `etc/module.xml` (sequence) | modify | YQSS3M |
| Admin surfaces JS/UI (`address-cascade.js`, `source-city.js`, `provider-mixin.js`, system config field) | modify | 9EX975 |
| SubCity PHP surface + admin grid + KO stack + EAV patch (gỡ) | delete | K09G8Y |
| `.ai/project-context/{09,02}.md`, README/CHANGELOG ×2 | modify | ZHFVRH |

## 3. Steps (độc lập reviewable)

1. **Gate 0 — DEC + Spec approval** — risk: n/a — deps: none
   - SA/TL review DEC-FEAT2PZQKJ-001 (chốt D2/D4/D5/D6) + Spec Draft → Approved; TL reviewer ghi vào metadata.
   - verify: DEC `status: accepted` + approval_date; Spec status Approved.
2. **TASK-9AEAQQ schema additive** — risk: high (DB, Tier-2) — deps: 1
   - db_schema.xml + whitelist; `setup:upgrade` trên DB có data; EXPLAIN không cần (chưa có query).
   - verify: `declarative:schema:diff` sạch; AC-001..004.
3. **TASK-NW66H9 profile/contracts** — risk: medium — deps: 2
   - XSD + reader + DTO + resolver/schema provider + config `address/profiles/mapping`; unit tests.
   - verify: unit tests xanh; AC-001..004.
4. **TASK-J49PRZ provider + GraphQL** — risk: medium — deps: 3
   - Hierarchy provider (membership filter) + 2 resolvers mới; integration fixture depth 2-3; EXPLAIN.
   - verify: AC-001..004.
5. **TASK-3T3NSV Hyva renderer (flagged)** — risk: high (checkout Tier-2) — deps: 4
   - Component Alpine generic + template mới + flag `address/general/renderer`; port validation checklist.
   - verify: AC-001..005; QC L3 customer form + cart + OSC + payment test.
6. **TASK-4F1K3N import v2 + re-key + vn_current** — risk: medium — deps: 4 (chạy song song 5)
   - Dual-format import; data patch re-key gán code (city_id không đổi); `vn_current` profile + config map VN.
   - verify: AC-001..004 (so sánh city_id trước/sau).
7. **TASK-CR4D1V security/perf fixes** — risk: medium — deps: none (touch-point sẵn có)
   - SQL binding ×3; CityData cache key locale+store; bỏ `(int)` cast + dedupe persist path; gỡ ext attr lặp.
   - verify: AC-001..004; test suite module xanh.
8. **TASK-YQSS3M carrier alignment** — risk: medium — deps: 4, 6 (D2 chốt)
   - CascadingOptions → provider; GiaoHangNhanh readers → nguồn mới + sequence decl; GraphQL shim; 4 JS callers VietNamAddress.
   - verify: AC-001..004 (grep không còn sub_city reader ngoài AddressDropdown).
9. **TASK-9EX975 admin surfaces** — risk: medium (Tier-2 customer/order) — deps: 5, 6
   - 4 surface chuyển renderer schema-driven; validation plugin theo profile.
   - verify: AC-001..004; QC admin 4 surface.
10. **TASK-K09G8Y removal (GATED)** — risk: high (destructive, Tier-2) — deps: 8, 9 + gate pre-flight
    - Prod/staging scan (evidence) → Tier-2 sign-off riêng → drop tables/columns/EAV/observers/KO stack; release note.
    - verify: AC-001..004; QC L3 full.
11. **TASK-ZHFVRH docs** — risk: low — deps: 10
    - 09 module map (bổ sung module thiếu), BR-002, README/CHANGELOG ×2.
    - verify: cross-check config.php vs module map.

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| OSC checkout (shipping/billing address + rates) | high | Flag per-store; QC L3 + payment test mỗi cutover; giữ KO stack đến Phase 3 |
| Customer address form (Hyva) validation parity | high | Checklist rule-by-rule + QC parity matrix |
| GHN/GHTK rate + order sync (carrier payload) | high | city_id canonical không đổi; QC carrier path; FK không chạm |
| Import trên env có data | medium | Dual-format; re-key UPDATE giữ city_id; dry-run + diff trước/sau |
| Admin 4 surface (AJAX form hydration) | medium | Pattern SL-011 root-scope + async region_id hydration giữ nguyên |
| CityData private-content cache | low | Fix key locale+store (TASK-CR4D1V) trước khi đổi section behavior |

## 5. Test approach

- Unit: profile merge/sort/fallback, membership inheritance, provider (mock), validators.
- Integration: migration trên DB có data; import dual-format; GraphQL 2 locale; cache isolation.
- QC L3 (bắt buộc theo AGENTS §8.6 — checkout/customer/carrier): VN dropdown end-to-end; OSC + payment test (Mollie); admin 4 surfaces; GHN/GHTK rate + order sync; cart estimate.
- Evidence per task: `.ai/runtime/evidence/TASK-*/`; feature-level: `.ai/runtime/evidence/FEAT-2PZQKJ/`.

## 6. Out of scope

- `vn_legacy` dataset + old↔new ward mapping; carrier ID normalization; geocoding; OSC deep integration (SPEC-TASK-FMAN1B); admin CRUD cho profiles; sửa `app/code/Mageplaza/*`; production deployment (human-initiated).

## 7. Open questions / Escalation

- D2/D4/D5/D6 (DEC-FEAT2PZQKJ-001) — **Tier 2 (SA/TL)** — chặn step 2 output design trở đi.
- Prod data sub_city scan — **Tier 2** — chặn step 10.
- TL reviewer cho plan này — **Tier 1** — ghi metadata.
- `vn_legacy` cần trong release? — PM/SA — mặc định KHÔNG.
