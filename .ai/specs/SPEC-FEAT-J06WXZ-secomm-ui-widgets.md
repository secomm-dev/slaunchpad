# Feature Spec: Secomm UI Widgets

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-FEAT-J06WXZ |
| Feature ID | FEAT-J06WXZ |
| Specification Level | FULL |
| Author | Tuấn Lê |
| Status | Approved |
| Date | 2026-08-24 |
| Related Ticket(s) | To be created after spec approval |
| Workflow Mode | A |

## 1. Objective

Xây dựng capability Magento dùng chung cho các product theme Hyvä, cho phép merchant chèn và cấu hình các component được tuyển chọn từ Hyvä UI thông qua một Magento widget type duy nhất mang tên `Secomm UI`.

Capability phải cung cấp nội dung động, manual product/category selection, theme override, upgrade safety và backward compatibility cho CMS content đã lưu. Merchant không phải nhập template path, PHP, JavaScript hoặc Tailwind class.

## 2. User Stories

### US-001: Chọn Secomm UI component trong CMS

Là content administrator, tôi muốn chọn widget type `Secomm UI` và một component từ danh sách được hỗ trợ để xây CMS Page/Block mà không cần developer chỉnh layout XML.

**Acceptance Criteria:**

- [ ] Widget type hiển thị label chính xác `Secomm UI`.
- [ ] Component select chỉ chứa component đã đăng ký và được phê duyệt.
- [ ] Component không tương thích CMS không xuất hiện trong select.
- [ ] Widget có thể insert/edit lại trong CMS Page, CMS Block và editor Magento được support.

### US-002: Cấu hình nội dung động theo component

Là content administrator, tôi muốn form thay đổi theo component đã chọn để chỉ nhập những dữ liệu có ý nghĩa cho component đó.

**Acceptance Criteria:**

- [ ] Field schema phù hợp với component được chọn.
- [ ] Hỗ trợ field cơ bản: text, textarea/WYSIWYG khi được phép, select, yes/no, media, URL/CTA và numeric constraints.
- [ ] Hỗ trợ repeated items cho các component như accordion, slider, USP hoặc category tiles khi component matrix yêu cầu.
- [ ] Required/type/range/allowed-value được validate phía server.
- [ ] Dữ liệu không hợp lệ không được dùng để chọn arbitrary template hoặc render unsafe markup/style.

### US-003: Chọn product/category thủ công

Là content administrator, tôi muốn chọn chính xác product/category cần hiển thị và giữ thứ tự merchandising đã chọn.

**Acceptance Criteria:**

- [ ] Product component cung cấp manual product chooser.
- [ ] Category component cung cấp manual category chooser.
- [ ] Conditions builder không xuất hiện.
- [ ] Renderer giữ thứ tự item đã chọn, bỏ qua an toàn item không tồn tại/disabled/not visible tại store hiện tại.
- [ ] Empty result không gây exception hoặc render demo data.

### US-004: Dùng chung trên nhiều product theme Hyvä

Là frontend developer, tôi muốn module cung cấp implementation mặc định và theme có thể override presentation mà không fork business/schema contract.

**Acceptance Criteria:**

- [ ] Widget render được trên một Hyvä theme không có override riêng.
- [ ] Theme override template bằng Magento theme inheritance.
- [ ] Hai product theme có thể dùng cùng component ID/schema nhưng khác presentation.
- [ ] Module không yêu cầu Luma/non-Hyvä fallback.

### US-005: Nâng Hyvä UI an toàn

Là maintainer, tôi muốn Hyvä UI update không âm thầm thay đổi storefront hoặc phá CMS directive hiện có.

**Acceptance Criteria:**

- [ ] Runtime không include template trực tiếp từ `vendor/hyva-themes/hyva-ui`.
- [ ] Composer update Hyvä UI không tự thêm, xóa hoặc thay đổi component trong Admin.
- [ ] Mỗi imported component ghi source component, source version, schema version và local modifications.
- [ ] Component ID/schema đã phát hành không bị đổi hoặc xóa nếu chưa có migration/backward-compatibility path.

## System Behaviour

### Main flow

1. Admin mở Magento widget insertion hoặc widget instance form.
2. Admin chọn type `Secomm UI`.
3. Hệ thống hiển thị component select từ allowlist/registry nội bộ.
4. Admin chọn component; hệ thống hiển thị schema field tương ứng.
5. Admin nhập dữ liệu và, với catalog component, chọn product/category thủ công.
6. Khi save/insert, Magento lưu widget directive/instance parameters với stable component ID và schema version.
7. Storefront widget block resolve component ID qua registry, normalize/validate data, lấy catalog entity nếu cần và render template nội bộ.
8. Magento theme inheritance ưu tiên template override của active Hyvä product theme nếu có.

### Failure behaviour

- Unknown/disabled component ID: không resolve arbitrary path; fail closed, không render component và log diagnostic không chứa CMS content nhạy cảm.
- Invalid field value: dùng safe default khi field optional; bỏ render và ghi diagnostic khi required contract không đạt.
- Missing product/category: bỏ item; giữ thứ tự các item hợp lệ còn lại.
- Empty collection: render empty output hoặc documented empty state theo component schema; không dùng sample/demo data.
- Missing optional frontend dependency: component phải bị loại khỏi registry hoặc fail closed theo component eligibility matrix.
- Legacy schema version: chạy compatible normalizer/adapter; không mutate CMS content tự động trong request storefront.

### Compatibility behaviour

- Stable key là component ID nội bộ, không phải Hyvä UI directory name.
- Schema change additive là mặc định; breaking schema cần version mới hoặc adapter migration.
- Upstream component mới chỉ xuất hiện sau import, registry registration, test và release.
- Theme override chỉ thay presentation; registry/schema/data validation thuộc module core.

## 3. Scope

### In Scope

- Custom Magento module dự kiến `Secomm_UiWidget`.
- Một Magento widget type với label `Secomm UI`.
- Component registry/allowlist và metadata/provenance.
- Dynamic Admin fields theo component.
- Content-independent components: accordion, banner, card, generic content, testimonial, USP, shortcuts, embed, categories/slider dạng manual content, và các variant được eligibility matrix phê duyệt.
- Data/context-backed components phù hợp CMS: product/category-oriented components và các component khác chỉ sau khi có data provider/context contract rõ ràng.
- Manual product chooser và manual category chooser.
- Repeated item editing khi component cần collection input.
- Default module templates và Magento theme override cho Hyvä product themes.
- Tailwind v4 source registration/build support cho template module/override.
- Alpine.js isolation khi component cần interaction.
- Widget directive/schema backward compatibility.
- Security, accessibility, caching và performance validation.
- Documentation cho thêm component mới và sync upstream có kiểm soát.

### Out of Scope

- Header, footer, minicart, breadcrumbs, product gallery, category filter và component thay thế layout/system behaviour, trừ khi có feature/spec riêng.
- Luma hoặc non-Hyvä storefront support.
- Product/category conditions builder.
- Tự động scan hoặc expose toàn bộ `vendor/hyva-themes/hyva-ui/components`.
- Render trực tiếp template từ Hyvä UI vendor package.
- Tự động sync/overwrite component nội bộ khi Composer update.
- Cho admin nhập PHP, JavaScript, raw template path hoặc arbitrary Tailwind class.
- Cài `Hyva_Widgets` hoặc `Hyva_CmsTailwindJit` làm runtime dependency trong baseline.
- REST/GraphQL management API cho component/widget.
- Database schema mới, trừ khi solution design chứng minh repeated-item storage không thể dùng widget parameters an toàn và SA phê duyệt riêng.

## 4. Business Rules

| Rule ID | Rule | Test Approach |
|---------|------|---------------|
| UIW-BR-001 | Admin chỉ thấy một widget type `Secomm UI`; component là field bên trong widget. | Admin integration/manual test |
| UIW-BR-002 | Chỉ component trong explicit allowlist được chọn/render. | Registry unit test + tampered directive test |
| UIW-BR-003 | Product/category được chọn thủ công; giữ merchandising order. | Integration test với ordered IDs |
| UIW-BR-004 | Capability chỉ hỗ trợ product theme dựa trên Hyvä. | Theme compatibility matrix |
| UIW-BR-005 | Hyvä UI là upstream/reference; code runtime thuộc `Secomm_UiWidget`. | Dependency/source audit |
| UIW-BR-006 | Component ID và schema version là persisted public contract. | Backward-compatibility fixtures |
| UIW-BR-007 | Component mới không tự xuất hiện sau dependency update. | Composer/package update regression |
| UIW-BR-008 | Admin không cần biết Tailwind/Alpine/template implementation. | Admin usability review |
| UIW-BR-009 | Storefront strings thuộc Secomm phải có `vi_VN` và `en_US`. | Translation/static review |
| UIW-BR-010 | Không render demo URLs/content từ Hyvä UI source. | Template/content regression |

## 5. Technical Approach

### 5.1 Architecture Impact

Feature thêm shared storefront capability và module boundary mới, vì vậy `changes_architecture = true` và Mode A là bắt buộc.

Architecture proposal:

```text
Magento CMS/widget directive
        ↓
Secomm UI widget block (BlockInterface)
        ↓
Component registry → definition/schema/provenance
        ↓                         ↓
Input normalizer/validator    Data provider (manual/catalog)
        └──────────────┬──────────┘
                       ↓
Safe template resolver
                       ↓
Module default template → active Hyvä theme override
```

Canonical architecture decision: `DEC-FEATJ06WXZ-001` — accepted, phê duyệt ngày 2026-08-24.

### 5.2 Implementation Notes

- Widget class implement `Magento\Widget\Block\BlockInterface` và được khai báo trong `etc/widget.xml`.
- Component select sử dụng registry/source model nội bộ; không derive từ filesystem/vendor runtime.
- Registry definition tối thiểu gồm component ID, label, template alias, schema version, field schema, source provenance, dependency flags và cache policy.
- Template resolver chỉ nhận definition đã đăng ký; parameter từ CMS không được trở thành template path.
- Product/category providers batch-load entity theo manual IDs và reorder theo selection.
- Dynamic Admin form implementation phải được proof bằng vertical slice `Banner A` trước khi mở rộng.
- Repeated item persistence format phải được solution design chốt, có size/validation limits và backward-compatible decoder.
- Styling choice dùng enum/token được phép; template ánh xạ sang class cố định để Tailwind scanner nhìn thấy.
- Mỗi Alpine instance có isolated state/unique DOM IDs; không jQuery/RequireJS trên Hyvä storefront.
- Không sửa code trong `vendor/` hoặc third-party module.

### 5.3 Database Changes

Baseline: **No database schema changes**. Dữ liệu được lưu bằng Magento widget directive/instance parameters. Nếu implementation discovery chứng minh cần custom persistence cho repeated collections, phải quay lại spec/architecture review và Tier-2 schema approval trước khi thay đổi.

### 5.4 API Changes

No REST/GraphQL API changes trong baseline.

### 5.5 Integration Impact

- `Magento_Widget`: widget declaration, Admin insertion, directive rendering.
- `Magento_Cms`: CMS Page/Block rendering.
- `Magento_Catalog`: manual product/category selection và storefront collection resolution.
- Hyvä Theme/Tailwind/Alpine: presentation và interactive behaviour.
- PageBuilder/TinyMCE: insertion/edit compatibility; không customize PageBuilder content type trong baseline.

Không thay đổi payment, checkout, shipping, order state hoặc external API contract.

## 6. UI/UX

Admin flow tối thiểu:

```text
Widget Type: Secomm UI
Component:   [Banner A ▼]

Component Options
  Title
  Subtitle
  Mobile Image  [Select from Gallery]
  Desktop Image [Select from Gallery]
  CTA Label
  CTA URL
  Alignment     [Start | Center | End]
  Appearance    [Primary | Secondary | Link | Overlay]
```

UX rules:

- Chỉ hiển thị field có ý nghĩa cho component hiện tại.
- Field labels/descriptions hướng tới content editor, không dùng terminology implementation.
- Component change không được silently giữ dữ liệu incompatible mà renderer có thể dùng nhầm.
- Repeated items có add/remove/reorder và upper bound được định nghĩa.
- Product/category chooser cho biết item đã chọn và thứ tự.
- Không có input raw Tailwind class/template path.

## 7. Dependencies

- Existing: Magento 2.4.8-p5 modules `Magento_Widget`, `Magento_Cms`; `Magento_Catalog` cho data-backed components.
- Existing: Hyvä Default Theme 1.5.2, Tailwind CSS v4, Alpine.js.
- Development reference: `hyva-themes/hyva-ui` 2.8.0 hiện có trong repository.
- Optional development reference only: official `Hyva_Widgets` source nếu solution design cần đối chiếu custom field/media/repeated-item pattern; không thêm dependency production nếu chưa có TL approval.
- `Hyva_CmsTailwindJit` không cần cho baseline vì classes nằm trong code-owned `.phtml`, không nằm trong raw CMS input.

## 8. Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Một widget type có nhiều schema làm Admin form phức tạp | M | H | Registry-driven schema; proof bằng Banner A; giới hạn component theo batch |
| Repeated items vượt giới hạn widget directive/encoding | M | H | Chốt persistence contract + size limits; test PageBuilder/TinyMCE round-trip |
| Template/data contract drift giữa product themes | M | H | Schema thuộc module; theme chỉ override presentation; compatibility fixtures |
| Hyvä UI upstream breaking change | M | M | Copy/import có kiểm soát; provenance + manual diff; không runtime include vendor |
| Tailwind purge/missing dynamic classes | M | H | Static enum-to-class maps; module `@source`; production build verification |
| XSS/unsafe URL/style từ CMS parameters | M | H | Server validation, allowlists, context escaping, no arbitrary templates/classes |
| Product/category query N+1 hoặc stale cache | M | H | Batch load; store-aware filters; cache identities/tags per definition |
| Multiple Alpine widgets xung đột state/DOM ID | M | M | Instance isolation + unique IDs + multi-instance tests |
| Component list quá lớn làm editor khó dùng | M | M | Eligibility matrix, batches, labels/grouping, explicit allowlist |
| Hyvä-only module bị dùng nhầm trên Luma | L | M | Module docs/compat contract; no claim of Luma support; deployment/theme checks |

## 9. Test Approach

### Unit

- Registry returns only registered/enabled component definitions.
- Unknown/tampered component ID fails closed.
- Field normalization/validation for required, enum, numeric, URL and repeated items.
- Schema version adapter reads legacy fixtures.
- Template resolver rejects arbitrary paths.
- Manual ID ordering utility is deterministic.

### Magento integration

- Widget declaration resolves and block implements `BlockInterface`.
- CMS directive round-trip for CMS Page and CMS Block.
- Widget instance placement where Magento container/template supports it.
- Manual product/category chooser values persist and render in selected order.
- Store/status/visibility filtering and missing entity behaviour.
- Cache identity/invalidation for product/category-backed output.
- Multi-store rendering and translations.

### Frontend/manual

- PageBuilder/TinyMCE insert, edit, save and reopen.
- At least two widget instances of the same interactive component on one page.
- Responsive behaviour and keyboard/screen-reader accessibility.
- Two Hyvä product themes: module default plus at least one template override.
- Tailwind `npm run build-prod` and class-presence visual regression.
- XSS payloads in text, WYSIWYG, URL, media metadata and style-like fields.
- FPC/block cache behaviour after CMS/widget update.

### Vertical slice gate

`Banner A` phải pass end-to-end Admin → persistence → validation → rendering → Tailwind → theme override trước khi implement component batch còn lại.

## 10. Assumptions

- [x] Product/category selection là manual-only — user confirmed 2026-08-24.
- [x] Compatibility target là Hyvä-only — user confirmed 2026-08-24.
- [x] Admin cần cấu hình dynamic content — user confirmed 2026-08-24.
- [x] Capability dùng như core trên toàn bộ product theme Hyvä — user confirmed 2026-08-24.
- [ ] Magento widget parameter/directive encoding đáp ứng repeated item payload theo giới hạn đã chọn — cần proof trong solution design/vertical slice.
- [ ] Tất cả component nhóm data-backed được yêu cầu đều có CMS-safe context contract — cần component eligibility matrix xác nhận từng variant.

## 11. Open Questions

- [ ] OQ-001 — Chốt danh sách component/variant chính xác cho Batch 1 và Batch 2 bằng component eligibility matrix. Owner: Product/TL.
- [ ] OQ-002 — Chốt persistence/encoding và maximum item count cho repeated collections. Owner: SA/TL.
- [ ] OQ-003 — Chốt dynamic Admin form mechanism sau proof-of-concept: Magento helper block/AJAX schema renderer hay static `widget.xml` dependencies cho component đơn giản. Owner: SA/TL.
- [ ] OQ-004 — Chốt cache lifetime/identity policy cho từng product/category component. Owner: TL.
- [ ] OQ-005 — Xác định product themes Hyvä tối thiểu dùng trong compatibility gate. Owner: Product/TL.
- [x] OQ-006 — Resolved bởi `DEC-FEATJ06WXZ-002`: chỉ field `trusted-rich-text` opt-in dùng native Magento WYSIWYG và CMS block filter; không custom sanitizer baseline. Approved: Tuấn Lê, 2026-08-25.

## 12. Estimation

Chưa commit estimate trước khi component matrix và dynamic-form proof chốt. Range sơ bộ chỉ phục vụ planning, không phải delivery commitment.

| Task | Estimate | Actual |
|------|----------|--------|
| Feature/spec/solution design + component matrix | 1–2 d | |
| Module foundation + registry/schema contracts | 1–2 d | |
| Dynamic Admin form + Banner A vertical slice | 3–5 d | |
| Static/content component batch | 4–8 d, tùy số variant | |
| Manual catalog providers + data-backed batch | 4–8 d, tùy số variant | |
| Automated tests, two-theme regression, docs/QC handoff | 3–5 d | |
| **Total preliminary development/engineering** | **16–30 d** | |

## Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| DEV | Tuấn Lê | 2026-08-24 | Approved |
