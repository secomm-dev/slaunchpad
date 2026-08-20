---
id: FEAT-KQ6WC4
type: feature
project_code: SLP
parent: null
legacy_ids: [FEAT-002]
title: Configurable storefront notice
mode: B
risk: low
status: proposed             # record = plan/approach; implementation follows
created: 2026-07-21
updated: 2026-07-21
ticket_ref:                  # new work — no legacy ticket
decisions: []                # no architectural decision — follows existing patterns
# Knowledge-consolidation contract (RM-07)
components:
  - CMP-STOREFRONT           # storefront notice component (see .ai/project/COMPONENT_INDEX.md)
source_areas:
  - app/code/Secomm/StoreNotice/etc/module.xml
  - app/code/Secomm/StoreNotice/etc/config.xml
  - app/code/Secomm/StoreNotice/etc/system.xml
  - app/code/Secomm/StoreNotice/ViewModel/Notice.php
  - app/code/Secomm/StoreNotice/view/frontend/layout/default.xml
  - app/code/Secomm/StoreNotice/view/frontend/templates/notice.phtml
  - app/code/Secomm/StoreNotice/i18n/en_US.csv
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-07-21
supersedes: []
---

# [SLP][FEAT-KQ6WC4] Configurable storefront notice

<!-- CANONICAL RECORD (Phase 1a) — Mode B standard feature. -->
<!-- RM-06: low risk, clear AC, no architecture/contract/irreversible change → Developer có thể implement KHÔNG cần pre-implementation TL approach approval; TL code review là default gate. -->
<!-- KHÔNG affect: payment, DB schema, security-surface, API contract, architecture. -->

## Context

Cần một **notice/banner** hiển thị trên storefront Hyvä (`Secomm/launchpad`) — ví dụ thông báo khuyến mãi, lịch nghỉ lễ, hoặc thông điệp tạm thời — có thể **bật/tắt và soạn nội dung từ admin** mà không cần deploy code. Notice là **presentational**, không chạm payment/checkout/schema/security/API/architecture.

**Business driver:** marketing/operations muốn tự thay đổi thông điệp storefront theo chiến dịch mà không phụ thuộc developer release.

**Scope:** Hyvä storefront only (project storefront = Hyvä; Luma dormant → không cần dual-theme cho notice). Notice vị trí top-of-page, không overlap checkout critical path.

## Requirements

- **AC-001** (Admin enable/disable): Stores → Configuration → Secomm → Storefront Notice → **Enable** = Yes/No. Mặc định **No**.
- **AC-002** (Message per store view — BR-001): textarea nội dung notice, **scope = Store View** → store view `vi_VN` hiển thị text vi, store view `en_US` hiển thị text en.
- **AC-003** (Render trên Hyva): khi Enable = Yes, notice render trên mọi storefront page, vị trí top-of-page (trên/trên dưới header) trên theme `Secomm/launchpad`.
- **AC-004** (Dismissible, keyed by message): notice có nút đóng; trạng thái đóng lưu trong **localStorage** keyed theo hash nội dung → dismiss áp dụng cho message hiện tại; **đổi message → hiện lại**.
- **AC-005** (Disabled = no leak): khi Enable = No, không render notice và không load JS/CSS liên quan lên storefront.
- **AC-006** (No checkout interference): notice không ảnh hưởng Mageplaza OSC / Mollie / TableRate (AC-004 BR-003/006 regression check).
- **AC-007** (Tailwind v4 CSS-first): styling qua `@theme`/`@source` trong `tailwind-source.css` của theme; **không tạo** `tailwind.config.js`.
- **AC-008** (XSS-safe): nội dung admin-entered **escape output** (HTML escape); không render raw HTML.

## Approach & Decisions

Module mới **`Secomm_StoreNotice`** (vendor prefix `Secomm_` per AGENTS.md §7.1). Presentational-only, Hyvä-first.

- **Config:** `etc/config.xml` (default Enable=0) + `etc/system.xml` (admin: enable yesno + message textarea, Store View scope). Lưu `core_config_data` → **không schema change**.
- **Render:** `view/frontend/layout/default.xml` referenceContainer top-of-page → block dùng **ViewModel** (`ViewModel/Notice.php`, prefer over Block per coding rules) đọc config + escape message → `templates/notice.phtml`. Layout `default` → mọi page.
- **Dismissible:** Alpine.js component trong `notice.phtml` (`x-data`/`x-show`/`@click`) + localStorage key `secomm_notice_dismissed_<hash>`.
- **i18n:** message là admin-entered per store view → **không cần** storefront `vi_VN.csv`/`en_US.csv` cho message. Admin field labels cần i18n (module `i18n/en_US.csv`, admin-only — không conflict storefront).
- **Không decision mới** — feature tuân theo patterns hiện có (ViewModel + layout + store config); không architecture decision (DEC-xxx).

## Implementation Notes

_Status: **proposed** — chưa implement._ Planned files: xem `source_areas` frontmatter (module `Secomm_StoreNotice`: module.xml, config.xml, system.xml, ViewModel, layout default.xml, notice.phtml, i18n). Implement theo AGENTS.md §7 planning-first (record này = plan/approach). *(Cập nhật section này khi implement xong: files touched, patterns, bất kỳ approach change.)*

## Test Summary

_Status: **proposed** — chưa validate._ Planned QC: enable+render (AC-001/003) · per-store-view vi/en text (AC-002) · dismiss + re-show on message change (AC-004) · disabled=no leak (AC-005) · checkout smoke unaffected (AC-006) · Tailwind purge check (AC-007) · XSS payload escaped (AC-008). *(Cập nhật khi QC xong.)*

## Compatibility Conclusions

- **Themes:** Hyvä ✅ (target); Luma N/A (dormant — không triển khai dual-theme cho notice).
- **Browsers:** không browser-specific (server-rendered + Alpine.js).
- **Modules affected:** mới `Secomm_StoreNotice`; không sửa module khác.
- **API contracts:** none. **Schema:** none (core_config_data). **Security:** output escaped (AC-008); không thu thập/thêm PII.
- **Upgrade notes:** none.

## References

- Business rules: BR-001 (i18n vi/en) — `project-context/02_BUSINESS_RULES.md`.
- Coding standard: ViewModel over Block, escape output, Tailwind v4 CSS-first, no logic in templates — `project-context/CODING_RULES.md`.
- Template: `templates/feature-record-template.md`.
- Related records: none (FEAT-YVN39K = address module; không liên quan).
- Toolkit version: v4.0 · created via Phase 1a canonical-record flow 2026-07-21.
