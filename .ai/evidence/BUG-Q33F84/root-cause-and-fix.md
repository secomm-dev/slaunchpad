# BUG-Q33F84 — Evidence: RMA footer links không hiển thị trên Hyvä footer

Ngày: 2026-10-05 · Mode C · Theme `Secomm/launchpad` (Hyvä 3.x) + Mageplaza_RMA 4.1.1

## Root cause chain (verified qua vendor source)

1. Vendor neo footer link vào block `footer-static-links`:
   `app/code/Mageplaza/RMA/view/frontend/layout/hyva_default.xml:43-58`
   (`referenceBlock` + setTemplate `hyva/html/footer/links.phtml` + 2 block con
   `mprma_footer_link`, `mprma_policy_footer_link`, `ifconfig="mprma/general/enabled"`).
2. Block `footer-static-links` được định nghĩa bởi Hyvä default theme:
   `vendor/hyva-themes/magento2-default-theme/Magento_Theme/layout/default.xml:99`.
3. Theme dự án remove nó cho footer CMS (SLP-275 / TASK-7EYJ4C):
   `app/design/frontend/Secomm/launchpad/Magento_Theme/layout/default.xml:54`.
   → subtree chết theo parent → 2 link không bao giờ render. Không phải lỗi config.
4. Gating config từng link (giữ nguyên, không đổi):
   - Request: `Block/Link/Request/Footer.php::_toHtml()` — `enabled` + `location` chứa
     `FOOTER_LINK(2)` + guest (`enabled_guest`) — `app/code/Mageplaza/RMA/Block/Link/Request/Footer.php:59-70`.
   - Policy: `Block/Link/Policy/Footer.php::_toHtml()` — `getPolicyLink(FOOTER_LINK)` =
     `policy` đã chọn AND `policy_location` chứa Footer; không guest check
     (`app/code/Mageplaza/RMA/Helper/Data.php:1001-1011`).

## Cơ chế fix (move-before-remove) — verified

- `move`/`remove` đều chỉ *schedule* lúc đọc layout XML:
  - `vendor/magento/framework/View/Layout/Reader/Move.php` → `setElementToMove()`.
  - `vendor/magento/framework/View/Layout/Reader/Block.php:229-231` → `setElementToRemoveList()`.
- Thực thi lúc generation, theo thứ tự cố định trong
  `vendor/magento/framework/View/Layout/GeneratorPool.php:buildStructure()`:
  dòng 134-136 moves **trước** dòng 137-139 removes. `removeElement` chỉ xóa đệ quy các con
  còn lại (dòng 201-216) → block đã move sang `footer-content` sống sót.
- Handle order: `Hyva\Theme\Observer\AddLayoutHandles` dùng `addHandle("hyva_$handle")`
  (append SAU handle gốc) — không ảnh hưởng kết quả vì move/remove đều chạy lúc generation.

## Files thay đổi (đề xuất review)

| File | Loại | Nội dung |
|------|------|----------|
| `app/design/frontend/Secomm/launchpad/Mageplaza_RMA/layout/hyva_default.xml` | mới | 2 `<move>` vào `footer-content` + 2 `referenceBlock` setTemplate `Mageplaza_RMA::footer/link.phtml` |
| `app/design/frontend/Secomm/launchpad/Mageplaza_RMA/templates/footer/link.phtml` | mới | `<ul><li class="py-1.5"><a>` — thay markup `<li class="nav item">` của `Html\Link\Current`; escapeUrl/escapeHtml + aria-current |
| `app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/footer.phtml` | +2 dòng | `getChildHtml('mprma_footer_link')` + `getChildHtml('mprma_policy_footer_link')` trong section links |
| `app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv` / `en_US.csv` | +2 dòng/csv | "RMA Request" + "RMA Terms And Conditions" |

## Pending verification (chờ user/QC — session AI không chạy được docker)

```
! ./docker-compose exec -T deploy php bin/magento cache:flush
```
+ theme rebuild: `npm run build` trong `app/design/frontend/Secomm/launchpad/web/tailwind/`
+ config check: `config:show mprma/general/location` (chứa 2), `mprma/general/policy`,
  `mprma/general/policy_location`, `mprma/general/enabled_guest`.

Checklist: AC-1..AC-5 trong record `.ai/records/bugs/BUG-Q33F84.md`.

## Known trade-offs

1. Phụ thuộc tên block vendor (`mprma_footer_link`, `mprma_policy_footer_link`) — Mageplaza
   upgrade đổi tên → move no-op silent → link biến mất (QC bằng sự vắng mặt). Ghi trong
   layout comment.
2. Dựa vào thứ tự move-before-remove của core Magento layout engine (ổn định nhiều version,
   Luma cũng phụ thuộc) — rủi ro thấp.
3. Phương án bị loại: khôi phục `footer-static-links` (template vendor hard-code English phá
   footer CMS); tạo block theme-owned tên riêng `launchpad.*` (đã làm trước đó, user chọn
   move cho gọn — vẫn là phương án dự phòng nếu sau này muốn cắt coupling vendor-name).