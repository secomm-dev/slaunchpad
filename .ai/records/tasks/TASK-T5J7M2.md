---
id: TASK-T5J7M2
type: task
title: 'Format Price Product — configurable currency display precision (Secomm_CurrencyPrecision)'
project_code: SLP
parent: null
mode: B
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-T5J7M2-format-price-precision.md
risk: medium
status: in_review
created: 2026-10-05
updated: 2026-10-05
legacy_ids: []
decisions: []
decision_assessment: none-material
components:
  - CMP-STOREFRONT
source_areas:
  - app/code/Secomm/CurrencyPrecision/
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: null
last_verified: 2026-10-05
supersedes: []
---

# [SLP][TASK-T5J7M2] Format Price Product — configurable currency display precision (Secomm_CurrencyPrecision)

## Bối cảnh (Context)

Ticket **Format Price Product** — thêm config điều khiển số chữ số thập phân hiển thị của giá
(Auto = giữ behavior theo currency/locale, hoặc cố định 0–4), scope **Store View**, resolver
phải phân biệt theo **currency code** (VND=0 không được làm USD thành 0 decimals).

Root cause đã rà (không nằm riêng ở Hyvä): Magento price formatting có nhiều path dùng
precision mặc định 2; Hyvä có JS formatter riêng (`hyva.formatPrice`/`Intl.NumberFormat`) —
nên fix phải đồng bộ cả 3 lớp để server-rendered price và dynamic price không bao giờ lệch.

**TL đã approve solution + est 5h** (2026-10-05): module 0.5h + config 0.5h + resolver 1h +
formatTxt plugin 1h + getPriceFormat plugin 1h + Hyvä wrapper 1h. Điều kiện của TL: chỉ được
ảnh hưởng display precision, tuyệt đối không đổi numeric value / logic tính tiền; verify kỹ
totals sau place order; rà scope thực tế tránh sót chỗ dùng formatter riêng.

## Specification & Plan

- **Spec (FULL, VALID)**: [SPEC-TASK-T5J7M2-format-price-precision.md](../../specs/SPEC-TASK-T5J7M2-format-price-precision.md)
- **Plan**: không có file plan riêng — solution đã TL-approved trong ticket, kiến trúc 3 lớp
  mô tả trong spec §3; est tracking: `.ai/templates/estimation-tracking.csv` (row TASK-T5J7M2).

## Mode & Approach

**Mode**: B — standard feature (AC rõ, architecture unchanged, display-only; checkout chỉ bị
tác động phần hiển thị nên không phải Tier-2 flow change — TL đã review solution trước khi dev).
**Approach**: module mới `Secomm_CurrencyPrecision` — 1 resolver làm source of truth, áp đồng bộ
3 lớp: (1) before-plugin `Magento\Directory\Model\Currency::formatTxt` (choke point duy nhất
`format → formatPrecision → formatTxt`, phủ PLP/PDP/cart/checkout/email/PDF); (2) after-plugin
`Magento\Framework\Locale\Format::getPriceFormat` (precision + requiredPrecision → priceUtils /
checkoutConfig.priceFormat); (3) template load sau `head.hyva-scripts` wrap `hyva.formatPrice`
(min = max = N; Auto → không inject). Không sửa vendor. Không đụng `convertAndRound()`,
DB precision, tax/discount math, payment payload.

## Thay đổi đã triển khai (2026-10-05, dev complete — chờ QC + TL review)

| Est row | File(s) |
|---|---|
| 0.5h scaffold | `registration.php`, `etc/module.xml`, `etc/di.xml`, README, CHANGELOG, i18n vi/en |
| 0.5h config | `etc/adminhtml/system.xml` (group `currency/display_precision`, Store View scope), `Model/Source/PrecisionOptions`, `Model/Config/Backend/CurrencyPairs` (validate `CODE=0..4` lúc save) |
| 1h resolver | `Model/PrecisionResolver.php` (per-currency lookup + default fallback, null = Auto, memoize per store, DI proxy) + `Test/Unit/Model/PrecisionResolverTest.php` (5 case) |
| 1h server prices | `Plugin/CurrencyFormatting.php` (before `formatTxt`; override mọi caller precision theo D-3; skip `adminhtml` theo D-4 default) |
| 1h JS price format | `Plugin/JsPriceFormat.php` (after `getPriceFormat` → precision/requiredPrecision) |
| 1h Hyvä | `Block/CurrencyPrecision.php` + `view/frontend/layout/default.xml` (after `head.hyva-scripts`) + `view/frontend/templates/currency_precision.phtml` |

Bug fix trong lúc dev: property promoted `$resolver` trùng tên với property của parent
`Magento\Framework\View\Element\Template` → fatal redeclare readonly — đã đổi `$precisionResolver`.

## Checklist verify (theo yêu cầu TL)

1. **Golden gate**: Auto → output giống hệt pre-module (VND + USD).
2. **Totals không đổi** sau place order (so `quote` vs `sales_order` DB, 2 lần với config khác nhau):
   subtotal / grand total / tax / discount / shipping / ExtraFee.
3. **Payment amount**: VNPAY `vnp_Amount` (int ×100) + Mollie `amount.value` ("xxxx.00") không đổi khi precision 0↔4.
4. **Invoice / creditmemo / refund**: tiền trong DB + payload đúng nguyên.
5. **Parity server ↔ JS** trên từng surface (PLP, PDP initial/configurable/custom option/special-tier price,
   minicart, cart, checkout OSC, customer account, email, PDF).
6. **Phạm vi giữ nguyên**: admin backend + CSV export native; GraphQL chỉ trả số thô (không đổi).

## Việc còn mở

- [ ] QC manual theo spec §6 + unit test xanh trong docker
- [ ] TL confirm D-4 (hiện default: skip `adminhtml`)
- [ ] TL confirm UI config: đang textarea `VND=0, USD=2` (đúng est); nâng cấp dropdown-per-currency ~0.25h nếu client cần
- [ ] TL code review → update record sang `completed` + estimation actual hours + CURRENT_STATE.md
