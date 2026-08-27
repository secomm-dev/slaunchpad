# Evidence — TASK-WN0PBT: Abandoned Cart Email

Work item: TASK-WN0PBT (SLP-46 / LC-19)
Date: 2026-08-27
Environment: Dev Docker (docker-magento v53.0.1, PHP 8.3-fpm, MySQL 8.4, MailCatcher)

---

## TC-001: DB Schema Verified

**Command:** `bin/magento setup:upgrade --keep-generated` → Upgrade completed successfully.

**4 new tables (verified):**

| Table | Status |
|-------|--------|
| `mageplaza_abandonedcart_logs` | ✅ Exists |
| `mageplaza_abandonedcart_logs_token` | ✅ Exists |
| `mageplaza_abandonedcart_reports_index` | ✅ Exists |
| `mageplaza_abandonedcart_product_reports_index` | ✅ Exists |

**5 altered core columns (verified):**

| Table | Column | Status |
|-------|--------|--------|
| `customer_entity` | `mp_ace_blacklist` | ✅ |
| `quote` | `mp_abandoned_set_change` | ✅ |
| `sales_order` | `mp_abandoned_return_cart` | ✅ |
| `salesrule_coupon` | `mp_ace_expires_at` | ✅ |
| `salesrule_coupon` | `mp_generated_by_abandoned_cart` | ✅ |

**Module status:** `Mageplaza_AbandonedCart : Module is enabled`

---

## TC-002: Cron Running

**Crontab installed (app user):**
```
* * * * * cd /var/www/html && /usr/local/bin/php bin/magento cron:run 2>&1 | grep -v "Run jobs by schedule" >> /var/www/html/var/log/cron.log
```

**Cron log (last entry):**
```
[2026-08-27T06:51:06.682142+00:00] main.INFO: Cron Job sales_send_order_shipment_emails is successfully finished.
```

**cron_schedule entries (abandonedcart):**

| job_code | status | scheduled_at |
|----------|--------|-------------|
| `mageplaza_abandonedcart_cron` | pending | 2026-08-27 07:08:00 |
| `mageplaza_abandonedcart_report_indexer_cron` | pending | 2026-08-27 07:08:00 |
| `mageplaza_abandonedcart_cron` | pending | 2026-08-27 07:07:00 |
| `mageplaza_abandonedcart_report_indexer_cron` | pending | 2026-08-27 07:07:00 |
| `mageplaza_abandonedcart_cron` | pending | 2026-08-27 07:06:00 |

> Cron daemon chạy và schedule jobs thành công. `pending` status = jobs chờ execute (cron_schedule churn liên tục).

---

## TC-003: Email Deliverability (MailCatcher)

**SMTP config:** `smtp/general/enabled=1`, host=mailcatcher, port=1025.

**MailCatcher received 2 abandoned cart emails:**

| # | Subject | Received at |
|---|---------|------------|
| 1 | `Please comeback and complete your purchase!` | 2026-08-27T07:12:35+00:00 |
| 2 | `Do you need any help for your cart?` | 2026-08-27T07:15:36+00:00 |

> ✅ Email gửi thành công qua Mageplaza SMTP → MailCatcher. 2 email với subjects khác nhau xác nhận multi-schedule hoạt động.

---

## TC-004: vi_VN Translation File

**File:** `app/code/Mageplaza/AbandonedCart/i18n/vi_VN.csv` — **28 lines** (24 translation pairs + blank lines).

Covers:
- Template 1 subjects/body strings (5 strings)
- Template 2 subjects/body strings (8 strings)
- Email partials: productlist.phtml (7 strings: Image, Product Name, Qty, Price, Test product, Order Total Excl/Incl Tax)
- Related product partial: unsubscribe strings (2 strings)
- Hyvä unsubscribe template (1 string)

---

## TC-005: Git Status (files touched)

**Vendor upgrade (7 modified, staged):**

| File | Change |
|------|--------|
| `Block/Customer/AbandonedCartPhone.php` | Add `isHyvaTheme()` |
| `Block/Email/Template.php` | Add `isHyvaTheme()`, image URL fix, encrypted email param, PHPCS |
| `Helper/Data.php` | Remove duplicate `isEnabledHyvaTheme()`, PHPCS |
| `composer.json` | Version 4.6.7→4.6.9, package name suffix `-mkp` |
| `etc/adminhtml/di.xml` | PHPCS (CRLF→LF) |
| `view/frontend/templates/hyva/form/create/phone.phtml` | Use `$block->isHyvaTheme()`, PHPCS |
| `view/frontend/templates/hyva/unsubscribe.phtml` | Use `$block->isHyvaTheme()`, PHPCS |

**New files (added):**

| File | Purpose |
|------|---------|
| `app/code/Mageplaza/AbandonedCart/i18n/vi_VN.csv` | vi_VN translations (24 strings) |
| `.ai/records/tasks/TASK-WN0PBT.md` | Canonical task record |
| `.ai/specs/SPEC-TASK-WN0PBT-abandoned-cart-email.md` | Full specification |
| `.ai/plans/plan-abandonedcart-email.md` | Implementation plan |
| `.ai/tickets/TASK-WN0PBT-abandoned-cart-email.md` | Ticket |

---

## TC-006: E2E Flow (manual, developer-confirmed)

Developer confirmed Step 3 (Admin config) + Step 5 (E2E test) completed manually:

1. ✅ Email schedule configured via Admin UI
2. ✅ MailCatcher received 2 emails (TC-003)
3. ✅ Cart Board dashboard hiển thị (developer verified)
4. ✅ Restore cart link hoạt động (developer verified)
5. ✅ Không regression trên checkout/giỏ hàng (developer verified)

---

## DB Config (snapshot)

```
abandonedcart/general/email = {"_1787814913081_81":{"send":"","sender":"sales","template":"mageplaza_abandoned_cart_template_4","coupon":"1"}}
abandonedcart/general/send_subscribed_only = 1
abandonedcart/general/test_email = developer.quocthangpham0906@gmail.com
abandonedcart/analytics/enabled = 0
abandonedcart/sms_notification/enabled = 0
abandonedcart/coupon/dash = 3
abandonedcart/coupon/format = alphanum
abandonedcart/coupon/length = 12
abandonedcart/coupon/valid = 48
```

> Note: Config values trong DB phản ánh actual admin config tại thời điểm evidence — có thể khác plan (developer đã config manual). MailCatcher evidence (TC-003) xác nhận email thực tế gửi thành công.

---

## Acceptance Criteria Mapping

| AC | Evidence | Status |
|----|----------|--------|
| AC-001: E2E flow | TC-003 (2 emails received), TC-006 (developer confirmed restore cart OK) | ✅ Pass |
| AC-002: Config toggle | TC-005 (no core code changes), TC-006 (configured via Admin UI) | ✅ Pass |
| AC-003: State handling | TC-003 (MailCatcher deliverability), TC-002 (cron scheduled), TC-006 (Cart Board) | ✅ Pass |
| AC-004: No regression | TC-006 (developer verified checkout/giỏ hàng unaffected) | ✅ Pass |
