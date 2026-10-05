# SPEC — TASK-T5J7M2 — Format Price Product (currency display precision)

> **Work item**: TASK-T5J7M2 · **Mode**: B · **Status**: VALID · **TL approval**: solution + est 5h (2026-10-05)
> **Ticket nguồn**: "Format Price Product — [Product / price] Thêm config để config thay đổi định dạng giá của sản phẩm"

## 1. Requirement

Config điều khiển **display decimal precision** của currency, scope **Store View**:

- `Auto` = giữ behavior theo currency/locale
- `0` → `1.000 ₫` · `1` → `1.000,0 ₫` · `2` → `1.000,00 ₫` · `3` → `1.000,000 ₫` · `4` → `1.000,0000 ₫`
- Resolver phải resolve theo **currency code** — `VND=0` không được làm USD thành 0 decimals

## 2. Ràng buộc (chỉ display — hard constraint của TL)

KHÔNG thay đổi: database price precision · product price value · tax/discount calculation ·
currency conversion · `convertAndRound()` · order totals numeric · payment amount/API payload.
Vendor không được sửa.

## 3. Solution (TL-approved) — 3 lớp đồng bộ, 1 source of truth

`Model\PrecisionResolver` — per-currency lookup (`CODE=PRECISION` pairs + `default_precision`
fallback), `null` = Auto; memoize per store (hot path); DI proxy.

| Lớp | Điểm can thiệp | File |
|---|---|---|
| Server PHP | before-plugin `Magento\Directory\Model\Currency::formatTxt` — choke point duy nhất (`format → formatPrecision → formatTxt`), phủ cả Intl NumberFormatter + Zend fallback | `Plugin/CurrencyFormatting.php` |
| Magento JS | after-plugin `Magento\Framework\Locale\Format::getPriceFormat` — `precision` + `requiredPrecision` cùng lúc → priceUtils / checkoutConfig.priceFormat | `Plugin/JsPriceFormat.php` |
| Hyvä | template load sau `head.hyva-scripts`, wrap `hyva.formatPrice` với `minimumFractionDigits = maximumFractionDigits = N`; Auto → không render | `Block/CurrencyPrecision.php` + view/frontend |

## 4. Scope matrix

**Áp config**: storefront toàn bộ (PLP/PDP + tier/special/custom option/configurable preview,
search, wishlist, minicart, cart, checkout Mageplaza OSC + ExtraFee + DeliveryTime, success page,
customer account) · email (order confirm, AbandonedCart — render frontend emulation) · PDF invoice/creditmemo.

**Không áp (by design)**: CSV export (`Currency\Filter` đi framework Currency riêng) ·
GraphQL (chỉ trả `{value, currency}`) · REST numeric · payment payload · giá trị DB.

**Admin backend**: skip `adminhtml` (D-4 default — giữ native; email/PDF vẫn áp).

## 5. Quyết định

| ID | Quyết định | Nguồn |
|---|---|---|
| D-1 | Config theo ticket: Auto + per-currency precision, scope Store View (UI: textarea pairs `VND=0, USD=2`) | Ticket + TL est |
| D-2 | Email + PDF áp config (= storefront) | User chốt 2026-10-02 |
| D-3 | Override cả caller chủ động truyền precision — parity display wins | User chốt 2026-10-02 |
| D-4 | Skip adminhtml (default; chờ TL confirm — nâng lên "áp theo store của order" sau vẫn được) | Khuyến nghị, pending |

## 6. Acceptance / regression checklist (QC)

1. **Golden gate** — Auto: output giống hệt pre-module (VND + USD), cả server lẫn `hyva.formatPrice` console.
2. Per surface (chọn precision 0/2/4): PLP · PDP initial · configurable option change · custom option price ·
   special/old price · minicart · cart · checkout · order totals · customer account — **parity server ↔ JS**.
3. VND + ít nhất 1 currency có decimals (USD) — USD đổi không kéo VND theo.
4. **Totals sau place order không đổi** (so quote ↔ sales_order trong DB với ≥2 config khác nhau):
   subtotal, grand_total, tax, discount/coupon, shipping, ExtraFee.
5. Payment amount: VNPAY `vnp_Amount` int ×100, Mollie `value` "xxxx.00" — không đổi khi precision 0↔4.
6. Invoice / creditmemo / refund: số tiền DB + payload nguyên vẹn.
7. Email xác nhận + PDF invoice khớp storefront; admin backend + CSV export giữ native.

## 7. Estimation

TL-approved **5h** (module 0.5 · config 0.5 · resolver 1 · formatTxt plugin 1 · getPriceFormat plugin 1 · Hyvä 1).
Tracking: `.ai/templates/estimation-tracking.csv`. Dev complete 2026-10-05; QC + TL review pending.
