# Feature Spec — Abandoned Cart Email (Config, Localize, Test End-to-End)

Specification ID: SPEC-TASK-WN0PBT
Work item: TASK-WN0PBT
Specification Level: FULL

<!-- Spec cho work item TASK-WN0PBT (external PM ref: SLP-46 / LC-19 — Abandoned Cart Email). Config/test task · Mode C · không chạm high-risk area (AGENTS.md §12). -->

> **Project**: Secomm Launchpad · **Stack**: Magento 2.4.8-p5 + Hyvä 3.x (Tailwind v4 + Magewire 1.13)
> **Risk tier**: Low — marketing/outbound email, không chạm payment/checkout/order/customer-PII (AGENTS.md §12).

## Feature Overview

**Feature name**: Cài đặt, cấu hình và test Abandoned Cart Email end-to-end.

**Work item reference**: `TASK-WN0PBT` (external PM ref `SLP-46` / `LC-19`).

**Feature type**: Config + Localize + Test (extension đã cài sẵn)

**Priority**: P2 / Medium

**Mode / Risk**: C · Low

## Scope note

Spec này bao phủ **TASK-WN0PBT** (SLP-46): enable, cấu hình và verify tính năng Abandoned Cart Email sử dụng extension **Mageplaza AbandonedCart v4.6.9** (source-committed, Hyvä compatibility builtin). Không build code mới ngoài vi_VN translation CSV.

## 1. Bối cảnh & Mục tiêu

- **Mục tiêu**: Abandoned Cart Email chạy end-to-end — customer bỏ giỏ hàng → hệ thống gửi email nhắc theo schedule → customer click link khôi phục giỏ → giỏ hàng được restore.
- **Extension**: Mageplaza AbandonedCart v4.6.9 (proprietary, $149/năm Community Edition). Đã source-committed trong `app/code/Mageplaza/AbandonedCart/`, module enabled trong `config.php`.
- **Tại sao**: Business automation — tăng conversion rate bằng cách nhắc khách hàng quay lại giỏ hàng đã bỏ.
- **Hyvä compatibility**: Extension có builtin Hyvä support — `view/frontend/templates/hyva/` templates, `hyva_*` layout handles, `isHyvaTheme()` detection qua `Mageplaza_Core_Helper_AbstractData::checkHyvaTheme()`.

## 2. Luồng Dữ Liệu & Trình Tự Thực Thi

### A. Abandoned Cart Detection & Email Flow

```
Customer thêm sản phẩm vào giỏ hàng (Mageplaza OSC checkout)
         │
         ▼
Quote saved (items_count > 0, is_active = 1, customer_email not null)
         │
         ▼ (không checkout, idle > time_measure phút)
         │
Mageplaza Cron: mageplaza_abandonedcart_cron (mỗi phút)
  → Model/AbandonedCart::prepareForAbandonedCart()
    → Loop stores where module enabled
      → prepareEmailForStore():
          1. Load email schedule config (ArraySerialized matrix)
          2. Build quote collection: active, has items, email not null
             LEFT JOIN customer_log (last_login_at/updated_at)
          3. Per quote: check subscription, stock, blacklist
          4. Per schedule row: validate timing → sendMail()
             → DB transaction: generate token → send email → save log
         │
         ▼
Email gửi qua Mageplaza SMTP → MailCatcher (dev) / Production SMTP
         │
         ▼
Customer nhận email → click "Your cart here" (restore link)
         │
         ▼
Restore cart controller → load quote by token → set active → redirect to checkout
```

### B. Cron Infrastructure

```
Docker crontab (phpfpm container, app user)
  * * * * * /usr/local/bin/php bin/magento cron:run
         │
         ▼
Magento cron scheduler → mageplaza_abandonedcart_cron
                       → mageplaza_abandonedcart_report_indexer_cron
         │
         ▼
cron_schedule table (schedule + lock + history)
```

### C. Email Template Rendering

```
Cron → sendMail(quote, config, token, coupon)
         │
         ▼
Magento Email Framework → load template (abandoned_cart_1.html … _5.html)
         │
         ├─ {{template config_path="design/email/header_template"}}
         ├─ {{trans "..."}} strings → i18n CSV fallback (vi_VN.csv / en_US.csv)
         ├─ {{layout handle="ace_email_quote_items"}} → productlist.phtml
         ├─ {{layout handle="ace_email_related_products"}} → related_product.phtml
         └─ {{template config_path="design/email/footer_template"}}
```

## 3. Cấu hình Admin

### Stores > Configuration > Mageplaza > Abandoned Cart

| Group | Field | Value | Ghi chú |
|-------|-------|-------|---------|
| General | enabled | Yes | |
| General | send_email_recover | Yes | Giỏ đã recovered thì ngừng gửi |
| General | stop_sending_email | Yes | Hết stock thì ngừng gửi |
| General | send_subscribed_only | No | Gửi cả guest |
| General | enable_unsubscribe_link | No | Có thể bật sau |
| General | **Email Schedule Row 1** | Send: `1h`, Template: `Abandoned Cart Email 1`, Coupon: No | Email nhắc đầu |
| General | **Email Schedule Row 2** | Send: `24h`, Template: `Abandoned Cart Email 2`, Coupon: No | Email nhắc thứ hai |
| Report | time_measure | 5 | Giỏ idle 5 phút = abandoned |
| Report | cart_board | Yes | Hiển thị Cart Board dashboard |
| SMS Notification | enabled | No | Không dùng SMS |
| Analytics | enabled | No | Không dùng UTM tracking |
| Coupon | — | — | Không dùng auto-coupon |

## 4. Files & Areas

### Created

| File | Mục đích |
|------|-----------|
| `app/code/Mageplaza/AbandonedCart/i18n/vi_VN.csv` | 24 vi_VN translation strings cho email templates (template 1+2) + partials (productlist, related_product, unsubscribe) |

### Modified (vendor upgrade, committed riêng)

| File | Mục đích |
|------|-----------|
| `app/code/Mageplaza/AbandonedCart/Helper/Data.php` | Remove duplicate `isEnabledHyvaTheme()`, PHPCS formatting |
| `app/code/Mageplaza/AbandonedCart/Block/Email/Template.php` | Add `isHyvaTheme()` delegating to Core, image URL backslash fix, encrypted email param, PHPCS |
| `app/code/Mageplaza/AbandonedCart/Block/Customer/AbandonedCartPhone.php` | Add `isHyvaTheme()` delegating to smsHelper |
| `app/code/Mageplaza/AbandonedCart/composer.json` | Version 4.6.7→4.6.9, package name suffix `-mkp` |
| `app/code/Mageplaza/AbandonedCart/etc/adminhtml/di.xml` | PHPCS formatting (CRLF→LF) |
| `app/code/Mageplaza/AbandonedCart/view/frontend/templates/hyva/form/create/phone.phtml` | Use `$block->isHyvaTheme()`, Escaper injection, PHPCS |
| `app/code/Mageplaza/AbandonedCart/view/frontend/templates/hyva/unsubscribe.phtml` | Use `$block->isHyvaTheme()`, PHPCS |

### Config (DB, không sửa file)

| Area | Chi tiết |
|------|---------|
| `core_config_data` | Email schedule matrix (2 rows: 1h + 24h), Cart Board on, time_measure=5 |
| Crontab (container) | `* * * * * /usr/local/bin/php bin/magento cron:run` cho app user |

### Key existing files (read-only reference)

| File | Role |
|------|------|
| `app/code/Mageplaza/AbandonedCart/etc/crontab.xml` | 2 cron jobs, mỗi phút |
| `app/code/Mageplaza/AbandonedCart/etc/email_templates.xml` | 6 template registrations |
| `app/code/Mageplaza/AbandonedCart/etc/config.xml` | Default config values |
| `app/code/Mageplaza/AbandonedCart/etc/system.xml` | Admin config fields (5 groups) |
| `app/code/Mageplaza/AbandonedCart/etc/db_schema.xml` | 4 new tables + 4 altered core tables |
| `app/code/Mageplaza/AbandonedCart/Model/AbandonedCart.php` | Core logic: `prepareEmailForStore()`, `sendMail()` |
| `app/code/Mageplaza/AbandonedCart/Cron/AbandonedCart.php` | Cron entry point (3-line wrapper) |

## 5. Database Schema

### Bảng mới (tạo bởi db_schema.xml)

| Bảng | Mục đích |
|------|-----------|
| `mageplaza_abandonedcart_logs` | Log email/SMS: subject, customer_email, coupon_code, quote_id, status |
| `mageplaza_abandonedcart_logs_token` | Token restore cart: quote_id (FK→quote CASCADE), config_id, checkout_token |
| `mageplaza_abandonedcart_reports_index` | Report indexing |
| `mageplaza_abandonedcart_product_reports_index` | Product-level report indexing |

### Cột thêm vào core tables

| Bảng | Cột | Mục đích |
|------|-----|-----------|
| `quote` | `mp_abandoned_set_change` | Đánh dấu thời điểm abandoned |
| `sales_order` | `mp_abandoned_return_cart` | Đánh dấu order từ recovered cart |
| `salesrule_coupon` | `mp_generated_by_abandoned_cart`, `mp_ace_expires_at` | Coupon tự generate (không dùng trong task này) |
| `customer_entity` | `mp_ace_blacklist` | Blacklist customer khỏi nhận email |

## 6. User Stories

- **US-001**: As a **customer**, I want to receive an email reminder 1 hour after abandoning my cart so that I don't forget items I intended to purchase.
- **US-002**: As a **customer**, I want to receive a second reminder 24 hours after abandoning so that I have another chance to complete my purchase.
- **US-003**: As a **customer**, I want to click a link in the email to restore my cart so that I can continue checkout without re-adding items.
- **US-004**: As an **admin**, I want to configure email schedule (timing, templates) via Admin UI so that I can adjust without code changes.
- **US-005**: As an **admin**, I want to see abandoned carts in the Cart Board dashboard so that I can monitor recovery rate.
- **US-006**: As a **customer** (vi_VN), I want to receive the email in Vietnamese so that I can easily understand the content.

## 7. Acceptance Criteria

- **AC-001**: Main flow end-to-end chạy thành công: customer bỏ giỏ hàng → email #1 nhận sau 1h → email #2 nhận sau 24h → click restore link → giỏ hàng khôi phục đúng items + quantities → checkout bình thường.
- **AC-002**: Config có thể bật/tắt module và thay đổi email schedule (timing, template, số email) qua Admin UI mà không sửa core code.
- **AC-003**: Error/empty/loading state chính được xử lý: MailCatcher nhận email (SMTP deliverability), Cart Board hiển thị abandoned carts, email log hiển thị trong Admin.
- **AC-004**: QC có thể verify bằng dữ liệu test và không phát sinh regression flow liên quan (checkout, giỏ hàng, đăng ký/đăng nhập).

## 8. Dependencies

| Dependency | Type | Status | Notes |
|------------|------|--------|-------|
| Mageplaza AbandonedCart v4.6.9 license | License | Cần verify | Proprietary, $149/năm; module remains functional if expired but no updates |
| Mageplaza_Core ^1.5.13 | Module | ✅ Installed | Shared core dependency |
| Mageplaza SMTP | Module | ✅ Enabled | Trỏ MailCatcher (dev: mailcatcher:1025) |
| Magento cron | Infra | ✅ Running | Crontab trong phpfpm container, mỗi phút |
| Mageplaza OSC | Module | ✅ Installed | Giỏ hàng phát sinh từ OSC checkout |

## 9. Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Cron throughput (2 jobs/phút) | Medium | Medium | Đã document trong `06_KNOWN_CONSTRAINTS_AND_RISKS.md`. Monitor production load. |
| Direct edit Mageplaza source (4.6.9 upgrade) | Low | Low | Formatting + Hyva refactor + bug fix, không business logic. Giữ riêng commit. |
| SMTP deliverability (production) | Medium | Medium | Dev: MailCatcher OK. Production cần cấu hình SMTP provider + SPF/DKIM/DMARC. |
| vi_VN translations incomplete | Low | Low | Covers templates 1+2 + partials. Templates 3-5 chưa dịch (không dùng trong task này). |

## 10. Out of Scope

- Custom email HTML/CSS design.
- Client-specific business rules (custom timing logic, conditional content).
- SMS notification (extension hỗ trợ nhưng không bật).
- Auto-apply coupon code trong email.
- Production SMTP configuration (provider, SPF/DKIM/DMARC).
- Production cron infrastructure (dedicated cron container, monitoring).
- Email templates 3-5 (chỉ dùng 1+2 theo schedule).
- Integration với Klaviyo/Mailchimp/Dotdigital (không có trong project).

## 11. Test Notes

### Environment
- Dev Docker (Mark Shust docker-magento v53.0.1)
- SMTP: MailCatcher (http://localhost:1080)
- PHP 8.3-fpm, MySQL 8.4

### Test Data
- Tạo test customer account qua frontend
- Thêm ≥1 sản phẩm vào giỏ hàng
- Đóng browser, chờ cron process

### Test Shortcut (dev only)
- Giảm `time_measure` = 1 phút, schedule row 1 = `2m` để test nhanh
- **Revert trước deliver**: `time_measure` = 5, schedule = 1h + 24h

### Verification Points
1. MailCatcher nhận email với đúng template + vi_VN strings
2. Restore cart link hoạt động — giỏ khôi phục đúng items
3. Cart Board (Marketing > Abandoned Cart) hiển thị abandoned cart
4. `mageplaza_abandonedcart_logs` table có entries
5. Không regression: checkout flow bình thường, không JS errors

---
<!-- Reference: project-context/02_BUSINESS_RULES.md, 03_ARCHITECTURE_AND_INTEGRATIONS.md, 06_KNOWN_CONSTRAINTS_AND_RISKS.md, 11_CRON_QUEUE_INDEXER_CACHE.md -->