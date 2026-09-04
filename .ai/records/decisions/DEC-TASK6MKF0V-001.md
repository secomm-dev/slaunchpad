---
id: DEC-TASK6MKF0V-001
title: sub_city retired — mọi cấp hành chính render qua profile engine (city depth), submit contract bỏ sub_city
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-03
created: 2026-09-03
last_verified: 2026-09-03
verified_against_commit:
supersedes:
  - DEC-FEAT2PZQKJ-001 (phần BC-keep sub_city submit contract)
superseded_by: []
work_items: [TASK-6MKF0V]
---

# Decision Record: sub_city retirement — depth-2 only qua profile engine

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-03 (user acting as SA/TL authority, chat). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context

Profile engine (FEAT-2PZQKJ) đã mô hình hoá `vn_admin_2025` = 2-level và `vn_admin_pre_2025` = 3-level qua **recursive city levels** (`address_profiles.xml`: `city depth=1/2`), bỏ bảng sub_city khỏi luồng dữ liệu (`directory_city_sub_city` = 0 rows; importer mới không seed). Tuy nhiên legacy layer vẫn mang toàn bộ plumbing sub_city: JS cascade sync `custom_attributes[sub_city]`, server plugins (`SaveToQuote`, `AddSubCityFieldToAddressEntity`, mapper/renderer), admin CRUD `SubCity/*`, GraphQL `GetListSubCity` (@deprecated), cột `sub_city` trên 3 address tables. prior answer đã ghi nhận gap "PRE_2025 3-level không render được trên legacy cascade".

User chốt 2026-09-03 (chat): *"Đây là project hoàn toàn mới, không có order hoặc address cũ — `vn_admin_pre_2025` phải áp dụng depth-2 chứ không được tiếp tục dùng sub_city nữa."*

## Decision

1. **sub_city retired hoàn toàn** khỏi Secomm_AddressDropdown + Launchpad_Osc: không còn submit contract (`custom_attributes[sub_city]`, `extension_attributes.sub_city`), không còn UI select, không còn admin CRUD, không còn GraphQL field, không còn DB column/table.
2. **Mọi cấp hành chính render qua profile engine** (city `depth` theo profile schema): legacy renderer chỉ cần hỗ trợ đủ depth mà profile khai báo; PRE_2025 3-level = region → city depth-1 → city depth-2.
3. Bảng `directory_city_sub_city` + cột `sub_city` trên `quote_address` / `sales_order_address` / `customer_address_entity`: **DROP** — an toàn vì project mới, verified 0 orders / 0 customer addresses / 0 sub_city data (5 quote rows = rác QC dev, không populate ngược).
4. Việc thực thi qua **TASK-6MKF0V** (Mode A — DB schema + API contract change, Tier 2), chạy SAU khi batch address đang pending (TASK-3T3NSV + Phase B/C/D) được TL review + commit.
5. Phần "BC keep sub_city submit contract" của DEC-FEAT2PZQKJ-001 (giữ hidden sub_city để tương thích submit cũ) **không còn hiệu lực** — không có data cũ để bảo vệ.

## Rationale

- Một nguồn chân lý cho hierarchy (profile engine) thay vì 2 cơ chế song song (depth vs sub_city) — giảm bề mặt bug + QC.
- Project mới: chi phí migration data = 0; giữ plumbing chỉ tạo dead code + rủi ro ghi rác cột đã drop.
- Legacy cascade không render được depth-2 → giữ sub_city KHÔNG giải được PRE_2025; chỉ schema renderer/Option A mới đủ.

## Consequences

- (+) Pipeline sạch: 1 data model, 1 render path; PRE_2025 swap được end-to-end.
- (−) Breakchange nội bộ: mọi consumer đang gửi sub_city (nếu còn) sẽ bị bỏ qua; GraphQL contract thu hẹp (project nội bộ, chưa có client bên ngoài).
- (−) TASK-6MKF0V phải hoàn tất TRƯỚC khi swap sang PRE_2025 trên storefront thật, nếu không 3-level sẽ mất cấp.
- TASK-K09G8Y (remove copies/legacy mixins) tiếp tục giữ nguyên scope của nó — task này chỉ chạm phần sub_city.

## Related

- [TASK-6MKF0V](../tasks/TASK-6MKF0V.md) · [FEAT-2PZQKJ](../features/FEAT-2PZQKJ.md) · [DEC-FEAT2PZQKJ-001](DEC-FEAT2PZQKJ-001.md) (BC-keep superseded) · [DEC-TASKFMAN1B-001](DEC-TASKFMAN1B-001.md) · TASK-K09G8Y, TASK-FMAN1B
