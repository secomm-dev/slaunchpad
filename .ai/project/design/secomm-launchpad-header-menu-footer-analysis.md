# Secomm Launchpad — Header, Menu and Footer Design Analysis

Status: `Header approved for implementation under TASK-DWW34M; Menu plan revised 2026-09-29 (proposed, non-executable); Footer remains analysis and estimate only`

External references: Header `SLP-246`; Menu `SLP-245`

Related foundation: [Global Style input](secomm-launchpad-global-style-foundation-input.md)

Design file: `LAUNCHPAD-CORE` (`5MpBw9VaNgFdct3UDWABuV`)

This document records verified Figma structure, current Magento/Hyvä integration
surfaces, proposed component boundaries and implementation estimates. It does
not move Header, Menu or Footer into the Global Style scope and does not
authorize implementation.

## 1. Source nodes

### Header

| View | Canonical node | Verified structure |
|---|---|---|
| Desktop | `2151:9517` — Header component set | Two 1440×64 contextual variants; Figma models 400px side regions, but the approved responsive contract uses equal flexible regions around a centered 120×18 logo |
| Mobile/Tablet | `2115:5849` — Header component set | Mobile 375×64 with 8px outer padding; Tablet 640×64 with 32px outer padding; menu/logo left and Search/Wishlist/Cart right |

The component set models light/dark visual treatment, but it does not define the
runtime trigger between the two treatments. Tablet light coverage is not visibly
complete in the component-set overview.

### Menu

Nguồn hiện hành từ 2026-09-29: [page Mega menu — 2949:93615](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2949-93615).
Đã đọc page metadata, screenshot tổng và design context/screenshot riêng của Desktop và cả ba Mobile states. Page được tách riêng nhưng Desktop và Mobile component set vẫn giữ ID cũ. Kế hoạch cập nhật tại §7 thay thế các giả định Menu cũ.

| View | Canonical node | Verified structure |
|---|---|---|
| Desktop trigger/navigation | Within `2151:9517` | `SHOP` plus three dropdown labels, 24px item gap, Label M typography |
| Desktop mega menu | `2151:11236` | 1440px full-width panel; 40/32px outer padding; feature image/copy plus three navigation regions separated by dividers |
| Mobile component set | `2117:5971` | Ba states rộng 375px; root cập nhật cao 1177px, hai nested states lần đọc trước cao 812px; không có frame Tablet 640px |
| Mobile root | `2115:5380` | Logo/close + search; root links; Settings gồm Language (English/Vietnamese) và My Account; không Currency |
| Mobile level 1 | `2949:77708` | Logo/close + search; back row; collapsed child groups; feature image |
| Mobile level 2 | `2949:93394` | Giữ back row; một child group mở inline, leaf links thụt lề; feature image phía dưới |

Desktop selected parent items use a semantic brand-soft surface. Mobile dùng chuyển vào nhóm ở cấp đầu kết hợp accordion inline ở cấp tiếp theo; không phải accordion đệ quy cho mọi cấp. Tablet là responsive extension cần chốt trong spec (§7).

### Footer

| Region | Desktop | Mobile | Runtime decision |
|---|---|---|---|
| Newsletter | `2151:17272` | `2151:17058` | Visible on both; horizontal Desktop, stacked Mobile |
| Main links | `2151:17280` | `2151:17066` | Four Desktop columns; four-section Mobile accordion |
| Payment | `2174:34539` | none | Visible Desktop; hidden Mobile |
| Bottom | `2151:17305` | `2151:17095` | Horizontal Desktop; centered stack Mobile |

Verified Footer details:

- Desktop main links: four equal columns, 40px horizontal and 32px vertical
  padding, 24px heading-to-list gap and 12px link gap.
- Mobile main links: 24px horizontal/32px vertical padding, one representative
  section open, section dividers and 20px chevrons.
- Desktop Payment contains one accreditation image and eight 48×32/35px payment
  marks with 16px gaps.
- Bottom Footer uses Body 3; legal links have a 16px gap. Copyright year should be
  runtime-derived rather than copied as a fixed `2026` value.

## 2. Current implementation surface

> Updated outcome 2026-10-05: both desktop and mobile consume the single
> `hyva-topmenu-desktop` Snowdog hierarchy through separate theme templates.
> Desktop level-1 current/hover/open indicators are confirmed against Figma
> `2151:9582`: gray-solid on the light header, white on contextual headers,
> and exact-current category links expose `aria-current="page"`.
> Follow-up 2026-10-05: desktop nested hover/current rows reuse the level-1
> underline while click-selected/open parents retain brand-soft. Mobile
> current/open rows at every rendered depth use the Figma `2949:77725`
> Back-row brand surface and medium weight; current rows add 8px start padding.
> Statements below describing two active identifiers or vendor-default menu
> templates are the pre-implementation baseline.

- Cập nhật 2026-09-29: theme `Secomm/launchpad` đã có Header override và Snowdog Desktop level-1 override theo TASK-DWW34M. Desktop submenu và Mobile drawer vẫn dùng template Snowdog mặc định.
- `app/code/Secomm/ThemeHelper` đã tồn tại và cung cấp sticky config; tái sử dụng foundation này.
- Hyvä already supplies Logo, Search, Customer, Wishlist, Minicart/Cart Drawer,
  store/language switchers, Newsletter and Footer block contracts. These should
  be composed or overridden, not reimplemented as static markup.
- `Snowdog_Menu` is installed and its `default_hyva.xml` replaces Hyvä's native
  desktop/mobile menu with `hyva-topmenu-desktop` and `hyva-topmenu-mobile`.
- Snowdog also exposes `hyva-menu-footer`; the shipped templates and Tailwind
  source are generic and require theme-local overrides for this design.
- Snowdog menu content can use Magento category nodes, so catalog URLs and labels
  remain runtime data rather than copied Figma text.
- Existing Snowdog templates already include Alpine focus trapping, Escape
  handling and body scroll locking. Adapt these behaviors rather than discarding
  them when applying the design.

## 3. Recommended boundaries

### Header

- One semantic `<header>` shell with responsive composition.
- Keep Magento/Hyvä child blocks for logo, search, customer/session, wishlist,
  minicart and language data.
- Homepage initially uses the dark/on-hero treatment. Other pages initially use
  the light treatment. On scroll, the Header becomes sticky like the approved
  POC; the Homepage transitions to the light sticky treatment.
- Sticky behavior is controlled by a Magento Admin Yes/No setting at Store View
  scope. The proposed path is **Stores → Configuration → Secomm → Theme → Header
  → Enable Sticky Header**, backed by `secomm_theme/header/sticky_enabled` and
  enabled by default to match the approved design.
- Magento-side theme configuration belongs to `Secomm_ThemeHelper`. The module
  may later host other reusable theme configuration/providers and PHP helpers,
  but must not own presentation markup or CSS. Theme templates consume a typed
  config provider/ViewModel; they must not call ObjectManager or config storage
  directly.
- Customer/account is rendered next to Wishlist even though the current design
  omitted it; Design will add the missing visual reference later.
- Header owns contextual visual treatment and sticky behavior; Menu owns menu
  hierarchy/panels/drawer.
- Use exact Figma SVG assets through the Hyvä custom-icon workflow. Do not draw
  or substitute glyphs based only on icon names.

### Menu

- Snowdog Admin remains the runtime authoring source.
- Magento catalog categories remain the source for category nodes and URLs.
- The feature image/copy and other mega-menu managed content are authored in
  Snowdog rather than hard-coded into the theme.
- Theme-local Snowdog templates render the Desktop mega menu and Mobile/Tablet
  drawer from the same managed hierarchy while allowing device-specific layout.
- Desktop uses the approved hybrid activation contract: hover is a pointer
  enhancement; click, Enter and Space provide deterministic toggle behavior.
  The panel closes on delayed mouseleave, outside click or Escape. Hover is not
  the only activation mechanism, and keyboard focus remains managed.

### Footer

- Magento Newsletter block owns form action, form key, validation and response.
- Footer content follows the approved POC architecture and is loaded from CMS
  blocks. Presentation and responsive behavior remain in theme templates; link
  labels and destinations are not hard-coded in PHTML.
- Payment/accreditation content should be a separately managed Footer region;
  render on Desktop only. Exact Figma assets must be exported and committed.
- Bottom copyright uses Magento runtime copyright/year; legal destinations are
  managed content, not hard-coded URLs.
- Use the column layout at `min-width: 768px`; below 768px use the accordion.

## 4. Design gaps and decisions before implementation

| ID | Decision/gap | Resolution / recommendation | Owner | Status |
|---|---|---|---|---|
| HMF-01 | What switches Header dark/light treatments and sticky state? | Homepage starts dark/on-hero; other pages start light. Scroll activates sticky Header like the POC; Homepage sticky treatment becomes light. | Designer/TL | `Resolved` |
| HMF-02 | Tablet light variant is not clearly represented | Apply the same semantic light/dark context contract at all responsive sizes; Design may add the missing visual reference later. | Designer | `Resolved with documented design gap` |
| HMF-03 | Header design omits a visible customer/account action | Render Customer/account next to Wishlist; Designer will update the source design later. | Designer/Product | `Resolved with documented design gap` |
| HMF-04 | What user action opens/closes the Desktop mega menu? | Approved hybrid contract: hover enhancement plus click/Enter/Space; close via delayed mouseleave, outside click or Escape. Hover is not the only trigger. | TL | `Resolved` |
| HMF-05 | Mapping between feature image/copy and menu data | Manage the feature content in Snowdog with the relevant menu hierarchy; do not hard-code Figma copy/assets in PHTML. | TL/Content | `Resolved` |
| HMF-06 | Footer link authoring source | Follow the POC: CMS blocks provide managed Footer content; theme templates own responsive presentation. | TL/Content | `Resolved` |
| HMF-07 | Breakpoint between Footer columns and accordion | Columns at 768px and above; accordion below 768px. | Designer/TL | `Resolved` |
| HMF-08 | Newsletter success/error/loading and invalid-email states are absent | Reuse Magento/Hyvä behavior and Global Style Form tokens; propose missing visuals under WCAG 2.2 AA. | Frontend Developer | `Open` |
| HMF-09 | Payment/accreditation alt text and destination behavior are absent | Classify decorative vs linked assets before export and implementation. | Content/Designer | `Open` |
| HMF-10 | How is sticky Header enabled or disabled? | Add Store View scoped Admin config `secomm_theme/header/sticky_enabled` in new reusable module `Secomm_ThemeHelper`; default enabled. | Tech Lead | `Resolved` |
| HMF-11 | Fixed 400px Desktop side regions do not scale with real menu/action content | Use `minmax(0,1fr) / 120px / minmax(0,1fr)` with 24px column gaps. Keep level 1 on one scrollable row with a hidden scrollbar; logo centering is invariant. | Frontend Developer/TL | `Approved refinement` |

## 5. Work-point estimate

Every point is independently loggable and is limited to 1–4 hours. Estimates
include implementation and point-level self-test, while the final QA points cover
cross-component/runtime validation.

### Header — 20–27 hours

| ID | Work point / deliverable | Estimate |
|---|---|---:|
| HD-01 | Map Hyvä layout blocks and create theme-local Header composition | 3h |
| HD-02 | Implement Desktop/Mobile/Tablet responsive shell and spacing | 3–4h |
| HD-03 | Export/integrate logo and Header custom icon assets | 2–3h |
| HD-04 | Integrate language, wishlist, cart/minicart and customer/session rules | 3–4h |
| HD-05 | Adapt Search trigger/overlay to the Header contract | 2–3h |
| HD-06 | Scaffold `Secomm_ThemeHelper`, add ACL/system/default config and typed sticky-config provider | 2–3h |
| HD-07 | Implement config-gated contextual/sticky treatment and transitions | 2–3h |
| HD-08 | Header responsive, config on/off, keyboard, screen-reader and locale QA | 3–4h |

### Menu — baseline cũ 22–29 hours (superseded bởi §7: 26–33h)

| ID | Work point / deliverable | Estimate |
|---|---|---:|
| MN-01 | Define Snowdog hierarchy/data contract using real catalog categories | 3–4h |
| MN-02 | Implement Desktop primary navigation trigger row | 2–3h |
| MN-03 | Implement Desktop full-width mega-menu visual composition | 4h |
| MN-04 | Implement approved hybrid Desktop activation, close and transition behavior | 2–3h |
| MN-05 | Implement Mobile/Tablet drawer shell and responsive sizing | 3–4h |
| MN-06 | Implement nested navigation, settings and language disclosure | 3–4h |
| MN-07 | Preserve focus trap, Escape, scroll lock and reduced motion | 2–3h |
| MN-08 | Validate category depths, empty/long labels, locales and devices | 3–4h |

### Footer — 18–25 hours

| ID | Work point / deliverable | Estimate |
|---|---|---:|
| FT-01 | Define CMS block identifiers/deployment contract and wire Footer regions | 2–3h |
| FT-02 | Implement Magento Newsletter Desktop/Mobile composition and states | 3–4h |
| FT-03 | Implement four-column Desktop CMS-managed link navigation | 2–3h |
| FT-04 | Implement Mobile accessible accordion using the same content source | 3–4h |
| FT-05 | Export/integrate Desktop accreditation and payment assets | 3–4h |
| FT-06 | Implement responsive copyright and managed legal links | 2–3h |
| FT-07 | Footer responsive, content, keyboard and locale QA | 3–4h |

### Estimate summary

| Scope | Estimate | Confidence |
|---|---:|---|
| Global Style Foundation | 43–61h | Medium until Select/Button gates close |
| Header | 20–27h | Medium |
| Menu | 26–33h | Medium-low; revised 2026-09-29, xem §7 |
| Footer | 18–25h | Medium |
| **Combined implementation** | **107–146h** | **Medium-low; tổng scope lịch sử, không phải effort còn lại** |

The combined range excludes content entry/translation ownership, catalog setup,
backend feature changes and unrelated local-environment repair. Re-baseline after
HMF-08, HMF-09 and the remaining Global Style Button/Select gates are approved.

## 6. Validation contract

- Use the Global Style locale, browser and viewport matrix.
- Validate Header/Menu together because focus, stacking, body scroll and sticky
  behavior cross component boundaries.
- Validate sticky Header with Admin config enabled and disabled at Store View
  scope, including config inheritance/fallback and cache refresh behavior.
- Validate Footer at 320/375/768/1024/1280/1440/1920px, including long localized
  labels and empty/extra managed link groups.
- Exercise logged-in/logged-out, wishlist enabled/disabled, cart empty/non-empty,
  one/multiple store languages and menu trees with two/three levels.
- Record Figma comparison evidence separately for 375px and 1440px canonical
  frames; intermediate widths are responsive contract validation, not invented
  pixel-perfect source frames.


## 7. Kế hoạch Menu cập nhật — 2026-09-29

| Field | Value |
|---|---|
| Status | Proposed / analysis only; chưa activate implementation |
| Specification | Chưa có Full Spec riêng cho Menu; §7 là đề xuất đầu vào để hoàn thiện spec, không thay thế spec VALID |
| Source | Page `2949:93615`; các node trong §1 |
| Stack | Magento + Hyvä, Snowdog Menu, Alpine.js, Tailwind v4 |
| Scope | Desktop mega panel + Mobile/Tablet drawer; kế thừa Header/level-1 đã có |

### 7.1 Thiết kế đã xác minh và thay đổi so với plan trước

> This planning snapshot is superseded by
> `SPEC-FEAT-ZNJ4KF-launchpad-menu.md` amendments §8–§10 and the implemented
> outcome recorded under TASK-08343C.

- **Desktop** `2151:11236`: frame 1440×537; padding ngang 40px/dọc 32px; gap 32px; feature 432px với ảnh 432×381 và Body 3 copy; navigation widths 300/300/166px, hai divider 1px. Nav Label XL 18/28 medium, item padding 8px, gap 8px, chevron 16px, selected nền brand-soft và radius 6px. Đây là geometry ở frame chuẩn, không hard-code toàn bộ widths cho mọi viewport.
- Hai cột đầu có parent chevron/selected state; cột cuối là leaf. **Đề xuất hành vi:** cột sau hiển thị children của selection cột trước; đổi selection phải reset nhánh sâu hơn. Screenshot chỉ chứng minh visual state, chưa chứng minh trigger, default selection hay cây runtime.
- **Mobile root** `2115:5380` (Settings revision): 375×1177, gutter 8px, top padding 24px. Logo slot 160px (visual khoảng 156.8×24), close button 44×44. Logo drawer khác slot 120px của Header ngoài trang. Search nằm ngay dưới top bar. Root rows Label L 16/24, khoảng 56px + gap 8px, divider; Settings/Language theo sau navigation.
- **Mobile level 1** `2949:77708`: giữ logo/close/search; back row có nền brand-100; danh sách nhóm con dùng chevron-down; feature image phía dưới, không có đoạn copy như Desktop.
- **Mobile level 2** `2949:93394`: mở một nhóm ngay trong level-1 view; leaf links thụt lề, chevron-up, image được đẩy xuống theo chiều cao nội dung. Đây là drill-down + accordion, không phải màn hình level 2 thay thế toàn bộ panel.
- Header drawer tách khỏi vùng scroll. Dùng viewport height động và vùng nội dung `min-height: 0`/scroll, không khóa runtime ở 812px hay cắt mất ảnh/link khi accordion mở. Kiểm tra bàn phím ảo và safe area.
- Page hiện tại có ba Mobile states rộng 375px; **không còn căn cứ dùng 640px như frame canonical**. Đề xuất tablet tiếp tục dùng cùng pattern cho đến breakpoint Desktop hiện tại; width/gutter cần ghi rõ trong spec.
- **Settings đã xác minh lại theo cập nhật người dùng:** Language mở, có English và Vietnamese với flags, current English gạch chân; My Account có user icon và chevron-down; Currency không còn trong context/screenshot. Language options runtime lấy từ store configuration. Chưa có thiết kế nội dung My Account khi mở; hướng xử lý xem §8.

### 7.2 Data contract và integration

1. Snowdog tiếp tục quản lý tree; category URL/label lấy từ Magento. Không dùng số lượng, nhãn `Label`/`Group Heading` hay furniture demo copy làm dữ liệu cố định.
2. Layout hiện tại gọi hai identifiers `hyva-topmenu-desktop` và `hyva-topmenu-mobile`. Mục tiêu cùng managed hierarchy giữ nguyên, nhưng cần chốt cách hai renderer cùng tiêu thụ một menu trước khi sửa layout/data. Không tự đồng bộ bằng hai bản nhập tay.
3. Xác định node đại diện feature image/copy và mapping theo nhánh; ưu tiên node/template/CMS block capabilities có sẵn. Mobile dùng cùng managed image theo contract nhưng không render Desktop copy. Không tạo schema/dependency mới nếu chưa có quyết định riêng.
4. Search trong drawer phải hoạt động qua Magento/Hyvä search contract hiện có: submit bằng Enter/button, label và ID riêng; không duplicate ID hoặc tạo nested form với Header search. Autocomplete chỉ reuse nếu có sẵn và tương thích, không tự mở rộng search engine scope.
5. Language dùng store switcher URL/current store thực, reuse exact flag assets khi khớp. Không hard-code English hoặc chuyển locale chỉ bằng Alpine state.
6. Reuse Global Style tokens, SVG chính xác và Header integration; không sửa trực tiếp `app/code/Snowdog/Menu`. Giữ native navigation path khi Snowdog bị tắt.

### 7.3 Trình tự và estimate còn lại

| Bước | Deliverable | Estimate |
|---|---|---:|
| MN-R1 | Hoàn thiện Full Spec: verify node visibility, data mapping/shared tree, responsive và state contract | 3–4h |
| MN-R2 | Desktop panel: feature, ba vùng navigation, dividers, typography/selected state | 4h |
| MN-R3 | Desktop branch selection, hover/click/keyboard, delayed close, outside/Escape và focus cleanup | 3–4h |
| MN-R4 | Mobile shell: drawer logo/close, search integration, viewport/scroll sizing | 3–4h |
| MN-R5 | Root → group → inline accordion, back/focus handling và feature image theo nhánh | 4–5h |
| MN-R6 | Settings Language + My Account, flags/current state và vi/en; reuse runtime switcher/account | 2–3h |
| MN-R7 | Header/sticky/overlay integration, resize cleanup, reduced motion và tablet adaptation | 3–4h |
| MN-R8 | Build/lint, behavioral tests, responsive/locale QA và screenshot evidence | 4–5h |
| **Tổng** | **Proposed remaining effort; không làm lại Header level-1** | **26–33h** |

Tăng so với estimate trước vì search trong drawer và mobile drill-down + accordion đã rõ scope; bổ sung kiểm thử cả ba states. Các bước >4h sẽ chia thành work points ≤4h khi tạo executable plan. Chưa gồm nhập/dịch toàn bộ content, thiết kế tablet mới, custom backend/schema hoặc feedback vòng ngoài. Không bao gồm Currency; My Account entry đã trong scope, custom account submenu chưa được định nghĩa nên chưa estimate riêng.

### 7.4 Files dự kiến

- `app/design/frontend/Secomm/launchpad/Snowdog_Menu/templates/hyva-topmenu-desktop/menu.phtml`: giữ level-1 styling, chuẩn hóa state/interaction.
- Theme-local `hyva-topmenu-desktop/menu/sub_menu.phtml` và renderer con cần thiết: thay generic submenu bằng composition đã xác minh.
- Theme-local `hyva-topmenu-mobile/menu.phtml`, `menu/sub_menu.phtml`: drawer, chuyển nhóm, accordion và vùng feature.
- `Snowdog_Menu/layout/default_hyva.xml`: child blocks/search/language và shared source wiring sau khi data contract được duyệt.
- Tailwind source, i18n và asset files chỉ trong phạm vi Menu. Module/ViewModel chỉ bổ sung nếu khả năng sẵn có không đáp ứng, qua review.

### 7.5 Acceptance criteria đề xuất

- AC-M01: Desktop tại 1440px khớp node `2151:11236`; ba mobile states tại 375px khớp đúng ba node tương ứng, không chỉ kiểm tra drawer root.
- AC-M02: Desktop đổi parent cập nhật đúng nhánh con, không giữ stale selection; leaf điều hướng đúng; hover/click/Enter/Space/Escape/outside hoạt động theo contract đã duyệt.
- AC-M03: Mobile vào group, back về root, mở/đóng accordion đúng; focus về control tương ứng; leaf URL runtime chính xác.
- AC-M04: Search dùng được ở cả ba mobile states; submit bằng Enter và button, không trùng DOM IDs; bàn phím ảo không làm mất khả năng đóng/cuộn drawer.
- AC-M05: Feature image có ở nested mobile states, đúng crop và managed source; root/settings theo design; không hard-code demo copy/links.
- AC-M06: Menu dài/label vi-en dài/nhánh thiếu child/thiếu feature không gây overflow hoặc controls rỗng; depth ngoài ba vùng desktop có policy rõ trong spec.
- AC-M07: Body scroll position được khôi phục; trap/release focus đúng; resize qua breakpoint không để lại scroll lock/backdrop; không xung đột Search/Cart/Account và sticky on/off.
- AC-M08: QA 320/375/640/768/1024/1280/1440/1920px; 640px và các intermediate widths là responsive QA, không tuyên bố pixel parity với frame không có trên page.
- AC-M09: Settings có Language và My Account, không Currency/social demo; Language options/current store đúng theo runtime và current option có selected indicator. Account entry dùng Magento account URL/behavior đã có. Menu/category/content cập nhật đúng qua cache; Snowdog-off fallback không regression.
- AC-M10: PHP/XML lint, Tailwind production build, scoped behavior tests và diff check pass; lưu evidence theo work item khi implementation bắt đầu.

### 7.6 Quyết định còn mở trước khi activate code

- **Data:** chọn menu identifier chung và renderer mapping; feature gắn với root hay từng selected branch; node cha có URL truy cập bằng label hay link “Xem tất cả”.
- **Interaction:** dùng Menu C làm baseline: top-level hover enhancement/click, nested parent click/keyboard mở cột kế; không tự thêm nested hover. Default selection khi mở, accordion single/multiple-open và reset khi mở lại vẫn cần ghi trong spec. Đề xuất single-open, reset root khi mở lại, back trả focus về parent; chưa coi các chi tiết này là yêu cầu Designer đã duyệt.
- **Responsive:** tablet full-width hay giới hạn width; xác nhận breakpoint khớp Header và cách co các cột Desktop.
- **Settings:** visibility đã rõ (Language + My Account, bỏ Currency). Chỉ còn hành vi My Account: Figma có chevron-down nhưng chưa có expanded state; A-scroll gốc là direct link. Đề xuất reuse direct account route nếu không có submenu requirement, và đồng bộ affordance với Designer; không tự thêm account links/logout vào cached markup.

Các điểm đã duyệt ở HMF-04/HMF-05 vẫn được giữ; không yêu cầu duyệt lại nguồn Snowdog hay hybrid activation. Đây là revision của kế hoạch phân tích theo yêu cầu người dùng, chưa có Full Spec VALID để coi là executable plan theo `.ai/AGENTS.md` §7.1.


## 8. Reference implementation — Settings và Hyvä UI clarification

Cập nhật 2026-09-29 theo chỉ định người dùng. Figma quyết định visual và các states đã vẽ; Hyvä UI cung cấp behavior/structure tham khảo; Snowdog giữ ownership dữ liệu navigation. Đây là kế hoạch adapt template, không đổi sang native Hyvä category navigation.

### 8.1 Nguồn đã đọc trực tiếp

- Desktop: `vendor/hyva-themes/hyva-ui/components/menu/C-vertical-dropdown-4-column/README.md`, `src/Magento_Theme/templates/html/header/menu/desktop.phtml`, `desktop-item.phtml`.
- Tên người dùng `C-vertical-dropdown-4-column-open-with-block` là preview `media/C-vertical-dropdown-4-column-open-with-block.jpg` của component **menu/C-vertical-dropdown-4-column**, không phải component directory riêng.
- Mobile: `vendor/hyva-themes/hyva-ui/components/menu-mobile/A-scroll/README.md`, `src/Magento_Theme/templates/html/header/menu/mobile.phtml`, `mobile-item.phtml`, `src/Magento_Store/templates/header/menu/languages.phtml`.
- Runtime prerequisite: composer.lock ghi default-theme và theme-module 1.5.2; `vendor/hyva-themes/magento2-theme-module/src/view/base/templates/page/js/plugins/htmldialog.phtml` tồn tại. Trước code kiểm tra plugin được load ở storefront, không thêm package chỉ để dùng dialog.

### 8.2 Mapping sang Snowdog templates

| Surface | Reuse từ Hyvä UI | Adapt cho Snowdog/Figma |
|---|---|---|
| Desktop shell | Menu C top-level dropdown, hover chỉ với fine pointer, outside click/Escape, ARIA | Giữ Header level-1 sẵn có và delayed mouseleave đã duyệt; panel full-width, không bị vùng cuộn level-1 clip |
| Desktop hierarchy | `desktop-item.phtml`: parent/leaf patterns và focus behavior | Parent title dùng URL Snowdog; chevron riêng mở cột kế; không có See-all trùng lặp; reset descendants; responsive rail theo spec §9–§10 |
| Desktop block | CMS content slot đi cùng top-level panel | Upstream đặt block bên phải và lookup `desktop-menu-category-[ID]`; không copy convention category-only này thành contract cho mọi Snowdog node. Managed feature mapping qua Snowdog phải được xác định trước |
| Mobile shell | A-scroll `<dialog>` + `x-htmldialog.noscroll`, logo/search child blocks, vùng nội dung cuộn riêng | Theme-local Snowdog mobile template, scoped IDs/events; close/Escape/resize cleanup; logo/close/search geometry theo Figma |
| Mobile hierarchy | A-scroll back/focus restoration, inert siblings/settings khi panel con mở | Root → group dùng panel; cấp trong group dùng inline accordion theo Figma. Không copy recursive panel cho mọi cấp như upstream |
| Mobile Settings | Native language `<details>` và runtime store redirect, My Account route | Flags, giữ current language trong options và selected underline (upstream bỏ current khỏi list); bỏ Currency/socials/demo Contact; account ambiguity ghi ở §7.6 |

Không dùng `Hyva\Theme\ViewModel\Navigation::getNavigation()` của hai mẫu làm nguồn menu chính: thay bằng Snowdog `getNodes()`, child lookup và node-type rendering. Snowdog cho phép custom URL/CMS/wrapper, không chỉ category; giữ store scope/cache identities, escaping và managed content filtering. Nếu normalize tree, thực hiện một lần và tránh lookup lặp theo từng node.

### 8.3 Thứ tự adapt và kiểm thử bổ sung

1. Chốt shared Snowdog hierarchy/feature mapping và parent URL access; đưa hai source references vào Full Spec/executable plan.
2. Adapt Menu C shell + item behavior vào Desktop Snowdog overrides; giữ existing Header styling, dùng Global Style tokens và exact Figma SVG.
3. Adapt A-scroll dialog/header/search/scroll boundary; wire Snowdog node renderers, rồi thay nested recursive panels bằng accordion theo các Figma states.
4. Settings: native language semantics, actual store options/current marker, flags; My Account reuse Magento route/approved existing account behavior, không thay auth logic.
5. QA thêm: dialog plugin thực sự load; không double scroll lock/trap với Snowdog cũ; invisible sibling/settings inert; back/close khôi phục focus; active branch reset; current language vẫn hiện trong danh sách; không xuất hiện Currency/social/demo links.

Estimate giữ **26–33h** cho scope hiện tại: reuse reference giảm việc thiết kế tương tác từ đầu, nhưng vẫn cần Snowdog data adaptation, Tailwind v4/token conversion và QA. My Account expanded content nếu được yêu cầu sẽ bổ sung scope cụ thể trước khi code. Không copy nguyên source CSS, fixed column translations, CMS identifier convention hay layout XML vendor vào theme mà không merge với Header hiện tại.
