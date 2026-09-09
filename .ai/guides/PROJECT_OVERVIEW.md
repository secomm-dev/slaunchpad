# Project Overview — Secomm Launchpad

<!-- Enablement artifact — output: .ai/guides/PROJECT_OVERVIEW.md (VI). Filled from blueprint S1/S2/S4/S5/S6. -->

> **Purpose:** Tổng quan dự án — objective, stack, business rules, integrations, high-risk areas.
> **Human Owner:** All roles (orientation) · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "Objective" + "High-risk areas" + "Business rules" · **Expected Reading Time:** 7 min
> **Current Status:** Generated · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## Objective

[TBD — confirm với stakeholder] Mục tiêu dự kiến: ra mắt storefront thời trang **nhanh, đã địa-hóa-hóa (vi_VN)** trên Magento 2.4.8 + Hyvä, với thanh toán VNPAY và địa chỉ VN phân cấp. Client name và KPI cụ thể: `[TBD]`.

## Stack

| Component | Value |
|---|---|
| Platform | Magento 2.4.8-p5 (open-source) |
| Variant | magento-hyva |
| Frontend | Hyvä 3.x (default theme 1.5.2) · Tailwind CSS v4 (CSS-first, **không** có `tailwind.config.js`) · Magewire 1.13 · Alpine.js |
| PHP | 8.2 (compatible 8.2–8.4) |
| Database | MySQL 8.0 |
| Theme chính | `app/design/frontend/Secomm/launchpad` (Hyvä child) · `launchpad_fashion` (variant scaffold) |
| Repo | `git@bitbucket.org:secomm-vn/slaunchpad.git` · branch `development` |
| Project type | new-build · Workflow mode A |

## Key business rules (top)

| ID | Domain | Tóm tắt |
|---|---|---|
| BR-001 | Localization | Storefront song ngữ **vi_VN (primary) + en_US**. String mới phải thêm vào **cả** `vi_VN.csv` và `en_US.csv`. |
| BR-002 | Checkout | Address field → **dropdown phân cấp AJAX** (country → state/province → city/district → sub-city/ward) qua module Secomm + data VN. |
| BR-003 | Payment | Active: **Mollie**. **VNPAY** (custom, mặc định inactive) + Braintree/PayPal (bundled) sẵn sàng. |
| BR-004 | Checkout | **Mageplaza One Step Checkout** thay thế checkout mặc định (Osc + OscPro + OscUltimate). |
| BR-005 | Shipping | **Mageplaza TableRate** (carrier `mptablerate`, mặc định inactive) — dimensional (L/W/H, factor 5000). |
| BR-006 | Checkout | **ExtraFee** (surcharge) + **DeliveryTime** (chọn thời gian giao) đã cài. |

> Đầy đủ business rules: `../project-context/02_BUSINESS_RULES.md`.

## Integrations

| Name | Type | Criticality | Notes |
|---|---|---|---|
| Mollie Payments | payment | high | Composer module 3.1.1 + Hyvä compat. **Active.** REST/HTTP + webhook. |
| VNPAY | payment | high | Custom module. Pay/Info/IPN. **Mặc định inactive** — cần enable + security review trước go-live. |
| Mageplaza TableRate Shipping | shipping | medium | Carrier `mptablerate`, dimensional. Mặc định inactive. |
| Mageplaza SMTP | email | medium | Transactional email relay. Cron dọn log hằng ngày. |
| Mageplaza SocialLogin | auth | low | Social sign-in. Provider config `[TBD]`. |
| Mageplaza AbandonedCart | marketing | low | Recovery email. Cron mỗi phút. |

> **KHÔNG có** (confirmed): ERP/SAP/Odoo, Klaviyo/Mailchimp/Dotdigital, ElasticSuite/Smile (chỉ core Magento search), Amasty/Mirasvit/Wyomind.

## Architecture summary

Monolithic Magento 2.4.8-p5 storefront với Hyvä 3.x frontend. Custom code tập trung ở: module Secomm (base admin + address dropdown VN), Secomm_VNPAY, và bộ Mageplaza commerce suite (commit dưới dạng source trong `app/code/Mageplaza`). Hai Hyvä child theme: `launchpad` (primary) và `launchpad_fashion` (grandchild, scaffold).

**Quyết định kiến trúc chính:**
- Hyvä 3.x thay Luma cho performance (Tailwind + Alpine, minimal JS).
- Tailwind CSS v4 **CSS-first** (`@theme`/`@source` trong `tailwind-source.css` — **KHÔNG** tạo `tailwind.config.js`).
- Mageplaza OSC cho checkout; Magewire 1.13 cho component server-driven.
- Custom Secomm address dropdown cho VN.

## Workflow mode
**Mode A** (new-build, architecture impact) — Discovery → Spec → Build → Review → Deploy. Plan approval + code review + AI pre-review đều bắt buộc.

## High-risk areas

| Area | Lý do | Giảm thiểu |
|---|---|---|
| **Secomm_VNPAY** | Custom payment: IPN, chữ ký, mặc định inactive — nhạy cảm PCI | Enable + security review trước go-live; test idempotency + chữ ký. Tier 2 (SA review). |
| **Mageplaza One Step Checkout** | Thay checkout mặc định; tương tác payment/address phức tạp | End-to-end checkout QC + test payment trên mỗi change. Tier 2. |
| **Secomm address dropdown + data VN** | Custom address capture + data import; rủi ro chất lượng data + GraphQL | Validate dropdown phân cấp end-to-end; test admin CRUD + CSV import/export. |
| **Production infrastructure** | Chưa có Redis/Varnish/OpenSearch/CI | Chốt hạ tầng prod trước launch. **BLOCKING.** |
| **Search engine** | Magento 2.4.8 yêu cầu OpenSearch; catalog search hỏng nếu thiếu | Cấu hình OpenSearch ở production. **BLOCKING.** |

> Đầy đủ: `../project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md`.
