---
id: TASK-6V8H2P
type: task
title: Hyvä Global Style Foundation for Secomm Launchpad Core
project_code: SLP
parent: null
external_refs:
  xcorp: SLP-246
mode: A
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-6V8H2P-hyva-global-style-foundation.md
risk: medium
status: in_progress
created: 2026-09-19
updated: 2026-09-23
decisions: []
decision_assessment: material
components:
  - CMP-THEME
source_areas:
  - app/design/frontend/Secomm/launchpad/design-tokens/
  - app/design/frontend/Secomm/launchpad/web/tailwind/
  - app/design/frontend/Secomm/launchpad/web/fonts/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
last_verified: 2026-09-19
supersedes: []
---

# [SLP][TASK-6V8H2P] Hyvä Global Style Foundation

> External PM reference: `SLP-246`.

## Mini Spec

### Goal

Xây dựng foundation global style có thể tái sử dụng cho theme
`Secomm/launchpad`, dựa trên Figma Variables export final, Hyvä 3.x và
Tailwind CSS v4 CSS-first. Foundation là lớp core để các theme ngành hàng kế
thừa và override bằng semantic/brand tokens thay vì copy global CSS.

### Expected Behavior

1. Theme generate deterministic CSS từ immutable token export bằng transformer
   do theme sở hữu.
2. Production chỉ kích hoạt `light` + `olive` + `Inter`; `dark` không được phát
   hành như runtime mode hiện tại.
3. Semantic color, typography, spacing, radius, effects, container, focus,
   Button, Form và Hyvä custom-icon contract tích hợp qua patterns hiện có.
4. Mobile typography là base, Tablet kích hoạt tại 768px và Desktop tại 1280px.
5. Foundation pass token audit, repeat-generation, Tailwind build, responsive,
   locale và accessibility validation đã định nghĩa trong input.

### Constraints / Rules

- Không tạo `tailwind.config.js`.
- Không chỉnh tay token export hoặc generated CSS.
- Không generate dark mode, raw palette utilities hoặc suspicious CTA zero tokens.
- Alias brand phải tiếp tục là CSS variable reference để theme con override được.
- Header, Menu, Footer và page/component composition là workstream riêng.
- Form controls dùng final Figma Form Elements node `2410:14322`; Select được
  audit từ các component con trong node này trước khi chốt semantic API.
- Custom icons dùng Figma library `5:28677`, được thêm on demand qua
  `Hyva_Theme/web/svg/{style}/{semantic-name}.svg` và Hyvä `SvgIcons`; không
  bulk-export library hoặc dùng runtime Figma URL.

### Out of Scope

- Header, navigation/menu và Footer implementation.
- Dark mode runtime, alternate child-theme brand và page composition.
- Checkout-specific redesign hoặc sửa module third-party.
- Component/page composition ngoài Form foundation.

### Acceptance Criteria

- [x] AC-001: Token audit parse đủ 14 file/1.100 records và fail trên JSON,
  collision, invalid value hoặc alias không resolve được theo approved contract.
- [x] AC-002: Hai lần generate liên tiếp byte-identical; generated CSS không được
  chỉnh tay và normal build không ghi audit evidence.
- [x] AC-003: Production output chỉ active `light`, `olive`, `Inter` và typography
  Mobile/Tablet/Desktop tại breakpoint đã duyệt.
- [x] AC-004: Stable semantic tokens được map vào Tailwind/Hyvä hooks; raw
  primitives, dark mode và CTA zero tokens không tạo public utilities.
- [x] AC-005: Inter self-host WOFF2 hỗ trợ latin/vietnamese, normal/italic và
  weights 400/500/600/700; loading không gây duplicate external request.
- [x] AC-006: Container giữ Hyvä max-width tiers dưới 80rem và từ 96rem,
  resolve padding 8/24px, chỉ override tier 80rem thành outer 1408px/content
  1360px;
  full-width shells và documented component exceptions vẫn hoạt động.
- [x] AC-007: Button API có primary/secondary/tertiary/transparent, approved states và size
  strategy; các conflict 2XL/touch target được quyết định trước code tương ứng.
- [ ] AC-008: Focus-visible, reduced-motion, contrast và keyboard checks đáp ứng
  WCAG 2.2 AA contract.
- [ ] AC-009: Final Input/Textarea/Checkbox/Radio states map vào native
  Magento/Hyvä markup; `npm run build` pass và representative
  pages/locales/viewports không có global-style regression; checkout chỉ
  regression-check.
- [x] AC-010: Evidence phân biệt verified/inference/unresolved và ghi rõ mọi
  documented deviation trước TL review.
- [x] AC-011: Hyvä custom-icon workflow được audit với node `5:28677`, quy định
  semantic naming/storage, on-demand export, SVG security/currentColor, explicit
  dimensions và accessibility; mọi asset được thêm trong scope phải có
  component-level visual evidence, không bulk-export library.

## Artifacts

- Input: [Secomm Launchpad Global Style Foundation Input](../../project/design/secomm-launchpad-global-style-foundation-input.md)
- Specification: [SPEC-TASK-6V8H2P](../../specs/SPEC-TASK-6V8H2P-hyva-global-style-foundation.md)
- Draft plan: [TASK-6V8H2P Implementation Plan](../../plans/TASK-6V8H2P-implementation-plan.md)
- Related analysis: [Header, Menu and Footer](../../project/design/secomm-launchpad-header-menu-footer-analysis.md)
- Foundation validation baseline: [Pre-icon validation evidence](../../evidence/TASK-6V8H2P/global-style-foundation-validation.md)
- Final validation summary: [Global Style Foundation Final Validation](../../evidence/TASK-6V8H2P/global-style-foundation-final-validation.md)
- Icon workflow: [Hyvä Custom Icon Workflow](../../guides/HYVA_CUSTOM_ICON_WORKFLOW.md)
- Icon audit: [Launchpad Core Icon Foundation Audit](../../evidence/TASK-6V8H2P/icon-foundation-audit.md)

## Readiness

`Implementation complete — conditional QA handoff`. Token/CSS/Icon Foundation
implementation and deterministic gates are complete. AC-001…AC-007,
AC-010 and AC-011 pass. AC-008/AC-009 remain open for the
external cross-browser/accessibility matrix and standalone native-radio runtime
QA. The eight-viewport matrix, English store switch, textarea and checkbox now
pass locally. See the validation evidence for
the verified, inferred and unresolved split. Global Style does not itself consume
a concrete glyph, so no SVG asset was added in the Icon Foundation phase.
