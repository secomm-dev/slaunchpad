---
id: FEAT-005
title: 'Vietnam shipping address support — full VN hierarchy across cart estimate + shipping methods (extend Secomm_VietNamAddress)'
mode: A                      # Tier-2: shipping + address data (AGENTS.md §12)
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-SL-003-vn-cart-shipping-estimate.md
risk: high
status: proposed
created: 2026-07-29
updated: 2026-07-29
ticket_ref:                  # SL-003 là ticket con đầu tiên (không phải legacy consolidation)
decisions:
  - DEC-017                  # accepted — generic VN address (data + cart estimate cascade) → Secomm_VietNamAddress
  - DEC-018                  # accepted — per-shipping-method VN customization → Launchpad (project, per-carrier, optional)
  - DEC-019                  # accepted — generic = Magento-default labels; VN labels → VietNamAddress; carrier code mappings → Launchpad
  - DEC-8                    # accepted (legacy ADR) — generic vs market-specific boundary
decision_assessment: material
decision_refs: [DEC-017, DEC-018, DEC-019, DEC-8]
decision_approval_summary:   # snapshot 2026-07-29
  total: 4
  pending_approval: []
  approved: [DEC-017, DEC-018, DEC-019, DEC-8]
  rejected: []
  superseded: []
  last_synced: 2026-07-29
verified_against_commit:
# Knowledge-consolidation contract (RM-07)
components:
  - CMP-VNADDR               # Secomm_VietNamAddress (stable ID placeholder)
source_areas:
  - app/code/Secomm/VietNamAddress/                                                         # mở rộng: +view/frontend (cart estimate + shipping VN)
  - app/code/Secomm/AddressDropdown/Model/Resolver/GetListCityGraphql.php                   # reuse (data/GraphQL)
  - app/code/Secomm/AddressDropdown/Model/Resolver/GetListSubCityGraphql.php                # reuse
  - app/design/frontend/Secomm/launchpad/Magento_Checkout/templates/php-cart/shipping.phtml # cart estimate surface (override by VN module)
  - app/code/Mageplaza/TableRateShipping/                                                   # VN rate customization (Launchpad, per-carrier — DEC-018)
  - app/design/frontend/Secomm/launchpad/                                                   # Launchpad: per-carrier VN customization (DEC-018)
changes_project_state: true
changes_architecture: true   # VietNamAddress: data-only → data + frontend/shipping
changes_integration: true    # shipping methods VN-aware
changes_known_limitations: true   # resolves FEAT-001 AC-005 Hyva region-based limitation
last_verified: 2026-07-29
supersedes: []
---

# Feature Record: Vietnam shipping address support

<!-- CANONICAL RECORD — large capability (full VN hierarchy cho cart estimate + customize shipping methods cho VN). -->
<!-- SL-003 = ticket con đầu tiên. Raw evidence → `.ai/runtime/evidence/FEAT-005/`. -->

## Context

**Business requirement (user 2026-07-29):** với country=**Việt Nam**, **mọi** shipping method (TableRate + GHN + Ahamove…) đều yêu cầu địa chỉ **đầy đủ** — **country + city/province + ward/commune** (zip/postal có thể bỏ). Không phải chỉ TableRate. Do đó storefront VN phải thu thập full hierarchy **Province → City → Ward** ở mọi surface thu thập địa chỉ ship, kể cả **cart "Estimate Shipping & Tax"**.

**Hiện trạng (audit 2026-07-29):**
- **Customer address form** (Hyva `hyva/address/edit.phtml`): đã có cascade đầy đủ (FEAT-001). ✅
- **Cart estimate** (Hyva `php-cart/shipping.phtml`): chỉ region-based, **không có City/Ward** → thiếu (FEAT-001 AC-005 cố tình scope region-based). ❌ → ticket [SL-003](../../tickets/SL-003-hyva-cart-estimate-city-cascade.md)
- **Shipping methods**: chỉ `Mageplaza_TableRateShipping` enabled; **GHN + Ahamove chưa cài** (Ahamove removed per DEC-8). Chưa có customization VN nào.
- **Module ownership**: `Secomm_VietNamAddress` hiện **data-only** (chỉ `InstallVietNamAddressPatch`); city/sub_city **data + GraphQL + dropdown UI** nằm trong `Secomm_AddressDropdown` (generic).

**Scope:** capability VN-market — (1) cart estimate full hierarchy, (2) customize từng shipping-method extension cho VN, (3) thuộc sở hữu module VN (DEC-017). **Risk:** Tier-2 (shipping + address data — §12) → Mode A + escalate SA/TL.

## Requirements

Acceptance criteria ở mức feature (AC chi tiết nằm trong từng ticket con).

- **AC-001** (Cart estimate — full VN hierarchy): country=VN trên cart "Estimate Shipping & Tax" → yêu cầu + cascade Province → City → Ward; zip optional. Non-VN giữ native. (BR-002 cart surface) → **SL-003**
- **AC-002** (Estimate payload): city/ward có trong request estimate → **mọi** carrier nhận full VN address để tính rate.
- **AC-003** (TableRate VN — **Launchpad**): rate customization theo district/ward VN nằm trong **Launchpad** (per-carrier — DEC-018). → SL-004
- **AC-004** (GHN / Ahamove VN — **Launchpad**): khi carrier cài → integration full VN address (province/city/ward → carrier API), **riêng từng carrier + optional** trong Launchpad. → SL-005/006 (sau khi add module). **KHÔNG có rule chung bắt buộc** mọi carrier extend.
- **AC-005** (Ownership split — DEC-017/018): phần **generic** VN address (data reuse + cart estimate cascade) → module `Secomm_VietNamAddress`; phần **per-carrier** customization → **Launchpad**. `Secomm_AddressDropdown` (generic) giữ nguyên, chỉ reuse. Carrier = mix 3rd-party + Secomm → không ép carrier extend rule chung.
- **AC-006** (Cross-cutting): i18n vi/en (BR-001); Tailwind v4 (no `tailwind.config.js`); logic gate theo country=VN; no RequireJS leak trên Hyva.
- **AC-007** (Non-regression): non-VN countries + Luma path + customer address form + admin không bị ảnh hưởng.

## Approach & Decisions

**DEC-017 (accepted 2026-07-29, user as SA/TL):** phần **generic** VN address capability (full hierarchy — reuse data/GraphQL + cart estimate cascade) nằm trong `Secomm_VietNamAddress` — mở rộng module data-only thêm lớp `view/frontend` (cart estimate override conditional country=VN). **Reuse** `Secomm_AddressDropdown` (city/sub_city data + GraphQL `GetListCity`/`GetListSubCity` + Alpine dropdown pattern). `VietNamAddress` → phụ thuộc `AddressDropdown` (module.xml sequence).

**DEC-018 (accepted 2026-07-29, user as SA/TL):** phần **customize từng shipping method** cho VN nằm trong **Launchpad** (project), **per-carrier + optional** — carrier là mix 3rd-party (TableRate, GHN, Ahamove) + Secomm → **không ép mọi carrier extend rule chung**. Module `VietNamAddress` chỉ expose generic capability; Launchpad wire per-carrier.

**DEC-8 (accepted, legacy ADR):** boundary generic vs project — generic → module; carrier-specific glue → Launchpad (như OSC → SL-002). DEC-017 + DEC-018 + DEC-019 cụ thể hóa.

**DEC-019 (accepted 2026-07-29, user as SA/TL) — label & code ownership:** (1) `Secomm_AddressDropdown` chỉ dùng label **Magento-default** (Country / State/Province / City / Sub-City…) — KHÔNG label country-specific. (2) Label VN (Quốc gia / Tỉnh-Thành phố / Phường-Xã) nằm trong `Secomm_VietNamAddress` i18n. (3) **Code mapping per-carrier** (mã province/ward riêng GHN/Ahamove/TableRate) nằm trong customization từng carrier ở **Launchpad** (DEC-018). **Cart estimator chỉ thu thập định danh admin VN** (province=Magento region, ward=default_name→native `city`), KHÔNG mang carrier code — carrier map sang code riêng tại rate-collection (Launchpad, SL-004/005/006).

**Alternatives rejected:**
- *New module `Secomm_VietNamShipping`* — rejected (user): thêm module; chọn mở rộng `VietNamAddress` để gộp market-specific VN vào một chỗ.
- *Code trong `Secomm_AddressDropdown`, gate country=VN* — rejected: vi phạm DEC-8 (generic pollute market-specific).

**Ticket breakdown (feature → tickets):**
- **Module `Secomm_VietNamAddress` (generic — DEC-017):**
  - [SL-003](../../tickets/SL-003-hyva-cart-estimate-city-cascade.md) — cart estimate full VN hierarchy (Hyva). *[proposed]*
- **Launchpad (per-carrier, optional — DEC-018):**
  - SL-004 (planned) — TableRate VN rate customization.
  - SL-005 (planned, sau khi cài) — GHN VN integration.
  - SL-006 (planned, sau khi cài) — Ahamove VN integration.

> **Open question (consider):** city/sub_city **data + GraphQL** hiện thuộc `AddressDropdown` (generic). Về dài hạn có thể cân nhắc move data layer sang `VietNamAddress` để `VietNamAddress` tự chứa (không phụ thuộc ngược AddressDropdown). Out of scope FEAT-005 — flag cho SA.

### Resolved audit decisions (Phase-1 U1–U6, locked 2026-07-29)

- **U1 (ward code) → per-carrier (DEC-018/019):** không có normalized code chung; cart chỉ thu thập định danh admin VN (province=region, ward=`default_name`→native `city`). Carrier map code riêng ở Launchpad (SL-004/005/006).
- **U2 (Luma cart) → REUSE generic:** VN module KHÔNG reinject/own Luma estimator. Generic `Secomm_AddressDropdown` đã render Province(region)→Ward(`custom_city`) cho data VN 2-level (`custom_sub_city` auto-hide). VN chỉ thêm **label i18n** + (nếu cần) mixin mảnh ép required/ward→`city`. Luma adapter = verify + tinh chỉnh, không duplicate.
- **U3 (ward data source) → REUSE `GetListCity`:** GraphQL `GetListCity(region_id)` cho Ward. `@deprecated`+`@cache(false)` (N+1, FEAT-001 AC-011/Q1) — **accept risk**, flag; fallback `CustomerData/CityData` (cached) trên Hyva nếu perf issue. Không hardening resolver trong task này.
- **U4 (Luma testability) → CONSTRAINT:** project Hyva-only → Luma cart implement theo spec NHƯ QC cần **Luma theme riêng** (FEAT-001 known limitation). Không block Hyva delivery.
- **U5 (generic VN-leak) → SEPARATE TICKET [SL-007](../../tickets/SL-007-generalize-addressdropdown-remove-vn-leak.md):** leak VN trong generic (checkout JS, ACL, customer-form phtml) vi phạm DEC-019 → cleanup riêng, KHÔNG trong cart task. Cart chỉ đảm bảo KHÔNG thêm VN mới vào generic.
- **U6 (labels) → DEC-019:** generic=Magento default; VN labels ở VietNamAddress i18n.

## Implementation Notes

**Chưa bắt đầu** (record proposed; SL-003 chờ hiện thực). Khi proceed:
1. Mở rộng `Secomm_VietNamAddress`: thêm `view/frontend` (layout override `php-cart/shipping.phtml` cho Hyva, conditional country=VN) + `etc/module.xml` sequence `Secomm_AddressDropdown`.
2. Cart estimate cascade — mirror `hyva/address/edit.phtml` (Alpine + GraphQL + `reapplySelected`).
3. Estimate payload: city/ward vào `shippingAddressFromData` → estimate API.
4. TableRate VN: rate condition theo city/ward (zip optional) — SL-004.
5. GHN/Ahamove: khi cài carrier, wire full VN address — SL-005/006.

## Test Summary

Pending (record proposed). Raw output → `.ai/runtime/evidence/FEAT-005/`.
- Cart estimate VN (Province → City → Ward) vi/en; non-VN unchanged.
- Estimate payload có city/ward → TableRate rate đúng.
- Regression: Luma cart estimate, customer form, admin.

## Compatibility Conclusions

- **Themes:** Hyvä ✅ (target); Luma ✅ (DEC-9 dual — đảm bảo không break).
- **Browsers:** không browser-specific.
- **Modules affected:** `Secomm_VietNamAddress` (mở rộng); `Secomm_AddressDropdown` (reuse, unchanged); `Mageplaza_TableRateShipping` (VN customize); GHN/Ahamove (future).
- **API contracts:** REST estimate payload gains city/ward (frontend); GraphQL `GetListCity`/`GetListSubCity` reused as-is.
- **Upgrade notes:** re-verify php-cart/shipping.phtml override trên upgrade Hyva default theme.

## References

- Tickets: [SL-003](../../tickets/SL-003-hyva-cart-estimate-city-cascade.md) (cart estimate) · SL-004/005/006 (planned)
- Decisions: [DEC-017](../decisions/DEC-017.md) (module placement) · DEC-8 / DEC-7 / DEC-9 (original AddressDropdown boundary + Hyva migration)
- Related record: [FEAT-001](FEAT-001.md) (generic AddressDropdown; AC-005 region-based mà FEAT-005 mở rộng cho VN)
- Business rules: BR-001 (i18n), BR-002 (address cascade), BR-005 (TableRate) — `project-context/02_BUSINESS_RULES.md`
- Risk tier: AGENTS.md §12 (shipping + address data — Tier 2)
- Toolkit version: v4.0
