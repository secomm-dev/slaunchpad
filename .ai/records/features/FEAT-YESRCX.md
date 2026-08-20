---
id: FEAT-YESRCX
type: feature
project_code: SLP
parent: null
legacy_ids: [FEAT-003]
title: Admin-configurable storefront announcement toggle (reuse Secomm_Base)
mode: B
risk: low
status: done                     # implemented + statically validated 2026-07-21
created: 2026-07-21
updated: 2026-07-21
ticket_ref:
decisions: []                    # no architectural decision — reuses existing module + ifconfig pattern
# Knowledge-consolidation contract (RM-07)
components:
  - CMP-STOREFRONT               # placeholder stable ID (COMPONENT_INDEX = Phase 1c)
source_areas:
  - app/code/Secomm/Base/etc/adminhtml/system.xml
  - app/code/Secomm/Base/etc/config.xml
  - app/code/Secomm/Base/view/frontend/layout/default.xml
  - app/code/Secomm/Base/view/frontend/templates/announcement.phtml
  - app/code/Secomm/Base/i18n/vi_VN.csv
  - app/code/Secomm/Base/i18n/en_US.csv
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-07-21
supersedes: []
---

# [SLP][FEAT-YESRCX] Admin-configurable storefront announcement toggle (reuse Secomm_Base)

<!-- CANONICAL RECORD (Phase 1a) — Mode B standard feature. -->
<!-- RM-06: low risk, no risk category, reversible via config → implement KHÔNG cần pre-implementation TL approach approval; TL code review là default gate. -->
<!-- Scope clarification (stop-and-report resolved): "existing announcement block" không có sẵn → user duyệt tạo block mới trong Secomm_Base (reuse module + tab `secomm`). -->

## Context

Cần một **announcement/notice bar** trên storefront Hyvä, có thể **bật/tắt từ admin** (Stores > Configuration) mà không deploy code, **không** thêm DB table / schema / data patch, không chạm checkout/payment/security/auth/PII/API/integration/architecture, và **reversible** qua config.

**Reuse target:** module `Secomm_Base` đã có sẵn **tab `secomm`** ("Secomm Extensions") trong `etc/adminhtml/system.xml` (chưa có section nào) → thêm section/group/field Yes/No dưới tab này. Module đã registered + depends Magento_Catalog.

**Scope clarification:** requirement gốc nói "display the existing announcement block" nhưng khảo sát storefront (app/design + app/code/Secomm + Mageplaza; theme chưa có default.xml) cho thấy **không có announcement block nào tồn tại**. Stop-and-report → user duyệt **tạo block mới** trong Secomm_Base (như option A). `Mageplaza/Core/NoticeType` là admin feed, không liên quan.

## Requirements

- **AC-001** (Admin config): Stores > Configuration > SECOMM EXTENSIONS > Storefront > Announcement > **Enable Announcement** = Yes/No. Mặc định **No**.
- **AC-002** (Enable → render): khi Yes, announcement block render trên storefront (top-of-content, mọi page có `content.top`).
- **AC-003** (Disable → no render): khi No, block KHÔNG render (gating qua layout `ifconfig`; không load template khi disabled).
- **AC-004** (No DB/schema/patch): config lưu `core_config_data`; default từ `config.xml`. Không table mới, không schema/data patch.
- **AC-005** (No checkout/payment impact): block ở `content.top`; không chạm Mageplaza OSC / Mollie / TableRate (OSC có layout riêng, không render content.top block này).
- **AC-006** (No security/auth/PII/API/integration/arch): pure presentational; output escaped; không thu thập/thêm PII; không API/contract/architecture change.
- **AC-007** (Reversible): đổi Yes/No trong admin (flush cache) → bật/tắt ngay; không cần code change.
- **AC-008** (i18n — BR-001): chuỗi storefront có `vi_VN.csv` + `en_US.csv`.
- **AC-009** (Reuse): dùng module `Secomm_Base` + tab `secomm` sẵn có; không tạo module mới.

## Approach & Decisions

- **Config:** thêm `<section id="secomm_storefront">` → `<group id="announcement">` → `<field id="enable" type="select" source_model="Magento\Config\Model\Config\Source\Yesno">` vào `Secomm/Base/etc/adminhtml/system.xml` (dưới tab `secomm` sẵn có). `etc/config.xml` set default `<enable>0</enable>` (disabled).
- **Conditional render:** dùng **`ifconfig="secomm_storefront/announcement/enable"`** trên `<block>` trong `view/frontend/layout/default.xml` → Magento skip render khi disabled. **Không cần PHP/ViewModel** (no logic; pure layout gating) — minimal, idiomMagento.
- **Block class:** `Magento\Framework\View\Element\Template` + template `Secomm_Base::announcement.phtml` (no custom Block class needed).
- **Placement:** `referenceContainer name="content.top"` + `before="-"` (top-of-content, all standard pages).
- **i18n:** chuỗi `__('Welcome to our store!')` trong template; thêm `i18n/vi_VN.csv` + `i18n/en_US.csv` (storefront string, BR-001).
- **Không decision mới (DEC-xxx)** — tuân theo patterns sẵn có (config + ifconfig + template).

## Implementation Notes

- Files: xem `source_areas` frontmatter — all created/edited in `Secomm_Base`.
- `system.xml`: thêm `<section secomm_storefront>` → `<group announcement>` → `<field enable>` (Yesno source) dưới tab `secomm` sẵn có; không thêm `<resource>` (match existing pattern — tab chưa có acl).
- `config.xml`: mới (module chưa có); default `<enable>0</enable>` (disabled).
- `default.xml` + `announcement.phtml`: mới; block `Magento\Framework\View\Element\Template` gated bằng `ifconfig`; inline style tối giản (không phụ thuộc Tailwind purge); output escaped.
- i18n: `i18n/vi_VN.csv` + `en_US.csv` (storefront string + admin labels).
- Không DI change → không cần `setup:di:compile`. Không module version change → config.xml default picked up at runtime (flush config cache sau khi enable).

## Test Summary

Static validation (2026-07-21):
- XML well-formed (system.xml, config.xml, default.xml, module.xml) — ✅ all parse.
- **Config path nhất quán:** `secomm_storefront/announcement/enable` khớp trên system.xml field / config.xml default (`<enable>0</enable>`) / layout `ifconfig`. ✅
- PHP lint `announcement.phtml` — ✅ no syntax errors.
- Output escaped (`$block->escapeHtml`) — ✅ (XSS-safe, AC-006).
- Module registration intact (`Secomm_Base`) — ✅.
- Canonical record schema (Phase 1a `--check-records`) — ✅ VALID.

**Live verification pending Magento runtime** (no `bin/magento` at repo root in this env): admin toggle Yes/No + flush config cache + storefront render check (disabled→block absent at content.top; enabled→block present). To run when env available: `bin/magento cache:clean config; bin/magento config:set secomm_storefront/announcement/enable 1;` then load storefront.

## Compatibility Conclusions

- **Themes:** Hyvä ✅ (inline style, không phụ thuộc Tailwind/Alpine; cũng render trên Luma nếu active).
- **Browsers:** không browser-specific.
- **Modules affected:** `Secomm_Base` (additive: +config +layout +template +i18n); không sửa module khác.
- **API contracts:** none. **Schema:** none (core_config_data). **Security:** output escaped; no PII.
- **Checkout:** no impact (block ở content.top; OSC layout riêng).
- **Upgrade notes:** none.

## References

- Business rules: BR-001 (i18n vi/en) — `project-context/02_BUSINESS_RULES.md`.
- Coding standard: escape output, no logic in templates, ViewModel/Block minimal — `project-context/CODING_RULES.md`.
- Related record: FEAT-KQ6WC4 (broader proposal — new module `Secomm_StoreNotice` + configurable message text; remains **proposed**). FEAT-YESRCX implements the narrower admin-toggle, reusing the existing module.
- Template: `templates/feature-record-template.md`.
- Toolkit version: v4.0 · created via Phase 1a canonical-record flow 2026-07-21.
