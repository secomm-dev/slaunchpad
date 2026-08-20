# Implementation Plan: TASK-FMAN1B — Launchpad OSC Address Integration

| Field | Value |
|---|---|
| Specification | specs/SPEC-TASK-FMAN1B-launchpad-osc-address.md (backfilled 2026-08-18 — draft, giữ TBD research của ticket) |

> Mode A · **Plan only — chưa viết code**. Draft — chờ **OSC seam research (state 3)** + TL **`plan-approval` (Level 2)**.
> ⚠️ Plan này **contingent**: OSC seam chưa chốt (Knockout-based) → nhiều step ở mức approach, detail sẽ cụ thể hoá sau research.

## Metadata

| Field | Value |
|-------|-------|
| Ticket | [TASK-FMAN1B](../tickets/TASK-FMAN1B-launchpad-osc-address-integration.md) |
| Spec | _TBD — `.ai/specs/SPEC-TASK-FMAN1B-launchpad-osc-address.md` (chưa tạo, sau research)_ |
| Author | AI draft |
| Reviewer (TL) | [TBD] |
| Workflow Mode | A |
| Date | 2026-07-16 |
| Decisions | DEC-7 (Strategy B) · DEC-8 (boundary — OSC thuộc Launchpad) |
| Depends on | [TASK-88NDV5](TASK-88NDV5-implementation-plan.md) (module expose Magewire address component + GraphQL) |
| Validation level | **L3** (chạm checkout flow + address data — §8.6) |

## 1. Approach

**Boundary (DEC-8):** tất cả code coupling Mageplaza OSC nằm trong **package Launchpad** (`app/design/frontend/Secomm/launchpad/Mageplaza_Osc/...` + project integration code). **KHÔNG** sửa `app/code/Mageplaza/*` in-place; **KHÔNG** thêm OSC code vào module `Secomm_AddressDropdown`.

**Strategy B (DEC-7):** reuse Magewire address cascade component từ TASK-88NDV5; wire vào OSC address region (shipping + billing).

**Research findings (state 3 — partial):**
- OSC address render qua **Knockout UI components** `Mageplaza_Osc/js/view/shipping`, `.../form/element/region`, `.../view/billing-address`; templates `web/template/container/address/{shipping-address,billing-address,shipping/form,billing/create}.html`.
- OSC layout `onestepcheckout_index_index.xml` định nghĩa `shipping-step.shippingAddress` + `billing-step` qua jsLayout.
- OSC **dispatch JS events riêng** (không trùng Hyva/Luma) → cơ chế set shipping/billing information của OSC phải verify.
- **Seam chưa chốt**: cách cắm Magewire/Alpine component vào vùng Knockout của OSC (mix paradigm — fragile) — cần research sâu hơn (xem Open Questions).

## 2. Files affected (dự kiến — TBD sau research)

| File | Change type | Lý do / AC |
|------|-------------|-----------|
| `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/templates/...` (mới) | new | Theme override OSC address templates nhúng Magewire component (AC-O1, AC-O2) |
| `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/layout/onestepcheckout_index_index.xml` (mới) | new | Gắn Magewire address cascade vào OSC shipping/billing region (AC-002, AC-003) |
| Project integration code (theme-level Magewire/.phtml) | new | Wire TASK-88NDV5 component → OSC; sync address field với OSC submit (AC-002) |
| i18n `vi_VN.csv` + `en_US.csv` | modify | Chuỗi OSC-specific (AC-O3) |
| **KHÔNG affect** | — | `app/code/Secomm/AddressDropdown` (DEC-8), `app/code/Mageplaza/*` (in-place forbidden) |

## 3. Steps (mức approach — detail cụ thể hoá sau OSC seam research)

0. **OSC seam research (state 3, mandatory)** — risk: high — deps: TASK-88NDV5 done
   - Đọc `Mageplaza_Osc/view/frontend/web/js/view/{shipping,billing-address}.js` + `form/element/region.js` + template `container/address/*.html` → chốt **điểm cắm** Magewire component.
   - Verify **OSC submit mechanism** (set shipping/billing information) → cách sync address field mà không break totals/payment.
   - verify: research note `.ai/research/` + chốt seam option (theme-override vs OSC plugin).
   - ⚠️ Đây là step **chặn** — không implement trước khi seam chốt + SA/TL review.

1. **Theme override scaffold** — risk: medium — deps: step 0, TASK-88NDV5
   - Tạo `app/design/frontend/Secomm/launchpad/Mageplaza_Osc/` (templates + layout override).
   - verify: override được OSC nhận (render trong OSC page).

2. **Shipping address integration (AC-002)** — risk: high — deps: step 1
   - Nhúng Magewire address cascade (TASK-88NDV5) vào OSC shipping form; sync `region_id`/`city`/`sub_city` với OSC shipping address model.
   - verify: OSC → VN address cascade → "set shipping information" thành công → shipping methods + TableRate load (L3).

3. **Billing address integration (AC-003)** — risk: high — deps: step 2
   - Tương tự cho billing; persist đúng khi place order.
   - verify: billing cascade + place order thành công (L3).

4. **Regression guard (AC-004)** — risk: high — deps: step 2,3
   - Verify ExtraFee (BR-006) + DeliveryTime (BR-006) + Mollie payment (BR-003) render + hoạt động — không regression.
   - verify: **QC end-to-end checkout + payment** bắt buộc (BR-004).

5. **i18n (AC-O3)** — risk: low — deps: step 2,3
   - Thêm chuỗi OSC vào `vi_VN.csv` + `en_US.csv`.

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| OSC seam Knockout↔Magewire mix paradigm fragile/break render | high | Research sâu step 0; fallback pure theme-override; SA review |
| OSC submit mechanism lệch → totals/payment regression | high | Verify set shipping/billing info; QC end-to-end checkout + Mollie (L3) |
| ExtraFee/DeliveryTime/payment UI break do override | high | AC-004 QC full; không edit Mageplaza in-place |
| TASK-88NDV5 interface chưa đủ → wire khó | medium | Chốt interface contract giữa TASK-88NDV5/TASK-FMAN1B sớm |
| Tier 2 checkout flow | high | Escalate SA/TL; QC checkout + payment bắt buộc |

## 5. Test approach

- **L3 — checkout flow + address data**: OSC shipping + billing cascade vi/en; place order Mollie; ExtraFee/DeliveryTime không regression (AC-004, BR-004).
- **QC end-to-end**: full checkout VN address → payment → order state đúng.
- **Build**: `npm run build`; verify không sửa `app/code/Mageplaza/*` (AC-O2) + không thêm OSC code vào module (AC-O1).

## 6. Out of scope

- Module `Secomm_AddressDropdown` changes (→ TASK-88NDV5, DEC-8).
- `app/code/Mageplaza/*` in-place edits (forbidden — AC-O2).
- Backend/admin/data (untouched).
- Rewrite OSC toàn bộ (chỉ address region).

## 7. Open questions / Escalation

- **OSC seam (step 0)** — chặn: cắm Magewire ở đâu trong OSC Knockout? Option: (a) theme-override OSC address template nhúng Magewire; (b) OSC plugin/preference bind address model. _Cần research + SA quyết._
- **OSC submit mechanism**: set shipping/billing information của OSC — sync với Magewire state thế nào? (research)
- **TASK-88NDV5 interface contract**: module expose gì (Magewire component public API + GraphQL) để Launchpad wire? (chốt khi làm TASK-88NDV5)
- **Gate**: `plan-approval` (TL, Level 2) — **sau khi** step 0 research xong + plan cụ thể hoá.
- **Sequencing**: TASK-FMAN1B chỉ bắt đầu **sau** TASK-88NDV5 hoàn thành (hoặc chốt interface sớm).
