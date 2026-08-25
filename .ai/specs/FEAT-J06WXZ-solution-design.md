# FEAT-J06WXZ — Solution Design: Secomm UI Widgets

## Metadata

| Field | Value |
|---|---|
| Feature | FEAT-J06WXZ |
| Specification | `SPEC-FEAT-J06WXZ` — VALID/Approved |
| Decision | `DEC-FEATJ06WXZ-001` — accepted |
| Status | Approved with implementation plan — 2026-08-24 |
| Author | Tuấn Lê |
| Date | 2026-08-24 |

## 1. Goals and constraints

- Một Magento widget type `Secomm UI`.
- Explicit component registry, dynamic fields và content validation.
- Manual product/category selection only.
- Hyvä-only, shared core cho nhiều product theme.
- Module default templates; theme override chỉ presentation.
- Không runtime include/scan Hyvä UI vendor.
- Không DB schema hoặc dependency mới trong baseline.
- Stable component ID/schema version và legacy decoder.

## 2. Module boundary

Module dự kiến: `Secomm_UiWidget`, path `app/code/Secomm/UiWidget`.

```text
Api/
  ComponentDefinitionInterface.php
  ComponentRegistryInterface.php
  DataProviderInterface.php
  ParameterCodecInterface.php
Block/Widget/
  SecommUi.php
Block/Adminhtml/Widget/
  ComponentOptions.php
Model/Component/
  Definition.php
  Registry.php
  SchemaValidator.php
  TemplateResolver.php
Model/Parameter/
  Codec.php
  Normalizer.php
Model/Config/Source/
  Component.php
Model/DataProvider/
  ManualProductProvider.php
  ManualCategoryProvider.php
etc/
  module.xml
  di.xml
  widget.xml
  component.xml              # proposed declarative registry source
view/adminhtml/
  layout/, templates/, web/js/
view/frontend/
  templates/widget/, templates/components/
  layout/, web/tailwind/
i18n/, Test/, README.md, CHANGELOG.md
```

Tên/path cuối cùng có thể điều chỉnh theo Magento convention khi implementation, nhưng responsibility boundary không đổi nếu không quay lại architecture review.

## 3. Runtime flow

```text
{{widget type="Secomm\\UiWidget\\Block\\Widget\\SecommUi"
          component="banner_a"
          schema_version="1"
          payload="..."}}
                         ↓
Block validates top-level parameters
                         ↓
Registry.get("banner_a")
                         ↓
Codec decode → schema normalizer/validator
                         ↓
Optional DataProvider.resolve(normalized data, store)
                         ↓
TemplateResolver uses registered template alias only
                         ↓
module default template / active theme override
```

Unknown component, unsupported version hoặc invalid required payload phải fail closed; không bao giờ fallback sang template path từ directive.

## 4. Component definition contract

Definition cần có tối thiểu:

```text
id                  stable string, e.g. banner_a
label               translatable Admin label
group               content | catalog | contextual
template            registered module template alias
schema_version      positive integer
fields              ordered field definitions
data_provider       optional service key
cache_policy        none | catalog-identities | explicit
requirements        Hyvä/Alpine/module/config prerequisites
source              upstream component + imported version
status              enabled | disabled | deprecated
```

Registry merge phải deterministic, reject duplicate ID và không nhận arbitrary class/template từ CMS data.

## 5. Field schema and Admin rendering

Supported baseline field types:

- text, textarea, trusted-rich-text
- select, yes/no, integer/decimal
- media/image
- URL + CTA compound field
- product chooser, category chooser
- collection/repeater với nested allowed field types

`trusted-rich-text` tuân `DEC-FEATJ06WXZ-002`: Admin dùng native Magento WYSIWYG; storefront dùng CMS block filter. Đây là explicit trusted-admin boundary, không phải generic sanitizer. Chỉ template field opt-in được phép render filtered output không escape; embed giữ contract riêng.

Dynamic form proposed flow:

1. `widget.xml` khai báo common shell: component selector, schema version và custom options block.
2. `ComponentOptions` helper block nhúng schema của enabled registry definitions vào Magento `x-magento-init`; không cần custom Admin endpoint.
3. Client JS chỉ quản lý UI/media/reorder và ghi một hidden `parameters[payload]`; server definition vẫn là source of truth.
4. Save path được plugin validate cho cả CMS directive và standalone widget instance; storefront decode/normalize lại và fail closed.

Vertical slice phải quyết định mechanism cuối cùng dựa trên round-trip thực tế của Magento Widget popup, PageBuilder và TinyMCE. Không dùng generic dynamic field library mới.

## 6. Parameter persistence

Baseline: widget parameters, không DB table.

- Scalar/common parameters có thể lưu top-level.
- Component-specific data lưu bằng canonical JSON envelope format version `1`, sau đó Base64URL để an toàn trong Magento directive.
- Codec deterministic với giới hạn 16 KiB encoded, depth 6 và tối đa 50 rows cho mỗi collection.
- Decode failure không throw ra storefront; fail closed + safe diagnostic.
- Không unserialize PHP object; chỉ structured scalar/array data.
- Legacy codec/normalizer giữ support cho schema version đã release.

Proof gate: accordion/slider payload phải round-trip qua Insert Widget → save CMS → reopen editor → edit → storefront mà không mất/reorder/escape sai dữ liệu.

## 7. Manual catalog providers

### Product

- Input: ordered product IDs (SKU chỉ dùng display/search nếu chooser cần).
- Batch collection/repository query; không load trong loop.
- Filter store/website, status, visibility; stock/saleability policy thuộc component definition.
- Reorder result theo ordered IDs.
- Cache identities gồm product tags thực tế được render và store-sensitive parameters.

### Category

- Input: ordered category IDs.
- Batch collection với fields cần thiết; store-aware name/url/image.
- Filter inactive/missing categories.
- Reorder theo selection.
- Cache identities gồm category tags.

Conditions builder và auto discovery ngoài scope.

## 8. Templates and theme inheritance

- Default templates nằm trong `Secomm_UiWidget::components/...`.
- Product theme override tại `<theme>/Secomm_UiWidget/templates/components/...`.
- Schema/validation/provider không nằm trong theme.
- Mọi output escape theo context; trusted rich text phải qua approved sanitizer/render path.
- Demo URL/image/content của Hyvä UI bị xóa.
- DOM ID có prefix instance-specific.

## 9. Tailwind v4 and Alpine

- Register module template/CSS source bằng Hyvä module mechanism tương thích 1.5.2; fallback explicit `@source` chỉ nếu registration không đủ.
- Không generate fully dynamic Tailwind class từ CMS.
- Enum map nằm trong PHP/template dưới dạng complete static class strings.
- Component CSS chỉ import khi component được ship; không scan `vendor/hyva-themes/hyva-ui`.
- Alpine state local theo widget; global store chỉ khi contract thực sự shared.
- Plugins có sẵn ở Hyvä 1.5.2 (`x-snap-slider`, `x-htmldialog`) được reuse; dependency ngoài baseline phải approval.

## 10. Cache and security

- Static content: block cache key gồm store, component ID, schema version và canonical payload hash.
- Catalog content: thêm entity identities/tags; không cache customer-specific state trong public block.
- Newsletter/contextual components phải review private/customer state riêng trước B2.
- Validate component ID, schema version, payload size/depth/count, enum, URL scheme, media reference và numeric range.
- Không log full CMS payload, PII hoặc secrets.
- Admin actions dùng Magento authorization/form key/backend URL mechanisms.

## 11. Upstream lifecycle

Mỗi component manifest ghi:

```text
source_component: banner/A-default
source_version: 2.8.0
schema_version: 1
local_modifications: media chooser, dynamic CTA, removed demo fallback
```

Update flow: compare upstream → classify fix/breaking → port manually → test fixtures/visual regression → module release. Composer update một mình không đổi registry/template nội bộ.

## 12. Delivery sequence

1. Foundation/contracts.
2. Dynamic Admin form + codec.
3. Banner A vertical slice.
4. Batch 1.
5. Catalog providers/cache.
6. Batch 2.
7. Two-theme regression/docs/QC handoff.

## 13. Resolved and remaining decisions

Resolved by approved spec/DEC:

- Hyvä-only.
- Manual product/category chooser.
- One widget type + explicit registry.
- Secomm-owned runtime templates.
- No JIT/Hyva Widgets runtime dependency baseline.

Resolved in TASK-P0BP58:

- Admin renderer: native `widget.xml` helper block + server-embedded registry schema + `x-magento-init`; no custom endpoint.
- Persistence: canonical JSON/Base64URL format v1; 16 KiB/depth 6/50 collection rows.

Must still be resolved within planned proof gates, without changing approved behaviour:

- Rich HTML sanitizer path.
- Concrete cache lifetime per contextual component.
- Second Hyvä theme for compatibility gate.
