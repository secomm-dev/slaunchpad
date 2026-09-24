# Global Style Foundation — Final Validation Summary

**Date:** 2026-09-23  
**Task:** `TASK-6V8H2P` / `SLP-246`  
**Verdict:** **IMPLEMENTATION COMPLETE — CONDITIONAL QA HANDOFF**

Report này hợp nhất
[pre-icon validation baseline](global-style-foundation-validation.md) và
[Icon Foundation audit](icon-foundation-audit.md). Các kiểm tra đã có evidence
được đánh dấu PASS; external browser/device/locale matrix không được suy diễn.

## Deterministic gates đã chạy lại

| Gate | Result | Evidence |
|---|---|---|
| `npm run tokens:audit` | PASS | 14 files, 1,100 records, 890 production records, 436 aliases resolved; zero JSON/invalid/unresolved-production errors |
| Clean generation #1 | PASS | `figma-design-tokens.css` SHA-256 `0b6825343c62d129dbd8f619334c1ec3b6243053c41e933fc0af764089cae71b` |
| Clean generation #2 | PASS | Cùng SHA-256 với generation #1 |
| Generated Hyvä tokens | PASS | SHA-256 `0cc956d9062335804d67d47a9038b74fe8bbcb67c298873b3f41694fa3b3eac4` sau cả hai lần generate |
| `npm run build` | PASS | Tailwind CSS v4.3.2, completed without error |
| Production CSS | PASS | SHA-256 `0be0e02e529d0fb18de486676633a2aedfc32d10a42554761812b8f2f8dc28cd` after final textarea/scoped-container refinement |
| `git diff --check` | PASS | Không có whitespace error |
| `.ai/bin/project-ai-validate --check-specs` | BLOCKED — pre-existing tooling defect | Unmatched single quote gần line 831; unexpected EOF gần line 856 |

Token audit vẫn ghi nhận đúng các warning đã accepted/excluded:

- Bốn source path `applications/Icon-Size` có casing không canonical.
- Mười hai CTA zero records ở Button lg/xl bị loại khỏi production generation.
- Dark mode chưa thuộc production allowlist.

## Icon Foundation

- Figma node `5:28677` đã audit: `2001:10642` là fill,
  `2001:13716` là outline.
- Fill representative scale đồng nhất; outline representative có optical stroke
  theo size.
- Workflow chốt export on demand, semantic path, exact optical size,
  `currentColor`, SVG safety và Hyvä `SvgIcons::renderHtml()`.
- Global Style không consume glyph cụ thể nên không thêm SVG asset. Consuming
  component chịu trách nhiệm export subset và visual evidence.

## Final runtime completion pass

Runtime QA trên local Magento phát hiện và sửa hai foundation defects:

1. `textarea.form-input` bị utility specificity giữ ở 44px. Textarea behavior đã
   được đưa vào `form-input-field`, dùng semantic
   `--form-textarea-min-height`; runtime Contact page hiện đúng 160px.
2. Tailwind core `.container` giữ nguyên ở các tier khác. Project chỉ override
   khoảng `80rem–95.999rem` thành outer max-width 1408px; từ `96rem`, native
   max-width 1536px hoạt động lại. Padding là 8px mobile và 24px từ tablet.

Responsive matrix sau fix:

| Viewport | Effective client width | Outer container | Padding | Content area |
|---:|---:|---:|---:|---:|
| 375 | 360 | 360 | 8px | 344 |
| 768 | 753 | 753 | 24px | 705 |
| 1024 | 1009 | 1009 | 24px | 961 |
| 1280 | 1265 | 1265 | 24px | 1217 |
| 1440 | 1425 | 1408 | 24px | 1360 |
| 1600 | 1585 | 1536 | 24px | 1488 |
| 1920 | 1905 | 1536 | 24px | 1488 |

Ở Figma frame 1440px không có scrollbar: outer margin là 16px, cộng padding
24px tạo content inset 40px. Browser runtime có vertical scrollbar nên effective
client width nhỏ hơn window width; đây không phải thay đổi container contract.

Additional runtime evidence:

- Contact textarea remains 160px at all eight viewports.
- Native checkbox renders 20×20px; product swatch/review radios retain their
  component-owned interaction geometry without document overflow.
- Vietnamese → English store-switch resolves correctly to `lang=en`, loads
  `en_US/css/styles.css`, and Contact textarea remains 160px.
- Input focus retains brand-primary border and 4px focus ring.
- Container box không overflow. Tại đúng breakpoint 1280px, Footer child hiện
  có overflow nội bộ khoảng 21px; đây là Footer composition follow-up, không
  được che bằng cách thay đổi Global Style container.

## Acceptance criteria

| AC | Status |
|---|---|
| AC-001…AC-007 | PASS; AC-006 includes eight-viewport runtime matrix |
| AC-008 | PARTIAL — Chrome focus/contrast/reduced-motion CSS pass; external browser/accessibility matrix pending |
| AC-009 | PARTIAL — build, routes, eight viewports, both locales, textarea and checkbox runtime pass; standalone native-radio visual matrix and external browsers pending |
| AC-010 | PASS |
| AC-011 | PASS |

## External QA còn lại

1. Safari, Firefox, Edge, Safari iOS và Chrome Android.
2. Runtime keyboard/state QA cho standalone native radio trên page có fixture
   phù hợp; product radios hiện tại là component-owned swatch/rating controls.
3. Forced-colors/high-contrast, reduced-motion emulation và automated contrast
   scan đầy đủ.
4. Checkout chỉ chạy visual regression; không dùng OSC làm nguồn foundation.

Các mục trên là release/QC follow-up, không phải implementation gap đã được che
giấu. Task chưa nên chuyển sang `done` cho tới khi TL chấp nhận conditional
handoff hoặc external QA matrix hoàn tất.
