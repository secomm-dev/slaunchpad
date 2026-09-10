---
id: TASK-938NGT
type: task
title: 'llms.txt: single Brand Summary render + /ai/store purpose contract alignment'
project_code: SLP
parent: null
external_refs:
  branch: task/llms-store-metadata-contract
mode: C
specification_level: EMBEDDED
spec_status: VALID
specification_ref: null
risk: low
status: completed
created: 2026-09-10
updated: 2026-09-10
decisions: []
decision_assessment: none
components: []
source_areas:
  - app/code/Secomm/AiDiscoverability/
  - app/code/Secomm/AiCommerce/Test/
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
last_verified: 2026-09-10
supersedes: []
---

# [SLP][TASK-938NGT] llms.txt: single Brand Summary render + /ai/store purpose contract alignment

<!-- CANONICAL TASK RECORD — TASK-938NGT (branch: task/llms-store-metadata-contract). -->

## Bối cảnh (Context)

llms.txt render configured Brand / Site Summary **hai lần**: một lần là blockquote
`> summary` ngay dưới H1 (kèm site-title fallback), một lần nữa trong section
`## Store Summary` — trong khi cả hai đều sinh từ cùng một config field
`general/brand_summary`. Đồng thời dòng Purpose của endpoint `### Store Information`
(`/ai/store`) trong llms.txt quảng cáo "supported public catalog context" — không
khớp contract V1 có thật của `Secomm_AiCommerce\Service\Response\StoreDto`
(allowlist cố định: `store_code`, `locale`, `currency`, `base_url`).

## Mini-Spec (Embedded)

### Goal

1. Brand / Site Summary render **đúng một lần**, trong section Store Summary;
   bỏ hoàn toàn blockquote đầu tài liệu và fallback site-title cho summary.
2. Purpose của `/ai/store` khớp contract V1 thực tế; KHÔNG mở rộng API công khai.

### Expected Behavior

Với `Site Title = Demo store1`, `Brand / Site Summary = Hyva theme1`,
`Locale = vi_VN`, `Currency = VND`:

```
# Demo store1

Locale: vi_VN
Currency: VND

## Store Summary
Hyva theme1
```

Không chứa `> Hyva theme1`. Khi `brand_summary` rỗng: section Store Summary bị
lược bỏ hoàn toàn (không fallback, không fabricated text). Site-title fallback
cho H1 giữ nguyên. `/ai/store` Purpose: "Store metadata: store code, locale,
currency and base URL.".

### Constraints / Rules

- Không đổi runtime behavior của `StoreDto` (audit xác nhận contract đã đúng).
- Không thêm field/tagline mới, không redesign module.
- Giữ nguyên sanitization/bounding cho merchant-entered prose (strip tags, control
  chars, 500-char default bound, bracket escaping).
- PHP 8.2+ / `strict_types` / Magento coding standard.

### Acceptance Criteria

- [x] **DoD-001**: Brand Summary render đúng một lần dưới Store Summary; không còn
  blockquote `>` nào do formatter sinh ra (formatter bỏ param `$summary`).
- [x] **DoD-002**: `brand_summary` rỗng → không có section Store Summary và không
  có text fallback; site-title fallback chỉ áp dụng cho H1.
- [x] **DoD-003**: Purpose `/ai/store` đúng wording mới; old wording
  "supported public catalog context" không còn trong output/module (chỉ còn
  trích dẫn lịch sử trong CHANGELOG).
- [x] **DoD-004**: Unit tests pin cả 2 regression: formatter output exact bytes,
  StoreDto V1 allowlist exact (`store_code`, `locale`, `currency`, `base_url`),
  từ chối internal fields (`website_id`, `store_group_id`, `name`,
  `extension_attributes`), base_url normalization.
- [x] **DoD-005**: Focused suites xanh (AiDiscoverability + AiCommerce), PHP 8.3
  syntax sạch, PHPCS Magento2 sạch trên toàn bộ file thay đổi.

## Approach (đã triển khai)

- `LlmsTxtFormatter`: bỏ param `$summary` + khối render blockquote; const
  `SUMMARY_MAX_LENGTH` → `TEXT_MAX_LENGTH` (bound 500 giữ nguyên cho
  `sanitizeText` default); signature mới `format($siteName, $locale, $sections, $currency)`.
- `LlmsTxtGenerator`: bỏ biến `$summary` (site-title fallback cho summary), giữ
  `brandSummary` → prose section Store Summary (render-once logic có sẵn); gọi
  formatter signature mới; sửa luôn `@param` docblock lệch tên (PHPCS warning có sẵn).
- `CommerceEndpointsSource`: purpose `/ai/store` → wording mới.
- `system.xml` + `i18n/en_US.csv` + `i18n/vi_VN.csv`: comment field
  Brand / Site Summary documents rendered-once-under-Store-Summary + no-fallback.
- `README.md` + `CHANGELOG.md` (1.6.1): cập nhật cấu trúc output + bảng config.
- Tests: cập nhật `LlmsTxtFormatterTest` (14 tests, +2 mới), `LlmsTxtGeneratorTest`
  (6 tests, đổi tên + exact-bytes), `CommerceEndpointsSourceTest` (purpose mới),
  thêm `Secomm_AiCommerce/Test/Unit/Service/Response/StoreDtoTest.php` (3 tests).

## Đánh giá rủi ro / Scope

- Không chạm payment/checkout/shipping/order/customer/PII; không đổi API runtime.
- Caller của `format()`: duy nhất `LlmsTxtGenerator:208` (grep + CodeGraph audit).
- AiCommerce: chỉ thêm test; không bump CHANGELOG (runtime/contract unchanged —
  có chủ đích, ghi nhận tại đây).

## Evidence

- `.ai/evidence/TASK-938NGT/implementation-report.md` (test/PHP syntax/PHPCS output,
  direct formatter output, diff summary).
