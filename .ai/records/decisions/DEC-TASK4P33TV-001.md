---
id: DEC-TASK4P33TV-001
title: 'GHN required_note enum sandbox-verified = {KHONGCHOXEMHANG, CHOXEMHANGKHONGTHU, CHOTHUHANG}; RequiredNote source model trim về 3 giá trị (bỏ CHOXEMHANG, CHOTHUHANGKHONGDOI); builder whitelist tham chiếu constants của source model (single source of truth)'
status: accepted             # TL directive 2026-09-30 (shipment 21 CREATE fail) + plan approval
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-09-30
created: 2026-09-30
last_verified: 2026-09-30
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-4P33TV]
---

# Decision Record: GHN required_note enum — sandbox-verified, single source of truth

## Status

Accepted (2026-09-30 — shipment 21 CREATE fail-closed `INVALID_CONFIGURATION`;
plan approval cùng ngày).

## Decision Type

Architecture — contract correction (enum set xác định bằng sandbox probe) + chống drift
giữa admin source model và create request builder. Shipping/GHN — Tier 2 (plan approval).

## Decisions

1. **Enum sandbox-verified (shop 200537, 2026-09-30)**: create-order chấp nhận ĐÚNG 3 giá
   trị `KHONGCHOXEMHANG` ✓, `CHOXEMHANGKHONGTHU` ✓, `CHOTHUHANG` ✓; từ chối `CHOXEMHANG` ❌
   và `CHOTHUHANGKHONGDOI` ❌ ("Sai thông tin đầu vào"). Khớp contract matrix dòng 99.
2. **`RequiredNote` source model trim về đúng 3 giá trị** (bỏ 2 token chết; constants là
   nơi ĐUY NHẤT định nghĩa enum + labels sửa khớp semantics thật:
   KHONGCHOXEMHANG=Not allow viewing, CHOXEMHANGKHONGTHU=Allow viewing no trial,
   CHOTHUHANG=Allow trial refund supported).
3. **`GhnCreateRequestBuilder::REQUIRED_NOTES` tham chiếu constants của source model** —
   hết drift (trước đây 2 lớp tự khai riêng; admin offer 2 giá trị builder fail-closed →
   shipment không thể tạo).
4. **Default giữ `CHOXEMHANGKHONGTHU`** (config.xml + DB seeded value phải thuộc 3-set).
5. **DB remediation**: mọi row `required_note=CHOXEMHANG` (không hợp lệ — đã thấy trên dev
   config_id 470) phải UPDATE về giá trị hợp lệ; staging kiểm tra tương tự khi deploy.

## Consequences

- Admin dropdown chỉ còn 3 option hợp lệ; invariant test chặn drift
  (source options ⊆ builder whitelist + 2 legacy token fail-closed).
- Mọi môi trường đã save `CHOXEMHANG` cần data-fix (dev đã fix; staging kiểm tra khi deploy).

## Verification

- Sandbox probe 5/5 candidate (accepted 3, rejected 2) — evidence TASK-4P33TV.
- Retry shipment 21 → create thành công L8A7TC / fee 715.000đ / track attached.
- Ghn suite 450/450 (+ invariant tests).
