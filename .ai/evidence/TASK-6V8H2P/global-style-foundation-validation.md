# Global Style Foundation — Pre-Icon Validation Baseline

Date: 2026-09-23  
Task: `TASK-6V8H2P` / `SLP-246`  
Verdict: **CONDITIONAL PASS for token/CSS/Button/Form foundation; Icon Foundation
was added to scope afterward and requires separate audit/evidence**

## 1. Static and deterministic gates

### Verified

- `npm run tokens:audit`: PASS.
  - 14 source files.
  - 1,100 mode-expanded records.
  - 890 production records.
  - 436 aliases resolved by exact collection/path fallback.
  - Zero JSON errors, invalid values, normalized casing conflicts or unresolved
    production aliases.
- Production allowlist is `light` + `olive` + `Inter` +
  `Mobile/Tablet/Desktop` + Button `sm/md/lg/xl` export modes.
- Audit-only/excluded input remains excluded from production behavior:
  `colors/dark.tokens.json`, documentation-only `other/*` values and Button CTA
  zero modes.
- Two independent token generations are byte-identical.
- Generated token CSS SHA-256:
  `0b6825343c62d129dbd8f619334c1ec3b6243053c41e933fc0af764089cae71b`.
- `npm run build`: PASS with Tailwind CSS v4.3.2.
- Production CSS SHA-256 after repeated build:
  `a44e92822a75a2edec681ce797cb4f8ecb7140190448303a61ed593ee5206920`.
- `git diff --check`: PASS.
- Generated token CSS contains no runtime dark selector and no public CTA token.
- Raw source variables needed to preserve alias chains are emitted in the
  generated source layer; only the approved semantic subset is mapped into
  Tailwind `@theme` namespaces.

### Known input warnings — accepted/excluded

- Four non-canonical `applications/Icon-Size` source names are preserved because
  source export is immutable.
- Twelve suspicious Button CTA lg/xl zero records remain audit findings and are
  excluded from production generation.

## 2. Font validation

### Verified

- Eight static WOFF2 assets exist: normal/italic × 400/500/600/700.
- WOFF2 total payload is approximately 224 KiB.
- Inter OFL license is retained beside the assets.
- Every face uses `font-display: swap`.
- Runtime computed family is
  `Inter, ui-sans-serif, system-ui, sans-serif`.
- Browser FontFaceSet reports Inter 400 and 700 loaded.
- HTTP validation for `inter-400-normal.woff2`: `200`, `font/woff2`, immutable-like
  one-year public cache and no CORS/path failure.

## 3. Semantic and responsive contract

### Verified

- Runtime desktop semantic values resolve to the approved light/Olive values,
  including brand action `#45744c` and brand border `#293e2d`.
- Responsive typography CSS is mobile-first, switches to Tablet at 768px and
  Desktop at 1280px.
- Runtime at the tested 1815px browser viewport resolves Body 1 to 18px/28px and
  Heading 1 tokens to 60px/72px.
- Container contract was refined during final runtime QA. The final source keeps
  Hyvä/Tailwind max-width behavior below 80rem, uses 8px mobile padding and 24px
  padding from 768px, then overrides the 80rem container outer max to 1408px so
  its desktop content area is 1360px. See final validation evidence.
- No horizontal document overflow was found on the tested customer form route.
- Component/page classes may intentionally override the global container; the
  existing Header and registration composition exceptions are outside this
  foundation ticket.

### Inferred from compiled CSS, pending device-matrix confirmation

- Mobile 320/375 and Tablet 768/1024 container/typography transitions are
  structurally present in compiled CSS, but exact runtime measurements still
  require the external device/browser QA matrix.

## 4. Accessibility foundation

### Verified

- Keyboard focus on a native form input renders the approved 4px focus ring and
  brand-primary border.
- Reduced-motion CSS disables animation/transition duration and smooth scrolling
  when `prefers-reduced-motion: reduce` matches.
- Representative contrast calculations:
  - white on primary action: 5.45:1;
  - primary text on white: 17.75:1;
  - secondary text on white: 10.30:1;
  - error text on white: 6.57:1;
  - focus border on white: 11.53:1.
- These representative foreground/background pairs pass WCAG 2.2 AA normal-text
  contrast thresholds.

### Pending external QA

- Safari, Firefox, Edge, Safari iOS and Chrome Android keyboard/touch checks.
- Forced-colors/high-contrast inspection and full automated contrast scan.
- Runtime reduced-motion emulation.

## 5. Button and Form validation

Detailed evidence:

- `button-foundation-validation.md`
- `form-foundation-validation.md`

### Verified

- Button API and approved five component sizes are implemented from the current
  master, with the small-control touch-target strategy documented.
- Form input runtime: 44px height, 14px × 10px padding, 6px radius, Body 2
  typography and semantic default/focus colors.
- Label runtime: 14px/20px, 6px field gap and gray-quaternary color.
- Magento `role="switch"` controls retain their dedicated 36px × 20px geometry.
- Select consumes only the shared native field foundation.

### Documented deviation / unresolved design input

- No standalone final Select master exists. Select-specific chrome and variants
  remain gated; no missing design was invented.

## 6. Representative Magento regression

### Route health

| Route | Result |
|---|---:|
| `/` | 200 |
| `/customer-service` | 200 |
| `/customer/account/login/` | 200 |
| `/customer/account/create/` | 200 |
| `/living-room` | 200 |
| `/atlas-pouf` | 200 |
| `/checkout/cart/` | 200 |
| `/checkout/` | 302 — expected guest checkout redirect; regression-only |

### Runtime visual inspection

- Chrome desktop customer registration page loads compiled theme CSS and local
  Inter without missing assets.
- Input focus, field dimensions, typography and surrounding layout were visually
  inspected.
- No desktop horizontal overflow or form layout regression was found.

### Locale blocker

- Magento reports active stores `default/vi_VN` and `launchpad_en/en_US`.
- The local English store redirect currently returns to the Vietnamese store and
  strips the English store selection. Therefore English expansion/runtime visual
  QA is **unresolved environment/config evidence**, not marked as passed.

## 7. Acceptance-criteria matrix

| AC | Status | Evidence |
|---|---|---|
| AC-001 | PASS | Token audit inventory and fatal checks |
| AC-002 | PASS | Byte comparison and stable hash |
| AC-003 | PASS | Contract + generated mode inspection |
| AC-004 | PASS | Semantic `@theme` mapping; exclusions verified |
| AC-005 | PASS | Eight WOFF2 faces, license, runtime HTTP/font checks |
| AC-006 | PASS with external viewport confirmation pending | Source/compiled breakpoints + desktop runtime measurement |
| AC-007 | PASS | Button evidence and recorded conflict decisions |
| AC-008 | PARTIAL | Chrome focus + representative contrast + reduced-motion CSS pass; full browser matrix pending |
| AC-009 | PARTIAL | Build, route health and Chrome form runtime pass; cross-viewport/locale/control matrix pending |
| AC-010 | PASS | This report explicitly separates verified, inferred, pending and unresolved findings |
| AC-011 | PENDING | Added after this validation baseline; requires icon-library audit and component-level SVG evidence |

## 8. Tooling limitation

`.ai/bin/project-ai-validate --check-specs` cannot complete because the checked-in
validator has an unmatched single quote near line 831 and reaches unexpected EOF
near line 856. This is a pre-existing toolkit defect outside the theme scope.

## 9. TL handoff / remaining QA

Before changing the task from `in_progress` to `done`:

1. Run visual/reflow QA at 320, 375, 768, 1024, 1280, 1440, 1600 and 1920 CSS px.
2. Cover latest Chrome, Safari, Firefox and Edge plus Safari iOS and Chrome Android.
3. Restore/confirm the English store-switch flow and repeat representative route
   checks under `en_US`.
4. Include a page with native textarea, checkbox and radio for keyboard/state QA.
5. Perform checkout visual regression only; do not derive foundation visuals from
   Mageplaza OSC.
6. Repair the project validator separately, then rerun `--check-specs`.
7. Audit Figma icon library `5:28677` and produce separate Icon Foundation
   evidence before closing AC-011.
