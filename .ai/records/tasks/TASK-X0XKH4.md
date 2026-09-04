---
id: TASK-X0XKH4
type: task
title: 'Relation table secomm_vietnam_address_relation + CLI import/validate + region-level seed'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: MINI
spec_status: DRAFT
specification_ref: Embedded Mini-Spec
risk: medium
status: proposed
created: 2026-08-25
updated: 2026-08-25
decisions: [DEC-FEATYA2C0W-001]
decision_assessment: material
components:
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/VietNamAddress/etc/
  - app/code/Secomm/VietNamAddress/Model/
  - app/code/Secomm/VietNamAddress/Console/
  - app/code/Secomm/VietNamAddress/Files/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-YA2C0W][TASK-X0XKH4] Relation table secomm_vietnam_address_relation + CLI import/validate + region-level seed

> **SUPERSEDED 2026-08-27 — DEC-FEATYA2C0W-003 / TASK-J9AVGK**: mapping model mới (bảng `secomm_vietnam_address_mapping`, directed edges source/target scheme + code, relation SAME_AS/RENAMED_TO/MERGED_INTO/SPLIT_INTO, code-based trên unit codes `VNA25-`/`VNAP25-`) thay thế thiết kế `secomm_vietnam_address_relation` của task này. Không implement task này; historical note giữ lại cho truy vết.

> **Input assumption changed 2026-08-27 — DEC-FEATYA2C0W-002 (swap model)**: 2 dataset không còn cùng tồn tại trong DB (không có region `L-`). Khi kích hoạt, task này phải thiết kế relation **code-based** (tham chiếu `VNC-…`/`VNL-…` + region code chính thức, không FK tới id của 2 subtree đồng thời); source `VN_Address_Relations.csv` vẫn theo kế hoạch D-R1 (region-level trước). Blocked-by cũ TASK-ADT94K nay chỉ còn nghĩa "dataset + importer sẵn sàng" (đã xong phần infrastructure).

<!-- CANONICAL TASK RECORD — schema mới trong country module (declarative). Ward-level relations bị gate bởi D-R1 (nguồn data). -->

## Summary

Bảng quan hệ chuẩn hóa current↔legacy (canonical IDs 2 chiều) + CLI `secomm:vietnamaddress:import-relations` / `:validate-relations` deterministic từ CSV checked-in + seed sẵn 63→34 region relations (nguồn chính thức, khối lượng nhỏ kiểm được bằng tay).

## Mini Spec

### Goal
Relation model hỗ trợ 1:1, 1:N, N:1 ở cả region + city level; import/validate có thể chạy lại định kỳ khi dữ liệu chính thức cập nhật.

### Expected Behavior
1. Declarative schema: `secomm_vietnam_address_relation` (relation_id PK AI; source_type VARCHAR(16) region|city; source_id INT UNSIGNED; target_type; target_id; relation_type VARCHAR(16) NULL — UNCHANGED|MERGED|SPLIT|RENAMED; UNIQUE(source_type,source_id,target_type,target_id); INDEX(target_type,target_id)) + whitelist. Polymorphic IDs — không FK cứng (đồng bộ pattern membership table).
2. Hai chiều là 2 rows riêng (current→legacy + legacy→current) — reverse lookup thẳng, deterministic.
3. `Files/VN_Address_Relations.csv` — format: `source_type,source_code,target_type,target_code,relation_type` (tham chiếu qua `code` — ổn định hơn ID qua các lần re-import); header comment ghi rõ nguồn (NĐ-CP 165/2025 phụ lục — D-R1).
4. CLI import: resolve code→ID qua bảng chung, validate orphans (code không tồn tại → báo lỗi dòng, không insert lẻ), upsert theo UNIQUE key; CLI validate: báo duplicates/orphans/missing-reverse.
5. Seed region-level: 63 legacy region → 34 current region (2 chiều = 126 rows; relation_type MERGED). Ward-level: KHÔNG seed cho đến khi có nguồn D-R1 — file CSV chỉ chứa region rows ở giai đoạn này.
6. Name-assist: CLI validate có `--suggest` mode (match tên trong cùng current region) CHỈ xuất report gợi ý — không bao giờ tự insert.

### Constraints / Rules
- Không infer relations theo tên trong import path (spec cấm name-matching authoritative).
- Không thêm fields lý thuyết (metadata JSON...) — chỉ khi use-case thật xuất hiện.
- CLI exit code nonzero khi có error rows (CI-able).

### Out of Scope
- Resolver service (TASK-AP6YXP); carrier consumption.

### Acceptance Criteria
- AC-001: Schema apply sạch + whitelist sync (generate-whitelist exit 0).
- AC-002: Import seed 126 relation rows region-level; re-run idempotent.
- AC-003: Validate bắt orphan + missing-reverse (test với fixture lỗi).
- AC-004: `--suggest` chỉ in report, DB count không đổi.

## Approach

Plan: [FEAT-YA2C0W-implementation-plan](../../plans/FEAT-YA2C0W-implementation-plan.md) — Step 3 (blocked-by TASK-ADT94K cho region codes L-xx).

## Implementation Notes

Chưa triển khai (proposed — D-R1 ward-level data pending; region-level seed khả thi ngay).

## Verification

- [ ] AC-001..004 — evidence: `.ai/runtime/evidence/TASK-X0XKH4/`

## Related records

- Parent feature: FEAT-YA2C0W
- Decision: DEC-FEATYA2C0W-001 (accepted — D-R1 nguồn data, D-R3 relation_type)
