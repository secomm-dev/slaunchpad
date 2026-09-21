---
id: DEC-FEATFQWEQ3-001
title: 'Secomm_Ghn Create Order dùng GHN_ADMIN_2025 names + is_new_to_address=true (dual-scheme rating/create split)'
status: accepted             # approved 2026-09-10 (user acting as SA/TL — AskUserQuestion trong plan session)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-10
created: 2026-09-10
last_verified: 2026-09-10
verified_against_commit:
supersedes:
  - SPIKE-9Z231Q (một phần — conclusion "create cũng dùng old-style PRE_2025" của clarification v3 2026-09-08)
superseded_by:
work_items: [FEAT-FQWEQ3, TASK-9Q5ZAK]
---

# Decision Record: Secomm_Ghn Create Order dual-scheme (GHN_ADMIN_2025 names + is_new_to_address)

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-10 (user acting as SA/TL).
     Nguồn: implementation plan session FEAT-FQWEQ3 — user chọn "Theo SPEC (dual-scheme)" khi
     được present xung đột giữa SPEC draft §17/§44 và SPIKE-9Z231Q v3. -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Status

Accepted (2026-09-10 — user acting as SA/TL)

## Decision Type

Architecture

## Context

SPIKE-9Z231Q (audit 2026-09-08, clarification v3 với TL/SA) kết luận GHN operational cần **exact old
ward PRE_2025** cho cả Calculate Fee lẫn Create Order; create NAME-based (`is_new_to_address=true`)
bị chuyển thành rejected alternative, vì reverse-mapping audit §15 cho thấy 93,26% cặp ward
2025→PRE_2025 là one-to-many AMBIGUOUS và TL/SA verified "old province + old district + current
ward/name KHÔNG đủ".

SPEC draft mới cho `Secomm_Ghn` (owner cung cấp 2026-09-10, §4/§17/§44) lại yêu cầu Create Order
dùng `GHN_ADMIN_2025` names (`to_province_name` + `to_ward_name`, `is_new_to_address=true`,
không `to_district_name`) — tức là mô hình dual-scheme: rating qua legacy IDs, create qua names mới.

Hai tài liệu mâu thuẫn trực tiếp trên flow Create Order; phải chốt một hướng trước khi decompose
tasks (đặc biệt quyết định có sync `GHN_ADMIN_2025` master data + mapping hay không).

## Decision (Decision)

1. **Create Order theo SPEC dual-scheme**: payload create dùng
   `is_new_to_address = true` + `to_province_name`/`to_ward_name` lấy từ mapping
   `VN_ADMIN_2025 → GHN_ADMIN_2025`; KHÔNG dùng `to_district_id`/`to_ward_code` cho destination
   create (AC-SHIP-001..003 của SPEC-FEATFQWEQ3 giữ nguyên hiệu lực).
2. **Rating KHÔNG đổi**: Calculate Fee / Leadtime / Available Services vẫn qua
   `VN_ADMIN_PRE_2025 → GHN_ADMIN_PRE_2025` → legacy `district_id + ward_code` (AC-RATE-001..002).
3. **Master data sync cả 2 scheme** `GHN_ADMIN_2025` + `GHN_ADMIN_PRE_2025` vào
   `secomm_ghn_address_unit`, mapping 2 chiều `VN_ADMIN_2025 ↔ GHN_ADMIN_2025` và
   `VN_ADMIN_PRE_2025 ↔ GHN_ADMIN_PRE_2025` (SPEC §5/§6) — rộng hơn thiết kế chỉ-PRE_2025 của
   spike v3.
4. **Điều kiện an toàn (gate của TASK-9Q5ZAK / GHN-D)**: trước khi code Create payload PHẢI có
   staging evidence chứng minh `is_new_to_address=true` trả về đúng tuyến với **ward đã merge**
   (các cặp MERGED_INTO từ audit §15 — đúng lớp trường hợp đã khiến v3 reject). Evidence trái với
   kỳ vọng → quay lại TL/SA re-decide.
5. SPIKE-9Z231Q report giữ nguyên nội dung + thêm update note trỏ về DEC này (không rewrite §16).

## Implementation addendum (2026-09-16 — external resolver removed from backlog)

- Line "Dependency: E-B v2 + VietMap + NO_MATCH vẫn là blockers của GHN-C (rating)" ở trên
  **KHÔNG còn đúng nguyên văn**: VietMap đã bị LOẠI khỏi active Launchpad backlog (yêu cầu
  geocode — merchant/TL decision 2026-09-16; architecture **Revision v8**, DEC-FEATYA2C0W-005).
- Blocker thay thế cho GHN-C rating: **Legacy RATE Strategy opt-in** (architecture v5 §15.1 —
  `DIRECT_FALLBACK` | `MAP_THEN_FALLBACK` qua `LegacyRateStrategy` +
  `FallbackEligibilityInterface`); AMBIGUOUS/UNMAPPED residual → `UNAVAILABLE` → fallback qua
  bridge. External resolver seam giữ nguyên dạng dormant extension point (không provider, không
  roadmap).
- GHN-C không còn blocked bởi VietMap; blocker còn lại = legacy strategy configuration
  (composition) + shipping rate-provider slice (TASK-5JQYMP đã ship contracts).

## Consequences

- (+) Create Order không phụ thuộc resolution PRE_2025 (tránh SPOF external resolver cho flow gửi
  hàng — chỉ rating phụ thuộc); payload đơn giản hơn (2 name, không district).
- (+) Tách bạch rõ hai address path (§4 Critical Address Rule) — mỗi operation một chain, không reuse.
- (−) Phải sync + maintain thêm `GHN_ADMIN_2025` master data + mapping 2025 (chi phí GHN-B tăng).
- (−) Rủi ro vận hành nếu GHN thực tế misroute ward merged với new names — bộc lộ qua gate #4
  (staging evidence) trước khi code; nếu fail, fallback decision = quay lại old-style create
  (spike v3) bằng 1 capability/profile change.
- Dependency: E-B v2 + VietMap + NO_MATCH vẫn là blockers của GHN-C (rating) — DEC này KHÔNG đổi D4.

## Verification

* SPEC-FEAT-FQWEQ3 §52.1 ghi quyết định + gate; FEAT-FQWEQ3 ticket_ref TASK-9Q5ZAK có ghi BLOCKED
  kèm staging evidence.
* SPIKE-9Z231Q `.ai/research/SPIKE-9Z231Q-secomm-ghn-canonical-architecture.md` có update note
  đầu mục §16 trỏ về DEC này.
* GHN-D code review bắt buộc chứa evidence staging merged-ward trong `.ai/evidence/TASK-9Q5ZAK/`.
