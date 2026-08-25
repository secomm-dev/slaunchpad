# TASK-3F6QWZ — Review, Install and Refactor shipping method modules

- **ID**: `TASK-3F6QWZ`
- **External Ref**: `SLP-12`
- **Priority**: P1 (High)
- **Estimate**: ~40–56h
- **Mode**: A (Tier-3: shipping)
- **Spec**: [.ai/specs/SPEC-TASK-3F6QWZ-review-install-shipping-modules.md](../specs/SPEC-TASK-3F6QWZ-review-install-shipping-modules.md)
- **Risk tier**: Tier 3 (shipping — AGENTS.md §12)
- **Status**: ✅ Completed (2026-08-20)

## Description

Review, install và refactor 4 module vận chuyển hiện có (`Secomm_ShippingCore`, `Secomm_GhnAddressMapper`, `Secomm_GiaoHangNhanh`, `Secomm_Ahamove`) lên môi trường Launchpad Core (Magento 2.4.8-p5 / PHP 8.2-8.4 / Hyvä 3.x / Mageplaza OSC). Dọn dẹp deprecated code, xóa hardcoded JSON, chuẩn hóa theo kiến trúc dùng chung.

## Scope

- Clone & review 4 module legacy.
- Namespace migration: `Boolfly_GiaoHangNhanh` → `Secomm_GiaoHangNhanh`.
- Xóa `session_destroy()` trong Ahamove, xóa JSON tĩnh >35.000 dòng trong GHN.
- `Secomm_ShippingCore`: shared contracts (`OriginProviderInterface`, `CarrierTrackingProcessorInterface`).
- `Secomm_GhnAddressMapper`: dynamic mapping admin grid, CLI, cascading AJAX.
- Real-time rate hiển thị trên Mageplaza OSC.
- Graceful hide khi API lỗi (carrier trả `false`).
- Webhook normalize qua ShippingCore pipeline.

## Acceptance Criteria

- [x] **DoD-001**: 4 module compile DI + static content không lỗi.
- [x] **DoD-002**: Real-time rate hiển thị đúng trên Mageplaza OSC.
- [x] **DoD-003**: Graceful hide khi API lỗi, checkout không chết.
- [x] **DoD-004**: Webhook normalize qua ShippingCore pipeline, tracking state + comment đúng.

## Technical Approach

1. Review 4 module, identify deprecations/conflicts.
2. `Secomm_ShippingCore` — scaffold shared interfaces.
3. `Secomm_GhnAddressMapper` — dynamic mapping thay JSON tĩnh.
4. `Secomm_GiaoHangNhanh` — namespace migration + dynamic mapping + queue consumers + status mapper.
5. `Secomm_Ahamove` — webhook bridge + xóa `session_destroy()`.
6. Integration test: Mageplaza OSC real-time rate + graceful hide + webhook.

## Files/Areas Affected

- `app/code/Secomm/ShippingCore/` — shared contracts.
- `app/code/Secomm/GhnAddressMapper/` — address mapping module.
- `app/code/Secomm/GiaoHangNhanh/` — GHN v2 carrier.
- `app/code/Secomm/Ahamove/` — Ahamove carrier.

## Decisions

- DEC-SL015-001 — ShippingCore Origin Provider
- DEC-SL017-001 — ShippingCore Tracking pipeline
- DEC-TASK3F6QWZ-001 — Decouple GHN mapping vào Secomm_GhnAddressMapper
- DEC-TASK3F6QWZ-002 — Refactor GHN/Ahamove vào ShippingCore tracking/status mapper

## Risks

- Tier 3 (shipping) → TL review bắt buộc.
- GHN/Ahamove API downtime → graceful hide (Known Limitation).
- Namespace migration blast radius.

## Known Limitations

1. **Carrier Rate Fallback**: Chưa có auto-fallback nội bộ khi API lỗi — cần bật sẵn Flat Rate/TableRate làm phương thức dự phòng. → Follow-up: TASK-H9R1HX.

## Definition of Done

- [x] Code complete + matches spec
- [x] AI pre-review pass
- [x] **TL review approved**
- [x] `bin/magento setup:di:compile` + `setup:static-content:deploy` OK
- [x] QC verified: OSC checkout e2e + graceful hide + webhook tracking
- [x] Evidence `.ai/evidence/TASK-3F6QWZ/`
- [x] Decisions minted + approved

## Related

- Follow-up: [TASK-H9R1HX](TASK-H9R1HX-carrier-rate-fallback.md) (carrier-internal rate fallback)
- Spec: [SPEC-TASK-3F6QWZ](../specs/SPEC-TASK-3F6QWZ-review-install-shipping-modules.md)
