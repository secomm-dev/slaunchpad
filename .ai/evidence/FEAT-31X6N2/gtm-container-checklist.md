# GTM Container Config Checklist — FEAT-31X6N2

> Config-side (marketer/TL làm trong GTM account — KHÔNG phải code). Browser events
> của Secomm_Tracking đẩy object `launchpad_event` vào `window.dataLayer`; GTM tags đọc
> từ đó. Magefan GTM loader đã gắn container — chỉ cần config trong GTM UI.

## 1. Built-in / variables cần tạo

| Variable | Type | Source |
|---|---|---|
| `LE Event` | Data Layer Variable | `launchpad_event.event` |
| `LE Event ID` | Data Layer Variable | `launchpad_event.event_id` |
| `LE Value` | Data Layer Variable | `launchpad_event.value` |
| `LE Currency` | Data Layer Variable | `launchpad_event.currency` |
| `LE Order ID` | Data Layer Variable | `launchpad_event.order_id` |
| `LE Consent Marketing` | Data Layer Variable | `launchpad_event.consent.marketing` |
| `LE FBP` | Data Layer Variable | `launchpad_event.user.fbp` |
| `LE FBC` | Data Layer Variable | `launchpad_event.user.fbc` |
| `LE TTCLID` | Data Layer Variable | `launchpad_event.user.ttclid` |
| `LE TTP` | Data Layer Variable | `launchpad_event.user.ttp` |

## 2. Trigger

| Trigger | Type | Condition |
|---|---|---|
| `Launchpad Event` | Custom Event | Event name = `launchpad_event` (mỗi push là 1 custom event GTM) |

Lưu ý: block push `{ launchpad_event: event }` → GTM thấy custom event tên `launchpad_event`,
payload nằm dưới key `launchpad_event.*`. Tags đọc variables ở trên.

## 3. GA4 tag

- Tag: **Google Analytics: GA4 Event** × mỗi event (view_item, view_category→view_item_list,
  search, add_to_cart, begin_checkout, purchase)
- Measure protocol không dùng; purchase lấy `transaction_id` = `{{LE Order ID}}`,
  `value` = `{{LE Value}}`, `currency` = `{{LE Currency}}`
- Đề xuất dùng sẵn dataLayer của Magefan cho GA4 nếu đang hoạt động — chỉ cần
  `launchpad_event` cho Meta/TikTok (tránh double GA4 tag; chọn MỘT nguồn).

## 4. Meta Pixel tag

- Tag: **Facebook Pixel** (custom event) hoặc Community template "Meta Pixel"
- Pixel ID = số Pixel trong config Magento
- Event name map: purchase→`Purchase`, view_item→`ViewContent`, add_to_cart→`AddToCart`,
  begin_checkout→`InitiateCheckout`
- **eventID = `{{LE Event ID}}`** ← bắt buộc để dedup với CAPI (đây là điểm quan trọng nhất)
- User data: `externalID`? — Phase 1 bỏ qua; fbp/fbc tự đọc cookie bởi pixel
- Condition: fire khi `{{LE Consent Marketing}}` = true

## 5. TikTok Pixel tag

- Tag: TikTok Pixel custom event
- Pixel ID = số Pixel TikTok trong config Magento
- Event map: purchase→`CompletePayment`, view_item→`ViewContent`, add_to_cart→`AddToCart`,
  begin_checkout→`InitiateCheckout`
- **event_id = `{{LE Event ID}}`** ← dedup với Events API
- Properties: value/currency/contents từ `launchpad_event`
- Condition: fire khi `{{LE Consent Marketing}}` = true

## 6. Verify

1. GTM Preview mode → storefront walk (PDP→cart→OSC) → thấy từng launchpad_event fire đúng tag
2. Meta Test Events: browser Purchase + server Purchase → **Deduplication: deduplicated**
3. TikTok Event Debug: browser + server CompletePayment cùng event_id
4. GA4 DebugView: purchase 1 dòng, không double

## 7. Lỗi thường gặp

| Triệu chứng | Nguyên nhân | Fix |
|---|---|---|
| Meta double count | eventID tag khác server | kiểm tra `{{LE Event ID}}` = `purchase-{increment_id}` |
| Tag không fire | consent false | Cookie Restriction chưa accept hoặc condition sai |
| view_category miss | FPC cache cũ | flush FPC; event_id per-day nên tag vẫn dedup đúng |
| GA4 double | dùng cả Magefan dataLayer tag + launchpad_event tag | chọn 1 nguồn cho GA4 |
