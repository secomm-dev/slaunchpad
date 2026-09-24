# Hyvä Custom Icon Workflow — Figma to Theme

Tài liệu này định nghĩa workflow dùng chung toàn site để đưa custom UI icons từ
Figma vào Magento Hyvä child theme. Contract này chỉ áp dụng cho Hyvä storefront;
Luma và headless storefront cần integration contract riêng.

## 1. Source và authority

- Mỗi project/theme khai báo Figma icon-library node trong Global Style input.
- Component instance quyết định glyph, style, direction và rendered size cần dùng.
- Icon library cung cấp canonical vector source và semantic name.
- Final component instance thắng khi library name và glyph conflict; Designer
  phải xác nhận, FE không tự thay bằng glyph gần giống.
- Figma asset URL chỉ là retrieval evidence có thời hạn, không dùng lúc runtime.

Secomm Launchpad Core hiện dùng:

- Figma file `LAUNCHPAD-CORE` (`5MpBw9VaNgFdct3UDWABuV`).
- Icon library [`5:28677`](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=5-28677&m=dev).
- Hai outer frame cùng tên `Fill Icon Sets` là naming lỗi ở presentation layer,
  không phải hai fill libraries:
  - `2001:10642` chứa collection `fill-icon` (`2001:10644`).
  - `2001:13716` chứa collection `outline-icon` (`2001:13718`).
- Cả hai collection dùng semantic kebab-case và cung cấp size
  `12/16/24/32/40`; directional glyphs còn có `Down/Right/Up/Left`.

## 2. On-demand policy

Không export toàn bộ icon library trong Global Style mặc định. Với mỗi
component/block:

1. Đọc design context của component node.
2. Inventory icon instance trong các state/breakpoint thuộc scope.
3. Match instance với semantic component trong icon library.
4. Reuse SVG trong theme chỉ khi semantic name và glyph đều khớp.
5. Export, audit và thêm duy nhất icon còn thiếu.

Icon của state, popup hoặc component chưa được cung cấp vẫn out of scope. Chỉ
export toàn bộ library khi có work item riêng cho versioned icon package và
automated sync pipeline.

## 3. Naming và storage

Giữ semantic name/style từ Figma, normalize filename về lowercase kebab-case:

```text
shopping-cart-outline / Size = 24
    → Hyva_Theme/web/svg/outline/shopping-cart-24.svg
    → renderHtml('outline/shopping-cart-24')

search-fill / uniform geometry
    → Hyva_Theme/web/svg/fill/search.svg
    → renderHtml('fill/search')

arrow-outline / Size = 24, Direction = Right
    → Hyva_Theme/web/svg/outline/arrow-right-24.svg
    → renderHtml('outline/arrow-right-24')
```

Path chuẩn là `Hyva_Theme/web/svg/{style}/{semantic-name}.svg`; khi optical
variant đã được chứng minh, dùng
`Hyva_Theme/web/svg/{style}/{semantic-name}-{size}.svg`. Bỏ suffix style
`-fill`/`-outline` khỏi semantic filename vì style đã nằm trong directory; đưa
direction vào semantic filename. Path ổn định cho phép child theme override bằng
Magento theme fallback. Không thêm theme name vào filename.

## 4. Export rules

- Ưu tiên icon instance đang được component dùng rồi đối chiếu semantic name.
- Request SVG; không dùng PNG/screenshot hoặc export cả parent frame.
- Nếu asset chứa background/layout, lấy exact vector của instance và giữ path data.
- Không redraw hoặc thay bằng glyph tương tự.
- Dùng canonical 24px source cho glyph có normalized geometry đồng nhất; viewBox
  nhỏ hơn được chấp nhận khi đó là canonical artwork của glyph, ví dụ chevron.

## 5. Size policy

- Nếu normalized geometry chỉ khác uniform scaling, giữ một SVG và truyền
  `width`/`height` khi render.
- Chỉ giữ size-specific SVG khi path proportion, stroke, detail, whitespace hoặc
  optical center thực sự khác.
- Mọi size-specific exception cần evidence và chỉ asset ngoại lệ mới có suffix.
- Audit Launchpad Core xác nhận fill representative `academic-cap-fill` là
  uniform scaling giữa 12/24/40, nên dùng một canonical SVG.
- Audit Launchpad Core xác nhận outline representative `user-circle-outline` có
  optical stroke theo size: 1px@12, 1.5px@16, 2px@24/32 và 2.5px@40. Vì vậy
  outline icon phải lấy đúng designed-size asset và dùng suffix size, trừ khi
  audit glyph cụ thể chứng minh các variant tương đương.

## 6. SVG audit và normalization

- Giữ exact path data, `viewBox`, `fill-rule`, `clip-rule`, stroke caps/joins và
  intentional whitespace.
- Loại Figma frame background, debug outline, metadata, unnecessary ID/filter.
- Reject script, event handler, external reference, embedded raster data và
  unexpected foreign content.
- Monochrome foreground `fill`/`stroke` dùng `currentColor`; không bake semantic
  color token vào SVG.
- Multi-color brand/payment artwork giữ intended colors và được phân loại riêng.
- Decorative icon không thêm `<title>` trùng lặp; containing control sở hữu name.

## 7. Hyvä integration

```php
use Hyva\Theme\ViewModel\SvgIcons;

$icons = $viewModels->require(SvgIcons::class);

echo $icons->renderHtml(
    'outline/shopping-cart-24',
    '',
    24,
    24,
    ['aria-hidden' => 'true']
);
```

- Render UI icons inline qua `SvgIcons`; không dùng icon font hoặc Figma URL.
- Truyền explicit width/height tại usage.
- Decorative icon trong labelled control dùng `aria-hidden="true"`.
- Standalone informative icon lấy accessible name từ surrounding markup.
- Giữ nguyên Hyvä/Magento interaction, private-content, Alpine và ARIA behavior.
- Chỉ override vendor template khi không có theme-owned seam nhỏ hơn.

## 8. Traceability

Traceability đến từ source node trong input, guide này, semantic asset path, code
search `renderHtml('style/name')`, component implementation và visual evidence.

Không cần manifest khi mapping 1:1 rõ ràng. Chỉ tạo manifest cho alias, rename,
deprecation, nhiều source library hoặc automated sync.

## 9. Component completion checklist

- Mọi visible icon map được tới Figma semantic component.
- Chỉ icon cần thiết được thêm; SVG source/canonical box đã audit.
- Monochrome color dùng `currentColor`; dimensions khớp design.
- Accessible name, control semantics và target size không bị thay đổi.
- Visual QA bao phủ supplied breakpoint/state; build và runtime checks pass.

## 10. Global Style completion boundary

Global Style sở hữu workflow, naming/storage contract, security/accessibility
rules và source inventory. Global Style không đồng nghĩa với copy toàn bộ library.
Component work item chọn và thêm icon nó thực sự tiêu thụ theo contract này.
