---
id: DEC-FEATJSZQV3-003
legacy_ids: [DEC-019]
title: Generic module uses Magento-default level labels only; country labels + per-carrier code mappings owned elsewhere
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-07-29
created: 2026-07-29
last_verified: 2026-07-29
verified_against_commit:
supersedes: []
superseded_by:
work_items: [FEAT-JSZQV3]
---

# Decision Record: Label & carrier-code ownership — generic stays Magento-default

<!-- CANONICAL DECISION STORE (Phase 1a / RM-01). Accepted via user acting as SA/TL (chat 2026-07-29). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Concretizes DEC-8 (boundary) for i18n labels + extends DEC-FEATJSZQV3-002 (per-carrier code mapping). -->

## Context

Hai yếu tố mới cần chốt ownership:

1. **Label theo level:** mỗi nước có tên level riêng (VN: Quốc gia / Tỉnh-Thành phố / Phường-Xã). `Secomm_AddressDropdown` là generic reusable → không được chứa label country-specific; phải theo **Magento default** (Country / State/Province / City / Sub-City / …).
2. **Code mapping theo carrier:** mỗi shipping method có **mã code riêng** cho province/city và ward (GHN mã riêng, Ahamove mã riêng, TableRate matrix riêng). Không có một "normalized code" duy nhất dùng chung.

Tier-2 (shipping + address — §12) → Level-2 architecture decision, SA/TL.

## Decision

1. **`Secomm_AddressDropdown` = Magento-default labels only.** Chỉ dùng tên level generic của Magento (Country, State/Province, City, Sub-City, …). **KHÔNG** chứa label country-specific ("Province/City", "Ward/Commune", "Tỉnh/Thành phố", …) trong code/i18n/JS của module generic.

2. **Country-specific labels → country module.** Label VN (Quốc gia, Tỉnh/Thành phố, Phường/Xã, special-zone từ data import) nằm trong `Secomm_VietNamAddress` i18n, override label generic trong context country=VN.

3. **Per-carrier code mapping → Launchpad (DEC-FEATJSZQV3-002).** Mã province/ward riêng của từng carrier (GHN, Ahamove, TableRate) nằm trong **customization từng carrier ở Launchpad**, KHÔNG thuộc generic hay country module.
   - **Cart estimator** (FEAT-JSZQV3/TASK-FD6A9X) chỉ thu thập **định danh admin VN** (province = Magento region, ward = default_name → native `city`). **KHÔNG mang carrier code.**
   - Mỗi carrier map định danh admin VN → code riêng của carrier **tại thời điểm tính rate** (trong customization carrier ở Launchpad — SL-004/005/006).

Approved via user acting as SA/TL authority (chat 2026-07-29). Formal SA/TL name [TBD].

## Alternatives

- **Một "normalized code" chung trong generic module:** rejected — carrier có code không tương thích (GHN ≠ Ahamove ≠ TableRate); không thể chuẩn hóa về 1.
- **Country labels trong generic module:** rejected — vi phạm country-agnostic (DEC-8); module không reusable cho country khác.
- **Carrier code mapping trong country module:** rejected — country module sẽ coupling carrier 3rd-party → mất reusable (DEC-8/DEC-FEATJSZQV3-002).

## Consequences

- (+) Generic module sạch, reusable, country-agnostic; VN label cô lập trong `VietNamAddress`; carrier code cô lập per-carrier trong Launchpad.
- (+) Cart estimator scope đơn giản hơn: chỉ thu thập admin identifier, không lo carrier code.
- (−) Mỗi carrier phải có customization mapping riêng trong Launchpad (SL-004/005/006) — không tự động.
- **Confirmed violation (cleanup, ngoài scope cart):** generic module hiện đang leak VN label/code — `shipping-address-dropdown.js` + `billing-address-dropdown.js` hard-code `'Ward/Commune'`/`isVietnamCountry()`; `etc/acl.xml:13` "Manage VN Address"; `hyva/address/edit.phtml` VN comments. → cần ticket cleanup riêng (generalize lại theo Magento-default; VN behavior move sang VietNamAddress). KHÔNG fix trong task cart (no broad refactor).

## Affected components

`Secomm_AddressDropdown` i18n/code (→ Magento default, cleanup pending); `Secomm_VietNamAddress` i18n (VN labels); Launchpad (per-carrier code mappings, SL-004/005/006).

## Related records

- [FEAT-JSZQV3](../features/FEAT-JSZQV3.md) — VN shipping address support
- [DEC-FEATJSZQV3-001](DEC-FEATJSZQV3-001.md) — generic VN address capability → VietNamAddress
- [DEC-FEATJSZQV3-002](DEC-FEATJSZQV3-002.md) — per-carrier customization → Launchpad
- DEC-8 (generic vs market-specific boundary) — concretized here for labels
- DECISIONS.md index: DEC-FEATJSZQV3-003
