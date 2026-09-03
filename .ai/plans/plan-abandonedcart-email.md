# Plan: TASK-WN0PBT — Abandoned Cart Email

Record: [TASK-WN0PBT](../records/tasks/TASK-WN0PBT.md)
Spec: Embedded Mini-Spec (VALID)
Mode: C

---

## Step 0 — Commit vendor upgrade (4.6.7→4.6.9)

7 files đã staged trên branch. Bạn commit:
```
git commit -m "chore(abandonedcart): update to v4.6.9 with Hyvä compat refactor"
```

## Step 1 — Verify DB schema

```bash
bin/root bin/magento setup:upgrade --keep-generated --no-interaction
bin/mysql -e "SHOW TABLES LIKE 'mageplaza_abandonedcart%';"
```
Expect: `mageplaza_abandonedcart_logs`, `mageplaza_abandonedcart_logs_token`, `mageplaza_abandonedcart_reports_index`, `mageplaza_abandonedcart_product_reports_index`

## Step 2 — Bật Magento cron trong Docker

Cron **chưa chạy** — blocker chính.

```bash
# Cài crontab (mỗi phút chạy Magento cron)
bin/root bash -c 'echo "* * * * * cd /var/www/html && php bin/magento cron:run 2>&1 | grep -v \"Run jobs by schedule\" >> /var/www/html/var/log/cron.log" | crontab -u app -'

# Start cron daemon
bin/cron start
bin/cron status  # expect: "cron is running"
```

> Dev-only — mất khi container rebuild. Production cần crontab riêng.

## Step 3 — Configure email schedule (Admin UI)

**Stores > Configuration > Mageplaza > Abandoned Cart**

### General tab:
| Field | Value |
|-------|-------|
| enabled | Yes |
| send_email_recover | Yes |
| stop_sending_email | Yes |
| enable_unsubscribe_link | No |
| send_subscribed_only | No |
| **Email Schedule Row 1** | Send: `1h`, Template: `Abandoned Cart Email 1`, Coupon: No |
| **Email Schedule Row 2** | Send: `24h`, Template: `Abandoned Cart Email 2`, Coupon: No |
| test_email | nhập email test → Send Test |

### Report tab:
| Field | Value |
|-------|-------|
| time_measure | 5 |
| cart_board | Yes |

Sau khi config:
```bash
bin/cli bin/magento cache:flush
```

## Step 4 — Localize vi_VN

Tạo `app/code/Mageplaza/AbandonedCart/i18n/vi_VN.csv` — translation cho strings trong template 1+2.
Templates dùng `{{trans}}` directive nên Magento tự fallback theo locale.

## Step 5 — Test end-to-end

### 5a. Smoke test
- Admin > Abandoned Cart > General > Test Email → Send
- Mở http://localhost:1080 (MailCatcher) → verify email nhận được

### 5b. Full E2E
1. Tạo test customer, đăng nhập frontend
2. Thêm product vào giỏ, **không checkout**
3. Chờ > time_measure (5 phút) + schedule row 1 (1 giờ)
   - **Test nhanh**: tạm set time_measure=1, schedule row 1=`2m`
4. Verify email #1 trong MailCatcher
5. Click restore cart link → verify giỏ khôi phục đúng
6. Verify stop_sending_email: không nhận thêm email sau khi cart recovered

### 5c. Admin reports
- Marketing > Abandoned Cart > Cart Board → verify cart xuất hiện
- Marketing > Abandoned Cart > Abandoned Carts → verify log entries

## Step 6 — Revert test shortcuts

- `time_measure` = 5 (production-ready)
- Schedule: 1h + 24h (production-ready)

---

## Files

| Action | File |
|--------|------|
| Staged (Step 0) | `app/code/Mageplaza/AbandonedCart/` (7 files) |
| Create (Step 4) | `app/code/Mageplaza/AbandonedCart/i18n/vi_VN.csv` |
| Config (DB, Step 3) | `core_config_data` — Admin UI |
| Config (container, Step 2) | Crontab trong phpfpm |