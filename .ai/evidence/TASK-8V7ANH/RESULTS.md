# TASK-8V7ANH / SLP-216 — Evidence Results

> Chỉ sửa 2 theme i18n CSV: thêm row `"Track your order","Theo dõi đơn hàng"` vào
> `vi_VN.csv` (sau `"Order tracking"`) + mirror identity vào `en_US.csv`. Không đụng
> vendor/template/layout. Tất cả check dưới chạy sau khi thêm row, trước/sau `cache:flush`.

## Change

```bash
git diff --stat -- app/design/frontend/Secomm/launchpad/i18n/
#  en_US.csv | 1 +   vi_VN.csv | 1 +
```

Row mới nằm cạnh cụm Magento_Shipping popup (`Track:`, `Info:`, …, `Order tracking`).

## AC-001 — Framework-level (dictionary + render thật, per-store)

`verify-dictionary.php` (pattern BUG-GJT6C1 / LL-0007: setAreaCode frontend → wire
Phrase renderer → `Translate::loadData` force, 1 process/store). Render-level = block
`Magento\Shipping\Block\Tracking\Link` + template `Magento_Shipping::tracking/link.phtml`
thật, label set như layout argument (`new Phrase('Track your order')`), order fixture
từ DB.

```
[default] 1.dict_phrase      PASS  rendered=Theo dõi đơn hàng
[default] 2.render_block     PASS  contains-expected=true
[default] 3.render_no_other  PASS  other-locale-absent=true
[default] RESULT             3 pass / 0 fail
[launchpad_en] 1.dict_phrase      PASS  rendered=Track your order
[launchpad_en] 2.render_block     PASS  contains-expected=true
[launchpad_en] 3.render_no_other  PASS  other-locale-absent=true
[launchpad_en] RESULT             3 pass / 0 fail
```

Snippets: `render-default.html`, `render-launchpad_en.html`.

## AC-002 — Live vi_VN (order view, customer session)

Chuỗi nguồn của ticket nằm trong block `tracking-info-link`
(`Magento_Shipping/layout/sales_order_view.xml:13`, label argument `translate="true"`
→ `TranslateDecorator` wrap `Phrase` → theme dictionary).

**Điều kiện render:** Hyvä `Magento_Sales/templates/order/view.phtml:52-54` chỉ echo
`getChildHtml('tracking-info-link')` khi `$order->getTracksCollection()` non-empty —
order test local chưa có track → tạo track fixture (order 5 / shipment 3, customer 3,
`track-fixtures.php`), verify xong đã **delete** (track_id=1). Lưu ý QC: page này chỉ
hiện link tracking khi order có tracking record.

```
session: server-side (create-session.php, customer 3 luuk_berg@example.com — QC-only
pattern, không cần password) + curl --resolve slaunchpad.localhost:80:127.0.0.1
```

```
order view vi HTTP 200
[PASS] expected 'Theo dõi đơn hàng' occurrences=2
[PASS] English 'Track your order' occurrences=0 (expect 0)
CTX: …shipping/tracking/popup?hash=…" class="underline" target="_blank"
     title="Theo dõi đơn hàng"> Theo dõi đơn hàng</a>
html lang = vi
```

Snapshot: `order-view-vi.html`. occurrences=2 = text link + `title` attr (cả hai qua
`escapeHtml(Phrase)` → dictionary).

## AC-003 — Live en_US

`?___store=launchpad_en` (switch hoạt động trên order view — khác quirk LL-0011 ở
checkout OSC):

```
order view en HTTP 200
html lang = en
EN identity occurrences: 2
vi occurrences: 0
```

Snapshot: `order-view-en.html`.

## Cache

`sudo -u secomm php bin/magento cache:flush` trước khi fetch live.

## Logs / runs

- 2026-09-14 — tất cả check PASS; track fixture đã dọn; không regression phát hiện thấy.
- Live en trước fix không có baseline riêng (chỉ identity PASS sau fix — identity row
  trong en_US.csv giữ nguyên hành vi vì Translate skip identity pairs theo design, LL-0005).
