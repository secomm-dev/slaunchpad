---
id: DEC-FEATYA2C0W-001
title: VietnamAddress layer — VN_CURRENT/VN_LEGACY profiles + relation model + resolver contract trên nền FEAT-2PZQKJ
status: accepted             # approved via user acting as SA/TL authority (chat 2026-08-25)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-25
created: 2026-08-25
last_verified: 2026-08-25
verified_against_commit:
supersedes: []
superseded_by: DEC-FEATYA2C0W-002, DEC-FEATYA2C0W-003   # cả hai PARTIAL — xem "Supersession note" trong thân record
work_items: [FEAT-YA2C0W, TASK-R83FXW, TASK-ADT94K, TASK-X0XKH4, TASK-AP6YXP, TASK-7RK8Q3, TASK-S0M7YC]
---

# Decision Record: VietnamAddress layer — profiles + relations + resolver

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-08-25 (user acting as SA/TL; formal SA/TL name [TBD]) — từ review 14 mục 2026-08-25 (FEAT-YA2C0W baseline). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Xây TRÊN DEC-FEAT2PZQKJ-001 (accepted) — consume contracts, không mở lại kiến trúc generic. -->

> **⚠ SUPERSSESSION — đọc trước khi cite** (record giữ nguyên làm baseline lịch sử, viết bằng naming gốc):
> - **Naming/profiles**: `vn_current` → scheme **`VN_ADMIN_2025`** (profile `vn_admin_2025`); `vn_legacy` → scheme **`VN_ADMIN_PRE_2025`** (profile `vn_admin_pre_2025`) — theo DEC-FEATYA2C0W-003. `CURRENT`/`LEGACY` chỉ còn là **status label** trong scheme registry, không phải identity.
> - **D-R2 (prefix `L-01..L-63`) + D-R4 (coexist +11.4k rows)**: DEAD — thay bằng **swap model** (DB chứa MỘT scheme VN tại một thời điểm, purge + re-import) theo DEC-FEATYA2C0W-002.
> - **Relation model** (điểm 4: `secomm_vietnam_address_relation`, UNCHANGED|MERGED|SPLIT|RENAMED) → chuẩn hoá lại ở DEC-003: bảng `secomm_vietnam_address_mapping`, relation `SAME_AS|RENAMED_TO|MERGED_INTO|SPLIT_INTO`.
> - **Resolver contract** (điểm 6): statuses `RESOLVED/AMBIGUOUS/NOT_FOUND` → `EXACT/MAPPED/AMBIGUOUS/UNMAPPED` (`VnAdminAddressResolverInterface`); nguyên tắc AMBIGUOUS không auto-pick giữ nguyên.
> - **Vẫn còn giá trị**: country-layer boundary (DEC-8 / DEC-FEATJSZQV3-001/003), membership subtree-claim trên `secomm_address_profile_location`, mapping là data artifact checked-in + CLI deterministic (files hiện hành: `VN_ADMIN_2025_import.csv` / `VN_ADMIN_PRE_2025_import.csv` 7 cột), carrier-agnostic.

## Context

Sau cải cách hành chính VN 2025: 63 tỉnh cũ → 34 tỉnh mới; ~10.595 đơn vị cấp xã cũ → 3.313 phường/xã mới. Storefront chạy current 2-level (đã trong DB); hệ thống cũ (carrier master data, dữ liệu lịch sử) nói ngôn ngữ legacy 3-level. `Secomm_VietnamAddress` hiện là thin data-adapter — chưa có profiles/membership/relations/resolver.

Review 2026-08-25 phát hiện: (1) region codes 2 dataset chồng lấn namespace (01–63 cũ vs 01–34 mới, khác nghĩa) — import legacy qua import entity hiện tại sẽ đè regions đang chạy; (2) không có bảng relation hay mapping nào hiện hữu; (3) nguồn dữ liệu mapping current↔legacy (phụ lục NĐ-CP 165/2025) chưa có file trong project.

## Decision (proposed — từng điểm chờ SA/TL)

1. **Hai profiles đăng ký qua XML của country module** (`Secomm/VietNamAddress/etc/address_profiles.xml`, mechanism TASK-NW66H9): `vn_current` (region + city depth 1) và `vn_legacy` (region + city depth 1-2). Labels là translation keys (English default → i18n 3 locale vi_VN/en_VN/en_US của VietNamAddress) — không hardcode label trong renderer (DEC-FEATJSZQV3-003 giữ).
2. **Membership subtree-claim ở region level**: vn_current claim 34 region mới; vn_legacy claim 63 legacy region — dùng nguyên bảng `secomm_address_profile_location` của FEAT-2PZQKJ, không cơ chế mới.
3. **Legacy dataset import với namespace riêng**: region codes `L-01..L-63` (D-R2), depth-2 qua `parent_city_id` + `code` refs bằng import v2 (TASK-4F1K3N) — KHÔNG dùng import entity 9-cột cũ (sẽ upsert đè). Data current bất biến trong quá trình import.
4. **Relation model chuẩn hóa** `secomm_vietnam_address_relation` (source_type/source_id → target_type/target_id, canonical IDs 2 chiều là 2 rows, relation_type nullable UNCHANGED|MERGED|SPLIT|RENAMED) — hỗ trợ 1:1/1:N/N:1 ở region + city; không flatten thành `legacy_city_id` (D-R3).
5. **Nguồn relations là data artifact checked-in** (`VN_Address_Relations.csv`, nguồn documented — D-R1): import/validate qua CLI deterministic; name-matching chỉ là `--suggest` report, không bao giờ authoritative. Phase đầu seed **region-level 63→34** (khối lượng nhỏ, kiểm được); ward-level chờ nguồn chính thức.
6. **Resolver contract** `VietnamAddressResolverInterface` → structured `VietnamAddressResolutionInterface` (RESOLVED/AMBIGUOUS/NOT_FOUND + candidateLocationIds + resolvedLocationId khi deterministic); AMBIGUOUS KHÔNG auto-chọn; độc lập carrier hoàn toàn (không biết GHN/GHTK/Ahamove) — carrier modules consume sau.
7. **Phân tầng giữ nguyên** (PART 6): AddressDropdown không thêm gì VN-specific; carrier master data ở carrier modules; FEAT này chỉ là country layer.

### Đã chốt trong review (2026-08-25 — user acting as SA/TL)

- **D-R1 — Nguồn relations**: **phase region-level trước** — seed ngay 63→34 (126 rows, kiểm tay được); ward-level để NOT_FOUND tường minh (Known Limitation) cho đến khi có file mapping chính thức; KHÔNG bao giờ infer theo tên.
- **D-R2 — Namespace legacy codes**: **prefix `L-`** — legacy regions import với codes `L-01..L-63`, tách hoàn toàn với 34 codes hiện tại; giữ representation legacy 63 tỉnh.
- **D-R3 — relation_type**: **giữ, nullable** (UNCHANGED|MERGED|SPLIT|RENAMED) — debug/đối soát/migration; resolver không phụ thuộc.
- **D-R4 — Dung lượng DB**: **chấp nhận +~11.4k rows** legacy vào `directory_region_city` (indexed, membership-filtered, không ảnh hưởng query current).

## Alternatives

- **Biểu diễn legacy bằng labels-only trên cùng dataset:** rejected — sai sự thật dữ liệu (63≠34 regions, ward sets khác nhau); spec cấm "same locations, only different labels".
- **Import legacy qua import entity 9-cột cũ (sub_city):** rejected — đè region codes đang chạy + nuôi đường sub_city sắp retire.
- **Bảng relation 1 cột `legacy_city_id` trên city:** rejected — không diễn đạt 1:N/N:1; prompt cấm flatten.
- **Relations chỉ trong code/YAML config thay vì bảng:** rejected — 10k+ rows ward-level + cần query/reverse-lookup + cập nhật bằng CLI.
- **Name-matching runtime để resolve:** rejected — không deterministic, spec cấm làm authoritative mapping.

## Consequences

- (+) Foundation deterministic cho carrier mapping (GHN/GHTK) — điều kiện tiên quyết đã rõ ở PART 7 requirement gốc.
- (+) Data current bất biến; legacy song song có kiểm soát; cơ chế cập nhật dữ liệu hành chính tương lai = cập nhật CSV + CLI.
- (+) AMBIGUOUS tường minh — caller tự chính sách xử lý.
- (−) Bảng chung phình +14k rows (D-R4); cần import v2 (TASK-4F1K3N) hoàn thành trước legacy import.
- (−) Ward-level resolution tạm NOT_FOUND cho đến khi D-R1 có data — Known Limitation ghi rõ.
- Follow-up: carrier mapping work items consume resolver; TASK-4F1K3N scope note (TASK-S0M7YC).

## Affected components

`CMP-VNADDR` (mở rộng: profiles + membership + relations + resolver + CLI), `CMP-ADDR` (consume-only). Source: `app/code/Secomm/VietNamAddress/`.

## Related records

> `work_items` (frontmatter) là canonical. Mục này chỉ narrative.

- Feature: FEAT-YA2C0W (sub-tickets TASK-R83FXW…TASK-S0M7YC)
- Nền tảng: DEC-FEAT2PZQKJ-001 (accepted — recursive hierarchy + profiles), DEC-8, DEC-FEATJSZQV3-001/003, DEC-TASKYJENM2-001
- Related: TASK-4F1K3N (import v2 — dependency), TASK-J49PRZ (hierarchy provider — membership consumer)
- DECISIONS.md index: DEC-FEATYA2C0W-001
