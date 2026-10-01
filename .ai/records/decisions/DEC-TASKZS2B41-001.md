---
id: DEC-TASKZS2B41-001
title: 'GHN dimension limits merchant-tunable qua System Config PER-DIMENSION SHARED (max_length_cm/max_width_cm/max_height_cm — 3 paths dùng chung RATE + CREATE, defaults 200×3 Create contract); GhnShipmentConstraints constants là authoritative default/fallback'
status: accepted             # TL directive 2026-09-30 ("Move limit dimension vào system config") + plan approval 2026-10-01
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-10-01
created: 2026-09-30
last_verified: 2026-10-01
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-ZS2B41]
---

# Decision Record: GHN dimension limits → System Config

## Status

Accepted (2026-09-30 — TL directive "Move limit dimension trong GhnShipmentConstraints vào
system config"; plan approval cùng ngày). **REVISED cùng ngày (pre-review, user
correction): tách per-dimension** — không phải 1 giá trị max-side chung mà config RIÊNG
cho length/width/height ở cả RATE và CREATE (6 paths). **REVISED lần 2 (2026-10-01,
pre-review, user correction #2): consolidation 6 → 3 SHARED paths** — RATE và CREATE đọc
CÙNG MỘT bộ `carriers/secomm_ghn/max_{length,width,height}_cm`; default thống nhất **200**
(Create contract, DOCUMENTED). Lý do: Calculate Fee chỉ required `weight` — dimensions
optional — nên không cần bộ config riêng khắt khe hơn ở RATE. Quan sát sandbox 150
(2026-09-18) bị SUPERSEDE làm default; giữ làm merchant tuning guidance. Các bản
max-side chung (chưa từng release) và 6-path (chưa release, chưa commit) đều không còn.

## Decision Type

Architecture — carrier-owned hard limits (dimension) trở thành merchant-tunable qua admin;
GHN runtime wiring đổi đọc giá trị qua Config reader. Amend ghi chú FROZEN
"GhnShipmentConstraints reuse consts" của TASK-RT50KH/TASK-MQ2DRG Ở KHÍA CẠNH DIMENSION
(weight giữ nguyên contract constants).

## Decisions

1. **3 config paths dùng chung** trong group `carriers/secomm_ghn` (Sales → Delivery
   Methods) — PER-DIMENSION, SHARED RATE + CREATE (rev. 2026-10-01):
   `max_length_cm` / `max_width_cm` / `max_height_cm`, defaults **200×3** (Create
   contract, DOCUMENTED; sandbox 150 superseded làm default — merchant có account GHN
   enforce 150 hạ config trong admin). Validation `validate-digits
   validate-greater-than-zero`; defaults khai báo trong `etc/config.xml`.
2. **`GhnShipmentConstraints`**: `MAX_SIDE_CM = 200` là authoritative default/fallback
   DUY NHẤT cho cả 3 chiều (`RATE_MAX_SIDE_CM` xoá). `Config::readPositiveIntCm()`
   fallback về constant khi config empty/non-numeric/≤0 ⇒ hard limit KHÔNG BAO GIỜ bị
   vô hiệu hoá bởi cấu hình xấu. Provenance docblock ghi CẢ HAI giá trị với provenance
   riêng: 200 DOCUMENTED (default), 150 SANDBOX_OBSERVED (superseded — tuning guidance).
3. **Single read seam**: `Secomm_Ghn\Model\Config::getMaxLengthCm()` /
   `getMaxWidthCm()` / `getMaxHeightCm(?int $storeId = null)`. RATE:
   `QuoteParcelEstimator` đọc 3 giá trị (store scope) và truyền vào
   `QuoteParcelEstimate` (3 optional ctor params, defaults = `GhnPackageLimits::
   MAX_DIMENSION_CM` ⇒ BC). CREATE: `GhnPhysicalLimit` inject Config; interpreter +
   ViewModel consume qua interface → tự theo config.
4. **CREATE per-dimension enforcement FIX (2026-10-01)**: `GhnPhysicalParcelInterpreter::
   assertWithinLimits()` so MỖI chiều với limit CỦA CHIỀU ĐÓ (code cũ so mọi chiều với
   length limit — width/height vượt own limit lọt khi length nhỏ). Fail message
   "…above the %4 cm %2 limit." thay "per-side limit".
5. **Weight KHÔNG config** (scope): TYPE_2/TYPE_5 giữ contract constants (type split là fee
   contract; 50kg RATE cap là DEC-TASKMQ2DRG-001 business decision) — follow-up riêng nếu TL
   muốn.
6. **Config.xml default + const fallback là 2 nguồn có chủ đích**: config.xml phục vụ admin
   form hiển thị; const là authoritative fallback — đổi giá trị phải đồng bộ cả hai
   (comment trong config.xml ghi rõ).
7. **Semantics không đổi**: missing dims never reject; reason codes giữ nguyên; reject vẫn
   carrier-owned (không fallback). RATE vẫn là display filter (ẩn GHN), CREATE vẫn
   fail-closed trước HTTP.

## Consequences

- Merchant chỉnh limit không cần deploy; set giá trị xấu (0/âm/rỗng) an toàn (fallback).
- STORE-scope ở RATE (đọc qua SCOPE_STORE với `$storeId`); CREATE đọc scope-null —
  giới hạn của `CarrierPhysicalLimitInterface` (không có store param), documented
  limitation, out of scope.
- Giá trị đã lưu dưới 6 path cũ bị bỏ qua im lặng (chưa từng release; dev/staging DB có
  thể còn sót — cleanup thủ công tuỳ chọn, không data migration).
- RATE mặc định nới lỏng 150 → 200: cart có unit 151–200cm giờ QUOTE được (trước bị ẩn);
  merchant hạ config để siết lại như cũ.
- CREATE gate chặt hơn về width/height (per-dimension thật).
- Tests lock: fallback/override/cast + flow qua estimator + CREATE limit override +
  regression width/height own-limit.

## Verification

- Ghn suite **471/471** (211.251 assertions; +5 test: 3 regression width/height own-limit,
  1 merchant-lowered-150, 1 default-200 — 466 trước rev.); Ghn+ShippingCore 1071/1071;
  compile OK; validator `--check-records --check-specs` baseline **56 FAIL không tăng**
  (trước/sau 2026-10-01).
