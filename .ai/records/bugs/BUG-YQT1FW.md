---
id: BUG-YQT1FW
type: bug
title: "[Checkout][Shipping fee] Phí ship quy đổi không chính xác từ VND sang USD ở Store View EN"
project_code: SLP
parent:
external_refs:
  ticket: SLP-138
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: done
created: 2026-09-03
updated: 2026-09-03
ticket_ref:
affects_version: Magento 2.4.8-p5 + Secomm_GiaoHangNhanh + Secomm_Ahamove
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Secomm/GiaoHangNhanh
  - app/code/Secomm/Ahamove
source_areas:
  - shipping-carrier
  - checkout
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-03
supersedes: []
---

# [SLP][BUG-YQT1FW] [Checkout][Shipping fee] Phí ship quy đổi không chính xác từ VND sang USD ở Store View EN

<!-- External ticket: SLP-138 -->

## Summary

Trên Store View EN (currency display = USD), phí ship của carrier **Secomm_GiaoHangNhanh** và **Secomm_Ahamove** bị quy đổi sai (hiển thị gần bằng 0 hoặc con số không đúng thực). Nguyên nhân: helper `convertPriceToDefaultCurrency()` quy đổi phí VND về **display currency của store view hiện tại** thay vì về **base currency** — trong khi contract của Magento carrier rate yêu cầu `setPrice()` nhận giá trị ở **base currency (VND)**, Magento tự quy đổi base → display lúc render.

## Mini Spec

### Goal

- Carrier rate price phải luôn được biểu diễn ở **base currency** (hiện tại = VND):
  - Base = VND → trả phí gốc, không quy đổi.
  - Base ≠ VND → quy đổi VND → base bằng rate đúng chiều (`base → VND`).
- Không làm sập checkout khi thiếu/thiêu rate: fallback an toàn + có log để chẩn đoán.

### Expected Behavior

- **VI Store View (VND)**: phí ship hiển thị đúng phí GHN/Ahamove trả về (vd 250,000 ₫).
- **EN Store View (USD)**: phí ship hiển thị đúng quy đổi 1 lần (vd 250,000 ₫ × 0.00004 = **$10.00**), không bị quy đổi kép về 0.
- Base ≠ VND (nếu tương lai đổi base): phí VND được chia cho rate `base→VND` và ra đúng mệnh giá base.

### Constraints / Rules

- Magento carrier `setPrice()` nhận giá ở base currency — tuyệt đối không trả giá trị đã quy đổi sang display currency.
- Không thay đổi hành vi các method quy đổi khác (`getAmountByStoreCurrency`, `getVndOrderAmount`, `convertPriceProductToDefaultCurrency`).
- Exception khi lookup rate phải được log, không swallow im lặng; checkout không được 500.

### Out of Scope

- `Secomm_Ahamove/Command/CreateShipment.php:256` gửi item price sang Ahamove API (nghi vấn cần VND cho đơn hàng order-currency USD) — hành vi cũ giữ nguyên, theo dõi riêng.
- Import/cập nhật tỷ giá (rate import job) và cấu hình currency.
- Carrier-level auto-fallback sang TableRate (known limitation của TASK-3F6QWZ).

### Acceptance Criteria

- **AC-001**: EN Store View — phí ship GHN/Ahamove = phí VND × rate `VND→USD` đúng 1 lần (250,000 ₫ → $10.00 với rate 0.00004).
- **AC-002**: VI Store View — phí ship hiển thị đúng mệnh giá VND gốc, không đổi.
- **AC-003**: Khi thiếu rate row (getRate trả false/0) hoặc exception → hệ thống không crash, fallback trả phí gốc + ghi log.
- **AC-004**: `php -l` + `setup:di:compile` pass cho cả 2 module.

## Steps to Reproduce

1. Vào Store View EN (USD), thêm sản phẩm vào cart, nhập địa chỉ VN ở checkout.
2. Xem phí ship method GHN/Ahamove hiển thị.

## Expected Behavior

Phí ship đúng quy đổi 1 lần từ VND sang USD theo tỷ giá cấu hình.

## Actual Behavior (Before Fix)

Phí ship hiển thị ~0 USD (hoặc số sai) — helper đã chia phí VND cho rate `USD→VND` (25000) trả ra số **mệnh giá USD**, nhưng Magento coi đó là **base (VND)** rồi quy đổi tiếp VND→USD khi render ⇒ quy đổi kép.

## Root Cause Analysis

1. **Điều kiện nhảy sai nhánh theo display currency**: code cũ `if (getDefaultCurrencyCode() != 'VND')` — `default` là **display currency** của store view hiện tại (EN = USD), không phải base. Trên EN view, nhánh này chia phí VND cho `getRate('USD','VND') = 25000` → trả ra con số mệnh giá USD, vi phạm contract carrier price = base currency (VND).
2. **Quy đổi kép lúc render**: số USD đó được Magento hiểu là VND base → render USD tiếp ⇒ khách thấy ~0.
3. **Không có guard cho rate lookup**: `ResourceModel\Currency::getRate()` trả `false` khi thiếu row → PHP 8 `DivisionByZeroError` → catch cũ trả `0` (phí ship = 0, mất silently, không log ở GHN).

## Affected Files

- [`app/code/Secomm/GiaoHangNhanh/Helper/Rate.php`](app/code/Secomm/GiaoHangNhanh/Helper/Rate.php) — `convertPriceToDefaultCurrency()`
- [`app/code/Secomm/GiaoHangNhanh/Model/Carrier/GHN.php`](app/code/Secomm/GiaoHangNhanh/Model/Carrier/GHN.php) — debug logging cho calculate-rate
- [`app/code/Secomm/Ahamove/Helper/Data.php`](app/code/Secomm/Ahamove/Helper/Data.php) — `convertPriceToDefaultCurrency()`

## Callers (blast radius)

- `Secomm\GiaoHangNhanh\Model\Carrier\GHN::collectEstimatedShippingCost()` (GHN.php:211)
- `Secomm\Ahamove\Model\Carrier\ShippingMethod\AhamoveShippingMethod` (AhamoveShippingMethod.php:85) — rate collection
- `Secomm\Ahamove\Command\CreateShipment` (CreateShipment.php:256) — không đổi hành vi với base=VND (early return như cũ)

## Verification & Test Results

Xem `.ai/evidence/BUG-YQT1FW/evidence.md`.

## Notes for TL Review (Tier 2 — shipping)

> **TL/SA: APPROVED (2026-09-03)** — fallback giữ nguyên phí gốc (không đổi sang `null`); `CreateShipment.php:256` USD→VND theo dõi ticket riêng.

- Fix chuẩn hoá về base currency cho **cả 2 carrier** (CỐT LÕI: pattern sai tồn tại song song ở 2 module).
- Fallback khi rate lookup fail = trả phí gốc (không phải 0 như cũ). Trade-off: khách có thể thấy phí VND-denominated trên EN view thay vì 0 — TL quyết định có muốn trả `null` (ẩn method) hay không.
- Pre-existing gap (out of scope): `CreateShipment.php:256` gửi `convertPriceToDefaultCurrency($item->getPrice())` — với đơn order-currency USD và base=VND, giá gửi Ahamove chưa được convert USD→VND (hành vi cũ giữ nguyên, chưa phải regression của fix này).
