# Implementation Plan — TASK-KMJV5Q (SLP-291): Footer Legal Links — edit qua system config

| Specification | Embedded Mini-Spec trong `.ai/records/tasks/TASK-KMJV5Q.md` (spec_status: VALID) |
|---|---|
| Mode | B |
| Risk | low (display + config value-only; không §12, không DB schema, fallback giữ render hiện tại) |
| Owner | Dev (AI-assisted) → TL review → QC |

## Specification

Embedded Mini-Spec — record `TASK-KMJV5Q`: Goal / Expected Behavior / Constraints / Out of Scope /
AC-001..AC-007. User chốt 10-01: **system config**. Assumptions pending TL (scope = 2 link copyright
bar; edit cả label + URL; URL chấp nhận route + full external; không tạo placeholder CMS page).

## Kiến trúc chọn

**Section config**: tab mới `launchpad` + section `launchpad_footer` + group `legal_links` (4 field
text `terms_label` / `terms_url` / `privacy_label` / `privacy_url`, store view scope) — resource
`Magento_Backend::content`. Không config.xml defaults (trống = fallback code) → không cần data
migration, backward compat tự nhiên.

**ViewModel**: `Launchpad\CmsContent\ViewModel\LegalLinks` — DI `ScopeConfigInterface` +
`UrlInterface`; 4 getter trả `?string` (null khi config trống). URL resolve trong VM: bắt đầu
`http://` / `https://` → as-is, ngược lại `$url->getUrl($path)`. **Bind vào template qua
`$viewModels->require(LegalLinks::class)`** (registry Hyva inject cho MỌI template qua
`PhpPlugin` trên TemplateEngine — verify trong run: layout argument `xsi:type="object"` là block
data, KHÔNG tự thành template var; class VM phải `implements ArgumentInterface` để registry
nhận).

**Fallback trong template**: `$legal_links->getTermsLabel() ?: __('Terms & Conditions')` — phrase
fallback ở template level để đi đúng theme CSV (vi_VN.csv:990-992 hiện có).

**Cache invalidation**: observer `admin_system_config_changed_section_launchpad_footer` → clean
`block_html` + `full_page` qua `TypeListInterface` (core 2.4.8 không có cache.xml event-invalidation
— grep xác nhận; observer là pattern chuẩn Magento cho config→cache, 20 dòng, scope đúng section).

## Files (tạo mới / sửa)

Module `app/code/Launchpad/CmsContent/`:

| File | Thao tác |
|---|---|
| `etc/adminhtml/system.xml` | **Mới** — tab `launchpad` + section `launchpad_footer` + group `legal_links` (4 field, store scope, comment/placeholder giải thích fallback + route hint) |
| `etc/adminhtml/events.xml` | **Mới** — event `admin_system_config_changed_section_launchpad_footer` → observer |
| `Observer/InvalidateFooterLegalConfig.php` | **Mới** — `TypeListInterface::cleanType('block_html')` + `cleanType('full_page')` |
| `ViewModel/LegalLinks.php` | **Mới** — 4 getter `?string`, URL resolve http(s) passthrough / `getUrl()`, `strict_types` |
| `etc/module.xml` | Sửa — bump version |
| `CHANGELOG.md` + `README.md` | Sửa — ghi entry + section cấu hình mới |

Theme `app/design/frontend/Secomm/launchpad/`:

| File | Thao tác |
|---|---|
| `Magento_Theme/layout/default.xml` | Sửa — `<referenceBlock name="copyright">` + argument object VM (additive, không đụng node hiện có) |
| `Magento_Theme/templates/html/footer/copyright.phtml` | Sửa — đọc VM, merge fallback, giữ nguyên markup/aria-label/escaper |

Không đụng: vendor, 4 CMS block footer (PageBuilder), group "Pháp lý" `footer_links`, DB schema,
`config.php`, i18n CSV (0 storefront phrase mới — fallback dùng phrase hiện có), module nào khác.

## Phases

1. **Wave 1 — Code (AC-001..005)**: system.xml + events.xml + observer + ViewModel + layout
   argument + template. Bump version.
2. **Wave 2 — Verify (AC-001..007)**: matrix dưới đây + evidence `.ai/evidence/TASK-KMJV5Q/`.
3. **Wave 3 — Release (AC-007)**: CHANGELOG/README, `estimation-tracking.csv` row, validator
   `--check-records --check-identity --check-specs`, pre-review checklist → TL review.

## Verification matrix

| Khu | Check |
|---|---|
| Admin UI | Section renders Stores > Configuration > Launchpad > Footer > Legal Links; 4 field; scope store view (đổi store view → field riêng/inherit); save không lỗi |
| Fallback (AC-002) | Config trống → copyright bar render nguyên trạng hiện tại (DOM-compare: labels `Điều khoản và Điều kiện`/`Quyền riêng tư`, href routes cũ) |
| Route path (AC-003) | Điền label VI + route `about-us` → save (không flush tay) → storefront footer bar đổi ngay; FPC warm hit vẫn đúng |
| External URL (AC-004) | `https://example.com/terms` → href exact, không bị prefix base URL |
| XSS (AC-005) | Label chứa `<script>` / `"><img src=x>` → render escaped (text, không execute) |
| Store scope (AC-006) | store 2 `launchpad_en` đặt giá trị khác → khác store 1 (store-switch local chết LL-0011 → verify qua emulation CLI, pattern TASK-7EYJ4C F7) |
| Regression (AC-007) | Playwright: 5 section footer render đủ thứ tự, accordion mobile hoạt động, homepage không vỡ, 0 pageerror |
| Machine gate | `bin/project-ai-validate --check-records --check-identity --check-specs` PASS cho record này |

## Env traps (đã biết — xử lý trong run)

- CLI chạy **as secomm** (memory: shell root → 500 nếu chạy magento CLI as root).
- `setup:upgrade` có thể bị chặn bởi working-tree `Secomm_Ahamove db_schema.xml` pending (TASK-7EYJ4C
  v4.2) — version bump KHÔNG có patch/schema nên defer `setup:upgrade` vô hại; ghi chú TL nếu bị
  chặn.
- FPC BẬT local — probe bằng `curl -H "Host: slaunchpad.localhost" http://127.0.0.1/`; assert sau
  khi clear + warm hit.

## Rollback

Git revert working tree (chưa commit). DB chỉ có `core_config_data` rows do admin test — xóa tay
trong verify (không có patch/schema để revert).
