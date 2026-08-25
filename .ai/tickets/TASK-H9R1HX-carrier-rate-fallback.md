# TASK-H9R1HX — Add carrier-internal rate fallback when GHN/Ahamove API fails

- **ID**: `TASK-H9R1HX`
- **Parent**: `TASK-3F6QWZ` (SLP-12)
- **Priority**: P2 (Medium)
- **Estimate**: ~16–24h
- **Mode**: A (Tier-3: shipping)
- **Spec**: Embedded Mini-Spec (task record)
- **Risk tier**: Tier 3 (shipping — AGENTS.md §12)
- **Status**: ⏳ Proposed

## Description

Follow-up of TASK-3F6QWZ Known Limitation #1. Khi GHN/Ahamove API timeout/lỗi ở Online Mode, `collectRates()` hiện chỉ `return false` → carrier bị ẩn. Ticket này thêm carrier-internal auto-fallback: khi API lỗi, carrier trả fallback rate từ nguồn nội bộ (Mageplaza TableRate theo destination, hoặc Flat Rate config) thay vì ẩn method — checkout vẫn hoạt động ngay cả khi carrier API hoàn toàn down.

## Scope

- Thêm `RateFallbackProviderInterface` (+ impl đọc TableRate/Flat Rate) vào `Secomm_ShippingCore`.
- Wrap API call trong `collectRates()` của `AhamoveAbstractCarrier` và `GHN` với fallback branch.
- Admin config per carrier: enable fallback (default **off**), rate source (tablerate/flatrate), title suffix.
- Log mỗi lần fallback; ghi nhận fallback rate vào order `shipping_description`.

## Acceptance Criteria

- [ ] **AC-001**: Carrier API timeout/error → GHN/Ahamove vẫn hiển thị với internal-source rate; checkout hoàn tất.
- [ ] **AC-002**: Admin config toggle fallback per carrier, default off; fallback off = behavior hôm nay (graceful hide).
- [ ] **AC-003**: Mỗi lần fallback có log entry; order đặt với fallback rate ghi trong `shipping_description`.
- [ ] **AC-004**: Không thay đổi behavior Flat Rate / Mageplaza TableRate core (regression check).

## Technical Approach

1. `RateFallbackProviderInterface` trong ShippingCore — reuse pattern `OriginProviderInterface` (DEC-SL015-001).
2. Impl đọc TableRate/Flat Rate kết quả (read-only, không modify pipeline).
3. Wrap `collectRates()` API call với try/catch + fallback branch gated by config.
4. `system.xml` per carrier: enable, rate source, title suffix.
5. Logger + audit entry cho mỗi fallback occurrence.

## Files/Areas Affected

- `app/code/Secomm/ShippingCore/` — `RateFallbackProviderInterface` + impl.
- `app/code/Secomm/GiaoHangNhanh/Model/Carrier/` — fallback wrap trong `collectRates()`.
- `app/code/Secomm/Ahamove/Model/Carrier/` — fallback wrap trong `collectRates()`.

## Risks

- Tier 3 (shipping) → TL review bắt buộc.
- Fallback rate có thể không khớp với thực tế khi API up lại — cần monitoring.
- Phải đảm bảo fallback off = behavior hôm nay (zero regression).

## Open Questions (Level 2)

- **Q1**: Business có confirm yêu cầu auto-fallback chưa? (Ticket đang `proposed`, chờ confirmation.)

## Dependencies

- TASK-3F6QWZ (completed) — ShippingCore contracts, carrier modules đã refactor.
- DEC-SL015-001 (approved) — OriginProvider pattern để reuse.
- Mageplaza TableRate (installed).

## Definition of Done

- [ ] Code complete + matches mini-spec
- [ ] AI pre-review pass
- [ ] **TL review approved** (Tier 3)
- [ ] `bin/magento setup:di:compile` OK
- [ ] QC verified: API down → fallback rate hiển thị; fallback off → graceful hide unchanged; Flat Rate/TableRate core không regress
- [ ] Evidence `.ai/evidence/TASK-H9R1HX/`
- [ ] project-context updated

## Related

- Origin: [TASK-3F6QWZ](TASK-3F6QWZ-review-install-shipping-modules.md) (SLP-12) — Known Limitation #1
- Task record: [.ai/records/tasks/TASK-H9R1HX.md](../records/tasks/TASK-H9R1HX.md)
- Evidence origin: [evidence TC-003](../evidence/TASK-3F6QWZ/evidence.md)
