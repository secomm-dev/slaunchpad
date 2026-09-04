---
id: FEAT-YA2C0W
type: feature
project_code: SLP
parent: null
legacy_ids: []
title: 'Vietnam current/legacy address profiles, current↔legacy relations + VietnamAddressResolver (Secomm_VietnamAddress)'
mode: A                      # schema mới + relation model + service contract → Tier-2 review
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-FEAT-YA2C0W-vietnam-current-legacy-address.md
risk: medium
status: proposed
created: 2026-08-25
updated: 2026-08-25
ticket_ref:
  - TASK-R83FXW                   # Đăng ký profiles + config mapping + membership seeding (done 2026-08-26; renamed theo DEC-003)
  - TASK-ADT94K                   # Phase B — versioned scheme import foundation (VN_ADMIN_*, re-scoped 2026-08-27)
  - TASK-9394A9                   # Phase C — scheme registry + historical unit reference layer (2026-08-27)
  - TASK-J9AVGK                   # Phase D — mapping layer + resolution API (supersedes TASK-X0XKH4 + TASK-AP6YXP; 2026-08-27)
  - TASK-7RK8Q3                   # Integration tests + QC evidence (điều chỉnh theo phases mới khi kích hoạt)
  - TASK-S0M7YC                   # Docs sync + boundary audit
decisions:
  - DEC-FEAT2PZQKJ-001                  # kiến trúc nền recursive hierarchy + profiles (accepted — FEAT này consume)
  - DEC-8                    # generic vs country boundary (accepted)
  - DEC-FEATJSZQV3-001                  # VN capability sống ở VietNamAddress (accepted)
  - DEC-FEATJSZQV3-003                  # generic Magento-default labels; VN labels → VietNamAddress (accepted)
  - DEC-TASKYJENM2-001                  # VN 2-level ward = native city (accepted)
  - DEC-FEATYA2C0W-001                  # VN profiles + relation model + resolver contract (accepted 2026-08-25; relation/resolver part superseded by DEC-003 tasks)
  - DEC-FEATYA2C0W-002                  # swap model (accepted 2026-08-26; naming part superseded by DEC-003)
  - DEC-FEATYA2C0W-003                  # versioned VN_ADMIN_* schemes + registry + historical + mapping/resolution (accepted 2026-08-27)
decision_assessment: material
decision_refs: [DEC-FEAT2PZQKJ-001, DEC-8, DEC-FEATJSZQV3-001, DEC-FEATJSZQV3-003, DEC-TASKYJENM2-001, DEC-FEATYA2C0W-001, DEC-FEATYA2C0W-002, DEC-FEATYA2C0W-003]
decision_approval_summary:
  total: 8
  pending_approval: []
  approved: [DEC-FEAT2PZQKJ-001, DEC-8, DEC-FEATJSZQV3-001, DEC-FEATJSZQV3-003, DEC-TASKYJENM2-001, DEC-FEATYA2C0W-001, DEC-FEATYA2C0W-002, DEC-FEATYA2C0W-003]
  rejected: []
  superseded: []
  last_synced: 2026-08-27
verified_against_commit:
components:
  - CMP-VNADDR               # Secomm_VietnamAddress — profiles + datasets + relations + resolver (mở rộng từ data-only adapter)
  - CMP-ADDR                 # Secomm_AddressDropdown — consume contracts (không sửa generic trong FEAT này)
source_areas:
  - app/code/Secomm/VietNamAddress/
changes_project_state: true
changes_architecture: true   # relation model + resolver contract mới trong country adapter layer
changes_integration: false
changes_known_limitations: true
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-YA2C0W] Vietnam current/legacy address profiles, current↔legacy relations + VietnamAddressResolver (Secomm_VietnamAddress)

<!-- CANONICAL RECORD — layer country-specific trên nền FEAT-2PZQKJ. Foundation cho carrier mapping (GHN/GHTK)
     SAU này — resolver phải deterministic 1:1 + AMBIGUOUS tường minh trước khi carrier work bắt đầu. -->
<!-- Gate: DEC-FEATYA2C0W-001 ACCEPTED 2026-08-25 (user acting as SA/TL) — gate mở; TASK-R83FXW kích hoạt được ngay (TASK-ADT94K vẫn chờ TASK-4F1K3N). -->

## Context

Sau FEAT-2PZQKJ Phase 1 (schema recursive + profile engine — TASK-9AEAQQ/NW66H9 done), `Secomm_AddressDropdown` đã có đủ contracts cho country adapters. `Secomm_VietnamAddress` hiện là thin data-adapter (3 validators + data patch 2-level + i18n + GraphQL callers) — **chưa có** profiles, membership, relations, hay resolver (review 14 mục 2026-08-25, xem Spec Appendix A).

Bài toán VN đặc thù (cải cách hành chính 2025): 63 tỉnh cũ → 34 tỉnh mới; ~10.595 đơn vị cấp xã cũ → 3.313 phường/xã mới. Storefront chạy current 2-level; các hệ thống cũ (carrier master data, dữ liệu khách lịch sử) vẫn nói ngôn ngữ legacy 3-level → cần representation song song + resolution current↔legacy deterministic.

**Phát hiện review quan trọng**: region codes 2 dataset chồng lấn namespace (01–63 cũ vs 01–34 mới, khác nghĩa — 02 cũ=Hà Giang, 02 mới=Tuyên Quang). Import legacy qua import entity hiện tại sẽ upsert **đè 34 regions đang chạy storefront** → bắt buộc namespace riêng (`L-` prefix, D-R2).

**Phân tầng trách nhiệm (giữ nguyên DEC-8/DEC-FEATJSZQV3-001)**: carrier mapping / GHN-GHTK IDs / geocoding **out of scope** (PART 7 của requirement) — các module carrier consume resolver này sau.

**Risk**: schema mới trong country module (Tier-2 review); không chạm checkout/storefront behavior (additive).

## Requirements (feature-level; AC chi tiết trong sub-ticket + Full Spec)

- **AC-001**: Profile `vn_current` (region + city depth 1) và `vn_legacy` (region + city depth 1-2) đăng ký qua `etc/address_profiles.xml` của VietNamAddress, labels là translation keys dịch trong i18n 3 locale sẵn có (vi_VN/en_VN/en_US).
- **AC-002**: Dataset membership tách biệt: legacy-only nodes KHÔNG trả về dưới vn_current và ngược lại (subtree-claim ở region level — 34 vs 63 regions).
- **AC-003**: Legacy dataset import an toàn — codes `L-01..L-63`, depth-2 qua `parent_city_id`, không đụng data current đang chạy.
- **AC-004**: Relation table chuẩn hóa `secomm_vietnam_address_relation` (canonical IDs 2 chiều, relation_type nullable) + import/validate qua CLI deterministic từ file checked-in, nguồn documented.
- **AC-005**: `VietnamAddressResolverInterface` trả structured Result (`status` RESOLVED/AMBIGUOUS/NOT_FOUND, `candidateLocationIds[]`, `resolvedLocationId` khi deterministic) — hỗ trợ cả 2 chiều, 1:1/1:N/N:1; AMBIGUOUS KHÔNG auto-chọn.
- **AC-006**: Resolver độc lập carrier (không biết GHN/GHTK/Ahamove); input non-VN → NOT_FOUND có kiểm soát.
- **AC-007**: Regression zero — storefront/cart estimate/admin validators/OSC nguyên vẹn (FEAT này thuần additive vào country module).

## Sub-ticket breakdown

| Task | Mode | Risk | Ghi chú |
|---|---|---|---|
| [TASK-R83FXW](../tasks/TASK-R83FXW.md) | B | medium | profiles + mapping config + membership vn_current |
| [TASK-ADT94K](../tasks/TASK-ADT94K.md) | A | medium | legacy dataset + import depth-2 — **blocked-by TASK-4F1K3N** (import v2) |
| [TASK-X0XKH4](../tasks/TASK-X0XKH4.md) | A | medium | relation table + CLI + seed region-level; ward-level chờ **D-R1** (nguồn data) |
| [TASK-AP6YXP](../tasks/TASK-AP6YXP.md) | A | medium | resolver contract + impl — blocked-by TASK-X0XKH4 |
| [TASK-7RK8Q3](../tasks/TASK-7RK8Q3.md) | B | medium | integration tests + QC evidence |
| [TASK-S0M7YC](../tasks/TASK-S0M7YC.md) | C | low | docs + update TASK-4F1K3N scope |

## Risks

- **R1 — Nguồn relations (D-R1)**: chưa có file mapping authoritative trong project (NĐ-CP 165/2025 public nhưng phải được cung cấp/review). Không infer theo tên.
- **R2 — Import legacy 14k rows**: cần idempotent patch + không đè codes hiện tại (prefix L-).
- **R3 — Membership chưa có consumer runtime**: provider (TASK-J49PRZ, FEAT-2PZQKJ) là consumer của membership — seeding trước vẫn đúng, nhưng isolation test phải ở tầng data.
- **R4 — Scope leak**: không đưa relation/carrier logic vào AddressDropdown (audit trong TASK-S0M7YC).

## References

- Full Spec: [SPEC-FEAT-YA2C0W-vietnam-current-legacy-address](../../specs/SPEC-FEAT-YA2C0W-vietnam-current-legacy-address.md)
- Plan: [FEAT-YA2C0W-implementation-plan](../../plans/FEAT-YA2C0W-implementation-plan.md)
- Decision: [DEC-FEATYA2C0W-001](../decisions/DEC-FEATYA2C0W-001.md) (accepted 2026-08-25)
- Nền tảng: [FEAT-2PZQKJ](FEAT-2PZQKJ.md) — TASK-9AEAQQ (schema), TASK-NW66H9 (profile engine) done; TASK-4F1K3N/J49PRZ pending
- Precedent: [FEAT-E2HM1J](FEAT-E2HM1J.md) (admin surfaces), [TASK-KCBDDT](../../tickets/TASK-KCBDDT-generalize-addressdropdown-remove-vn-leak.md) (boundary)
- Related module: `Secomm_VietNamMarket` (sequence → VietNamAddress; register locale `en_VN`)
