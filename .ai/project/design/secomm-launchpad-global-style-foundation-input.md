# Secomm Launchpad Core — Hyvä Global Style Foundation Input

> Project input record được tạo từ reusable Hyvä Global Style Foundation
> template cho Magento 2, Hyvä và Tailwind CSS v4 CSS-first. Giữ `TBD` cho
> thông tin chưa có đủ evidence hoặc chưa được người có thẩm quyền quyết định.
>
> File đã điền là đầu vào cho skill `hyva-global-style-foundation`; nó không
> thay thế Full Spec, ticket, acceptance criteria hoặc implementation plan theo
> governance của project.

## 0. Metadata

| Field | Value |
|---|---|
| Project | `Secomm Launchpad` |
| Theme/Foundation name | `Secomm Launchpad Core — Global Style Foundation` |
| Input owner | `Frontend Developer` |
| Designer / design approver | `Designer` |
| Technical approver | `Tech Lead` |
| Design status | `Final design supplied; foundation and Hyvä custom-icon contract approved; representative icon vector audit complete` |
| Input version | `1.3 — Hyvä custom-icon foundation added; on-demand policy approved` |
| Last updated | `2026-09-23` |
| Related ticket / feature | `TASK-6V8H2P` (external PM reference `SLP-246`) |

## 1. Target Theme and Product Ownership

### 1.1 Theme inheritance

| Field | Value |
|---|---|
| Target Magento theme | `Secomm/launchpad` |
| Target theme path | `app/design/frontend/Secomm/launchpad` |
| Parent theme | `Hyva/default` |
| Tailwind version | `4.x` |
| Tailwind entry file | `web/tailwind/tailwind-source.css` |
| Store view(s) | `default (Vietnamese), launchpad_en (English)` |
| Locale(s) | `vi_VN, en_US` |

### 1.2 Product layer

Chọn đúng một ownership chính:

- [x] Commerce Core
- [ ] Core Add-on
- [ ] Industry Core — tên ngành: `TBD`
- [ ] Industry Add-on — tên ngành/add-on: `TBD`
- [ ] Customer-specific theme

Các theme dự kiến kế thừa foundation này:

- `Secomm/launchpad_fashion` — existing child/variant; additional industry themes will be created later

Các giá trị hoặc hành vi đặc thù ngành/khách hàng không được đưa vào foundation:

- `TBD`

## 2. Scope Contract

### 2.1 In scope

Đánh dấu các phần thuộc lần triển khai này:

- [x] Immutable Figma/DTCG token source
- [x] Token audit và deterministic token transformer
- [x] Production mode allowlist
- [x] Font loading và font-family mapping
- [x] Primitive-to-semantic color mapping
- [x] Typography foundation
- [x] Spacing scale
- [x] Border width và radius
- [x] Shadows/effects
- [x] Breakpoints
- [x] Container và gutters
- [x] Global document/body defaults
- [x] Global link và focus treatment
- [x] Global Button API/styles
- [x] Global Form API/styles
- [x] Hyvä custom-icon workflow và icon-library contract
- [x] Supporting primitives thực sự được Button/Form sử dụng
- [x] Representative-page validation

### 2.2 Out of scope

Mặc định không thuộc foundation này:

- Header implementation
- Footer implementation
- Navigation/menu implementation
- Full component implementation ngoài Button/Form foundation
- Page composition
- CMS content authoring
- Checkout-specific customization
- Business behavior hoặc backend feature
- Dark mode hoặc alternate brand chưa được production-approved

Ngoại lệ đã được phê duyệt, nếu có:

- `None`

`Global Button/Form API/styles` chỉ bao gồm token mapping, Hyvä hooks và global
states của native controls. Component composition và page-specific variants
vẫn nằm ngoài scope.

## 3. Figma Sources

> Dùng URL có `node-id` cụ thể. Không chỉ cung cấp link root của Figma file.

| Input | Figma URL / node ID | Status | Notes |
|---|---|---|---|
| Foundations overview | [Colors page / Global Colors](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=5-11305&m=dev) (`5:11305`) | `Final design supplied; contract audit pending` | Master Components deep-link `2151:9517` resolves to Header only; foundation nodes were discovered via read-only MCP inventory |
| Color primitives | [Global Colors](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=5-11305&m=dev) (`5:11305`) | `Final design supplied` | Visual primitive palette; canonical values/aliases will come from variables export |
| Semantic colors | [Semantic Colors](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=5-14540&m=dev) (`5:14540`) | `Final design supplied` | Related nodes: Theme `5:16330`, Text `5:18447`, Border `5:20240`, Foreground `5:21930`, Background `5:23671`, Other `5:25599` |
| Typography | [Typography page](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=5-26608&m=dev) (`5:26608`) | `Variables discovered; visual page empty` | Local collection `3. typography`: 80 variables; modes Desktop, Tablet, Mobile. Export remains source input |
| Spacing/sizing | [Global Size](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=5-32489&m=dev) (`5:32489`) | `Final design supplied` | Related semantic Size node `5:34376`; values to verify against export collection `6. sizes` |
| Radius/borders | [Size](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=5-34376&m=dev) (`5:34376`) | `Final design supplied` | Includes spacing/radius presentation; semantic border colors are in node `5:20240` |
| Shadows/effects | [Effects / Content Container](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=5-39321&m=dev) (`5:39321`) | `Final design supplied` | Shadows/effects presentation; canonical aliases will come from export collection `7. effects` |
| Grid/container | [Desktop representative frame](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2073-13426&m=dev) (`2073:13426`); [Mobile representative frame](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2142-2918&m=dev) (`2142:2918`) | `Approved from measured usage` | Desktop repeatedly uses 40px outer inset and 1360px content at 1440px; primary mobile page content repeatedly uses 8px inset at 375px |
| Button master component | [Button page](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2410-25885&m=dev) (`2410:25885`); Button `2410:25972`; Base Button `2410:26973` | `Final design supplied; canonical sources confirmed` | The page is the parent inventory. Current Button owns visual variants/states; Base Button owns S/M/L/XL/2XL and icon placement geometry. Legacy Button `2410:25890` and Legacy Icon Button `2410:25923` are excluded. |
| Form Input/Textarea components | [Input (Form) page](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2410-14322&p=f) (`2410:14322`); Input `2410:14473`; Textarea `2410:14352`; base Input `2410:15699`; base Textarea `2410:15674` | `Final design supplied for Input/Textarea` | Page inventory contains 600 Input variants, 60 Textarea variants, 16 base Input combinations and 4 base Textarea feedback combinations. Legacy sets `2410:14324`/`2410:14331` are excluded. No standalone Select component exists on this page. |
| Form Checkbox/Radio components | [Radiobutton](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2174-30745&m=dev) (`2174:30745`); [Checkbox](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2174-32588&m=dev) (`2174:32588`); Base CheckRadio `2174:33021` | `Final design supplied` | All belong to Button page `2410:25885`. Integrate through native controls and existing Hyvä form hooks; retain semantic focus/feedback behavior. |
| Link/focus examples | [Current Button set](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2410-25972&p=f&m=dev) (`2410:25972`) | `Partial` | Button/Checkbox/Radio expose Focus states; no standalone Link foundation node was found |
| Icon library | [Icons page](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=5-28677&m=dev) (`5:28677`) | `Audited; workflow ready` | `2001:10642` contains fill and `2001:13716` contains outline; use on-demand Hyvä workflow and exact size for optically adjusted outline assets |

### 3.1 Deferred component references supplied with this input

These nodes are recorded now so later Header/Footer/Menu work items can reuse
the same design inventory. They are **not** part of the current Global Style
implementation scope. Their token and typography usage may be used only as
supplemental evidence while auditing the foundation.

| Area | Intended viewport | Figma node | MCP observation | Current status |
|---|---|---|---|---|
| Header / navigation | Desktop variants | [2151:9517](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2151-9517&m=dev) | `Header`, 1440×64 variants; light/dark visual variants | Deferred — separate Header/Menu work item |
| Newsletter | Desktop | [2151:17272](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2151-17272&m=dev) | Horizontal form; Heading 6, Body 2, Button base; semantic bg/border/brand tokens | Deferred — separate Footer work item |
| Main footer | Desktop | [2151:17280](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2151-17280&m=dev) | Four responsive link columns at 1440px | Deferred — separate Footer work item |
| Payment section | Desktop | [2174:34539](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2174-34539&m=dev) | Accreditation artwork plus eight payment marks; visible on Desktop | Deferred — separate Footer work item |
| Bottom footer | Desktop | [2151:17305](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2151-17305&m=dev) | Horizontal copyright and legal links; Body 3 | Deferred — separate Footer work item |
| Newsletter | Mobile | [2151:17058](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2151-17058&m=dev) | Stacked full-width form; responsive Heading 6 usage | Deferred — separate Footer work item |
| Main footer | Mobile | [2151:17066](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2151-17066&m=dev) | Four-section accordion; one representative open state | Deferred — separate Footer work item |
| Payment section | Mobile | `Hidden by design decision` | Desktop payment content is not rendered in the Mobile footer | Deferred — separate Footer work item |
| Bottom footer | Mobile | [2151:17095](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2151-17095&m=dev) | Centered stacked copyright and legal links; Body 3 | Deferred — separate Footer work item |

### 3.2 Representative responsive frames

| Viewport | Page/frame | Figma URL / node ID | Designed width |
|---|---|---|---|
| Desktop | `Launchpad core / desktop` | [2073:13426](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2073-13426&m=dev) | `1440 px` |
| Tablet | `Not supplied — FE foundation contract derived between approved mobile/desktop frames` | `No dedicated node` | `Validate at 768px and 1024px` |
| Mobile | `Launchpad core / Home page` | [2142:2918](https://www.figma.com/design/5MpBw9VaNgFdct3UDWABuV/LAUNCHPAD-CORE?node-id=2142-2918&m=dev) | `375 px` |

Mục đích của các frame này là validate foundation; chúng không đưa toàn bộ page
composition vào scope.

## 4. Variables and Token Export

| Field | Value |
|---|---|
| Export path / attachment | `app/design/frontend/Secomm/launchpad/design-tokens/source/` |
| Export format | `Figma Variables JSON using DTCG-style $type/$value fields plus com.figma extension metadata` |
| Export tool/plugin and version | `Figma native Variables export; separate plugin/version not applicable` |
| Exported at | `2026-09-19 — sizes collection re-export verified; full-export release reference remains optional` |
| Matches final design | `Structurally matches the final Figma collection inventory; design-owner confirmation still required` |
| Collections included | `1. primitives (373); 2. semantics (46); 3. typography (80 × Desktop/Tablet/Mobile); 4. theme (22 × olive); 5. colors (136 × light/dark); 6. sizes (39); 7. effects (60); 8. buttons (12 × sm/md/lg/xl)` |
| Known incomplete collections | `Effects export contains geometry only while effect colors come from color-mode tokens; applications/Icon-Size casing is not canonical; Button application modes do not map one-to-one by name to the five canonical component sizes` |
| Documentation-only collections | `buttons/cta is excluded from the current Global Button contract until a dedicated CTA Button master component exists; current CTA lg/xl zero values must not generate production utilities` |

Initial immutable-input audit:

- 14 JSON files and 1,100 mode-expanded token records parse successfully.
- Every token record retains a Figma variable ID; 612 records retain alias metadata.
- Mode schemas are complete: light/dark each expose the same 136 names,
  typography modes each expose the same 80 names, and button modes each expose
  the same 12 names.
- Repeated variable IDs across files represent the same Figma variable exported
  once per mode and are expected; they are not treated as duplicate definitions.
- Corrected `spacing/9xl` export was verified as `80`, retaining its alias to
  `global_sizes/12` rather than becoming a raw local override.
- `.DS_Store` is local filesystem metadata and is not part of the token source
  contract.

Rules:

- Export files là immutable input, không chỉnh tay hoặc normalize in place.
- Giữ nguyên collection, modes, aliases và Figma variable IDs nếu export hỗ trợ.
- Ghi rõ export mới thay thế toàn bộ hay chỉ cập nhật một phần export trước.

## 5. Production Mode Allowlist

Chỉ các mode được duyệt dưới đây mới được generate vào production CSS.

| Role | Approved production mode | Audit-only modes | Rationale / approval |
|---|---|---|---|
| Color | `light` | `dark` | Approved from Ready-to-Dev Homepage resolved mode and confirmed by export |
| Brand | `olive` | `None currently; future child-theme brands deferred` | Approved Launchpad Core brand mode and confirmed by export |
| Font | `Inter` | `None currently` | All 48 typography font-family records across three modes resolve to Inter |
| Typography base | `Mobile` | `None` | Proposed mobile-first CSS base; final breakpoint contract still requires approval |
| Typography responsive | `Tablet, Desktop` | `None` | Export contains complete Mobile/Tablet/Desktop schemas; activation breakpoints remain to be approved |

Dark mode:

- [ ] Không có
- [x] Có trong export nhưng audit-only
- [ ] Được duyệt cho production — approval: `TBD`

## 6. Font Contract

| Field | Value |
|---|---|
| Primary font family | `Inter — export-confirmed; font delivery contract pending` |
| Secondary/display font | `None \| TBD` |
| Required weights | `400, 500, 600, 700; exported foundation typography currently consumes 400, 500 and 700` |
| Styles | `normal, italic` |
| Font source | `Google Webfonts Helper: https://gwfh.mranftl.com/fonts/inter` |
| File format | `Static woff2 per approved weight/style/subset, matching the POC delivery approach` |
| Required subsets | `latin, vietnamese` |
| Fallback stack | `Inter, ui-sans-serif, system-ui, sans-serif` |
| Self-hosting permitted | `Yes — approved POC approach` |
| License evidence/location | `TBD — retain the Inter license notice with downloaded font assets` |
| Loading strategy | `Self-host; preload only the critical normal face (proposed 400 normal), use font-display: swap for all faces; final asset filenames to verify after download` |

## 7. Semantic Color Contract

> Không cần copy toàn bộ primitive palette vào bảng này. Ghi semantic roles mà
> component và Hyvä hooks được phép sử dụng.

| Semantic role | Figma variable/token | Usage | Status |
|---|---|---|---|
| Primary action | `bg/brand-solid-primary` | Button/action background | `Export-confirmed; component usage to validate` |
| On primary | `text/white`, `fg/white` | Text/icon on primary action | `Export-confirmed` |
| Secondary action | `bg/brand-soft-primary`, `border/brand-primary` | Secondary/soft brand actions | `Export-confirmed; component usage to validate` |
| Page background | `bg/gray-soft-primary` | Document background | `Export-confirmed` |
| Surface | `bg/white`, `bg/gray-soft-secondary` | Cards/forms/surfaces | `Export-confirmed` |
| Primary text | `text/gray-primary` | Main content | `Export-confirmed` |
| Secondary text | `text/gray-secondary`, `text/gray-tertiary` | Supporting content | `Export-confirmed` |
| Border | `border/gray-primary`, `border/gray-secondary` | Default/disabled border | `Export-confirmed` |
| Focus | `effects/focus_ring/primary`, `interaction/focus` | Focus outline/ring | `Export-confirmed; WCAG contrast to validate` |
| Error | `text/error-secondary`, `border/error-primary`, `bg/error-soft-primary` | Error state | `Export-confirmed` |
| Success | `text/success`, `border/success`, `bg/success-soft-primary` | Success state | `Export-confirmed` |
| Warning | `text/warning`, `border/warning`, `bg/warning-soft-primary` | Warning state | `Export-confirmed` |
| Disabled | `text/gray-quinary`, `fg/gray-tertiary`, `bg/gray-soft-tertiary`, `interaction/disable` | Disabled content/control | `Export-confirmed` |

Additional semantic roles:

- `TBD`

## 8. Typography Contract

| Style/role | Figma token/style | Mobile | Desktop | Notes |
|---|---|---|---|---|
| Body | `body-1`, `body-2` | `16/24/400` | `18/28/400`, `16/24/400` | Body 1 changes responsively; Body 2 is stable |
| Body small | `body-3` | `14/20/400` | `14/20/400` | Stable across modes |
| Label | `label-xl/l/m/s` | `16/28`, `14/20`, `14/20`, `12/16`; weight 500 | `18/28`, `16/24`, `14/20`, `12/16`; weight 500 | Tablet matches Desktop for label sizes |
| Heading 1 | `heading-1` | `30/36/700` | `60/72/700` | Tablet `48/56/700` |
| Heading 2 | `heading-2` | `24/32/700` | `48/56/700` | Tablet `36/40/700` |
| Heading 3 | `heading-3` | `20/28/700` | `36/40/700` | Tablet `30/36/700` |
| Heading 4+ | `heading-4`…`heading-6` | `18/28`, `16/24`, `16/24`; weight 500 | `30/36`, `24/32`, `20/28`; weight 500 | Tablet values are present in export |

Responsive typography breakpoint token/value: `Approved: Mobile base; Tablet at 768px (md); Desktop at 1280px (xl).`

Nếu design không định nghĩa một semantic role, ghi `Not defined`; không tự nhân
bản một style gần giống mà không ghi nhận assumption.

## 9. Responsive, Breakpoint and Container Contract

### 9.1 Breakpoints

| Name | Token/value | Intended transition |
|---|---|---|
| `sm` | `640px` | Retain Tailwind v4 default |
| `md` | `768px` | Retain Tailwind v4 default; activate Tablet typography and gutter |
| `lg` | `1024px` | Retain Tailwind v4 default; layout utility transition only |
| `xl` | `1280px` | Retain Tailwind v4 default; activate Desktop typography and gutter |
| `2xl` | `1536px` | Retain Tailwind v4 default; container returns to the native `96rem` outer max-width |

Giữ breakpoint mặc định Tailwind hay override: `Approved: retain Tailwind v4 default utility breakpoints. Use exported viewport tokens as design/reference tokens; activate Tablet typography at 768px and Desktop typography at 1280px without redefining the whole Tailwind breakpoint scale.`

### 9.2 Containers and gutters

| Viewport/range | Max width | Left/right gutter | Alignment | Notes |
|---|---|---|---|---|
| Mobile `<768px` | `None; fluid width` | `8px` (`spacing/sm`) | Centered | Primary page-content contract measured at 375px; individual components may intentionally use larger internal padding |
| Tablet `768–1279px` | `None; fluid width` | `24px` (`spacing/2xl`) | Centered | FE foundation decision because no tablet frame exists; validate at 768px and 1024px |
| Desktop `1280–1535px` | Override only native `80rem` tier to `1408px` outer max | `24px` internal padding | Centered | At a scrollbar-free 1440px design frame: 16px auto margin + 24px padding = 40px content inset; content width is 1360px |
| Wide desktop `≥1536px` | Native Hyvä/Tailwind `96rem` outer max-width | `24px` internal padding plus auto outer margins | Centered | No project override at this tier; content area is 1488px |

Approved implementation model:

```text
retain Hyvä/Tailwind container max-width rules below 80rem and from 96rem
outer max-width = 1408px only in the 80rem–95.999rem range
mobile padding  = 8px
tablet/desktop padding = 24px at 768px and above
80rem-tier content = 1408px - 2 × 24px = 1360px
96rem-tier content = 1536px - 2 × 24px = 1488px
```

Component-level exceptions are not changes to the global container contract:

- Mobile Footer uses `24px` internal horizontal padding.
- Horizontal carousels may retain the leading gutter and intentionally bleed or
  scroll toward the trailing viewport edge.
- Full-bleed hero, background and section shells remain `100vw`; their content
  wrapper uses the global container only where the design shows an inset.

## 10. Button Foundation Contract

### 10.1 Variants and sizes

| Item | Approved values / Figma nodes | Notes |
|---|---|---|
| Variants | `primary`, `secondary`, `tertiary`, `transparent` | From current Button set `2410:25972` |
| Sizes | `s`, `m`, `l`, `xl`, `2xl` | From Base Button `2410:26973`. Master dimensions are canonical; exported application modes are reused only where their values match, not renamed into a false one-to-one size mapping. |
| Icon-only | `In scope` | Base/Button includes none, leading, trailing, icon-only and round icon-only forms |
| Full-width behavior | `Out of scope unless a consuming layout explicitly requires it` | Not represented as a master Button variant |

### 10.2 States

| State | Designed | Source of truth / FE fallback |
|---|---|---|
| Default | `Yes` | Current Button set `2410:25972` |
| Hover | `Yes` | Current Button set `2410:25972` |
| Active/pressed | `Yes` | Current Button set `2410:25972` |
| Focus-visible | `Yes` | Figma `Focus` state; implement as keyboard-visible focus using WCAG/Hyvä patterns |
| Disabled | `Yes` | Figma `Disable` state; production API uses `disabled` naming |
| Loading | `Out of scope` | Not found in the Button master component |

## 11. Form Foundation Contract

### 11.1 Controls in scope

- [x] Text input
- [x] Password input
- [x] Email/number/search inputs
- [x] Textarea
- [x] Select
- [x] Checkbox
- [x] Radio
- [x] Label
- [x] Help text
- [x] Validation/error message
- [x] Form group/layout primitives

Other controls: `None for current estimate; add only through an approved scope change.`

Implementation authority: `Input 2410:14473, Textarea 2410:14352, base Input
2410:15699, base Textarea 2410:15674, Checkbox 2174:32588, Radio
2174:30745 and shared Base CheckRadio 2174:33021 are final design sources.
Checkbox and Radio are catalogued under Button page 2410:25885. Existing Hyvä/native semantics and Magento
validation markup remain the structural authority. Select stays in the global
native-control scope, but its component-specific final visuals are gated until a
standalone Select master is supplied.`

### 11.2 States

| State | Designed | Source of truth / FE fallback |
|---|---|---|
| Placeholder/default | `Yes` | Final Input/Textarea component sets |
| Hover | `Yes` | Final Input/Textarea component sets |
| Active | `Yes` | Final Input/Textarea component sets; normalize against native pointer/active behavior |
| Focus-visible | `Yes` | Figma Focus plus Hyvä/WCAG keyboard-visible semantics |
| Filled | `Yes` | Final Input/Textarea component sets |
| Disabled | `Yes` | Final Input/Textarea component sets plus native `disabled` semantics |
| Read-only | `Not explicit` | Native `readonly` semantics using the closest approved non-interactive tokens; document evidence |
| Error | `Yes` | Final red feedback roles + Magento validation integration |
| Warning | `Yes` | Final amber feedback roles; expose only where consuming markup supplies warning state |
| Success | `Yes` | Final green feedback roles |

### 11.3 Measured Form contract

- Base width in the component matrix: `320px`; production controls remain fluid.
- Input visual height: `44px`; Textarea designed height: `160px`.
- Field padding: `10px` block / `14px` inline; radius: `6px`; label/hint gap:
  `6px`.
- Label: Label M; control value/placeholder: Body 2; hint/feedback: Body 3.
- Input types: Default, Leading dropdown, Trailing dropdown and Leading text,
  with optional leading/trailing icons where represented.
- Feedback roles: None, Error, Warning and Success. Icon assets must follow the
  approved Hyvä custom-icon workflow rather than being redrawn.

## 12. Hyvä Custom Icon Foundation Contract

| Item | Approved value |
|---|---|
| Icon workflow in scope | `Yes — Global Style owns the site-wide Hyvä contract` |
| Figma icon-library node | `5:28677` in `LAUNCHPAD-CORE` |
| Export policy | `On demand per component; do not bulk-export the complete library` |
| Theme asset path | `Hyva_Theme/web/svg/{style}/{semantic-name}.svg`; optical variant: `{semantic-name}-{size}.svg` |
| Naming | `Figma semantic name → lowercase kebab-case; style becomes directory; direction becomes filename segment; no theme-name suffix` |
| Size policy | `Fill representative is uniform scaling and uses one canonical SVG; outline representative has optical stroke variants and uses exact designed size unless glyph audit proves equivalence` |
| Monochrome color | `currentColor`; multi-color brand/payment artwork is classified separately |
| Runtime renderer | `Hyva\\Theme\\ViewModel\\SvgIcons::renderHtml()` |
| Accessibility | `Containing button/link owns the name; decorative SVG uses aria-hidden=true` |
| Child-theme behavior | `Stable logical path allows Magento theme fallback override` |
| Manifest | `Not required for 1:1 semantic mapping; introduce only for aliases, renames, deprecation, multiple libraries or automated sync` |
| Workflow guide | [Hyvä Custom Icon Workflow](../../guides/HYVA_CUSTOM_ICON_WORKFLOW.md) |

Audit findings:

- Outer frame `2001:10642` contains `fill-icon`; outer frame `2001:13716`
  contains `outline-icon`. The duplicated `Fill Icon Sets` label is only an
  outer-frame naming defect.
- Both collections expose 12/16/24/32/40px variants; directional families add
  Down/Right/Up/Left.
- `academic-cap-fill` 12/24/40 is proportional uniform scaling.
- `user-circle-outline` uses optical stroke widths 1/1.5/2/2/2.5px at
  12/16/24/32/40px, so a single rescaled outline asset is not pixel-equivalent.
- No SVG is added by Global Style alone: current CSS foundations expose icon
  slots but do not consume a concrete glyph. Each component work item exports
  only its required glyph/size/direction.
- Do not copy POC SVG assets unless both semantic name and vector glyph match the
  Launchpad Core library/component instance.

## 13. Accessibility and Missing-State Authority

| Decision | Value |
|---|---|
| Accessibility target | `WCAG 2.2 AA` |
| FE may propose missing states | `Yes — use approved semantic tokens, Hyvä/native patterns and WCAG 2.2 AA; designer/TL review material visual changes` |
| Keyboard/focus authority | `FE using Hyvä/native interaction patterns and WCAG 2.2 AA, subject to Designer/TL review for material visual changes` |
| Contrast issue owner | `Designer owns the visual decision; TL owns technical/accessibility release-gate resolution; FE/QC provide evidence` |
| Reduced-motion policy | `Respect prefers-reduced-motion: reduce; remove or shorten non-essential motion without removing necessary state feedback` |
| Minimum touch target | `Project baseline 44 × 44 CSS px. If final design differs, FE must document the case and obtain Designer/TL confirmation before implementation; inline text links and applicable WCAG exceptions are evaluated separately.` |

Quy tắc cho state bị thiếu:

`Approved: FE proposes missing hover, active, focus-visible, disabled, error
and success states using approved semantic tokens, Hyvä/native patterns and
WCAG 2.2 AA. FE must not silently invent or change a visual-system decision;
material visual differences require Designer/TL review.`

## 14. Conflict Resolution and Decision Authority

Thứ tự ưu tiên được phê duyệt:

1. Explicit approved decision/clarification
2. Final Master Component
3. Semantic Figma Variables
4. Foundation specification
5. Representative page instance
6. Hyvä/native pattern
7. FE proposal following WCAG 2.2 AA

| Conflict type | Decision owner | Escalation path |
|---|---|---|
| Token vs Master Component | `Designer` | FE records evidence and proposed resolution; TL joins when the resolution affects the production token/API contract |
| Master Component vs page instance | `Designer` | Final Master Component wins by default unless an explicit page exception is approved |
| Design vs accessibility | `Designer for visual resolution; TL for release gate` | FE/QC document evidence; unresolved AA failure is escalated to TL and cannot be silently accepted |
| Design vs Hyvä technical constraint | `Tech Lead` | FE proposes the closest standards-based Hyvä/native implementation; Designer reviews visual impact |

Additional ownership:

- Scope or estimate changes: Tech Lead for `SLP-246`.
- Keyboard semantics and focus implementation: FE follows native HTML, Hyvä
  patterns and WCAG; material visual differences go through Designer/TL review.
- No design conflict is resolved silently in code or token transformation.

Không silently resolve conflict. Phân loại mỗi finding thành `reuse`, `add`,
`normalize`, `conflict`, `unresolved` hoặc `excluded`.

## 15. Runtime Validation Contract

### 14.1 Representative pages

| Page purpose | Local/stage route | Store view / locale | Required checks |
|---|---|---|---|
| Typography/content | `/` and `/customer-service` | `default / vi_VN`; `launchpad_en / en_US` | Heading/body/label hierarchy, long-content reflow, Vietnamese diacritics, English text expansion and localization |
| Buttons/forms | `/customer/account/login/`, `/customer/account/create/`, `/customer/account/forgotpassword/`, `/contact/` | Both approved store views/locales | Native controls, all available states, keyboard, focus-visible, validation and error messaging |
| Commerce surface | PLP `/living-room`; PDP `/atlas-pouf`; Cart `/checkout/cart/`; Checkout `/checkout/` regression-only | Both approved store views/locales where data is available | Token/layout regression; checkout is not the visual source of truth for the Global Form API |

### 14.2 Target environments

| Item | Value |
|---|---|
| Environment | `Local — DDEV project slaunchpad; use relative routes so the contract remains environment-independent` |
| Browser(s) | `Latest stable Chrome, Safari, Firefox and Edge at QA time; latest stable Safari iOS and Chrome Android for mobile checks` |
| Viewport widths | `320, 375, 768, 1024, 1280, 1440, 1600, 1920 CSS px` |
| Device/browser exclusions | `No additional legacy-browser requirement currently approved` |
| Auth-required pages | `None in the current Global Style validation set` |

### 14.3 Validation expectations

- [ ] Token audit has no unapproved fail-severity finding
- [ ] Repeat generation is byte-identical
- [ ] Tailwind production build passes
- [ ] Runtime semantic aliases resolve to approved values
- [ ] Representative responsive layouts match approved frames
- [ ] Keyboard and focus-visible checks pass
- [ ] Text/UI contrast is checked for implemented states
- [ ] 320 CSS px reflow is checked where applicable
- [ ] Supported locales are checked
- [ ] Relevant third-party Hyvä surfaces have no visible regression

## 16. Explicit Exclusions and Deferred Decisions

| Item | Reason | Owner | Revisit condition / date |
|---|---|---|---|
| Header implementation | Separate component work item; current input stores reference only | Project team | After Global Style foundation contract is approved |
| Menu/navigation implementation | Separate behavior and data-source scope | Project team | Header/Menu analysis work item |
| Footer implementation | Separate component/content/payment scope; CMS blocks follow the approved POC architecture | Project team | Footer work item starts |
| Component/page implementation | Explicitly outside current foundation scope | Project team | Separate component/page work items |
| Dark color mode | Exported but not approved for current production scope | Design/Product owner | A final dark-mode design and implementation work item are approved |
| Alternate brand modes | Launchpad Core currently approves only `olive` | Design/Product owner | A child theme such as Fashion receives its own final brand-token contract |
| Final Select visual contract | Input, Textarea, Checkbox and Radio are supplied; no standalone Select master is present in `2410:14322` | Design owner | Supply/audit Select before committing Select-specific visuals beyond the shared native field contract |

Examples for future entries: dark mode, alternate brand, incomplete component
tokens and documentation-only variables.

## 17. Approval Checklist

### Design/input readiness

- [x] Figma links point to final or explicitly approved foundation nodes
- [x] Variable export matches the supplied final design version
- [x] Production modes are explicitly approved
- [x] Font source, weights, subsets and license are confirmed
- [x] Typography breakpoint is confirmed
- [x] Container/gutters are confirmed
- [x] Button/Form source of truth is confirmed; Select-specific visuals remain explicitly gated
- [x] Icon library source, on-demand policy and representative vector geometry are audited; AC-011 passes
- [x] Missing-state authority is confirmed
- [x] Conflict-resolution authority is confirmed
- [x] Representative pages and locales are confirmed
- [x] Out-of-scope areas are explicit

### Governance readiness before implementation

- [x] Work item exists (`TASK-6V8H2P`, external reference `SLP-246`)
- [x] Full Spec or valid embedded Mini-Spec exists
- [x] Acceptance criteria are reviewable
- [x] Implementation plan references the specification
- [x] Required human decisions/approvals are recorded

## 18. Open Questions

| ID | Question | Impact if unresolved | Owner | Status |
|---|---|---|---|---|
| Q-001 | Where are the exact Input, Select and Textarea master component nodes? | Input `2410:14473` and Textarea `2410:14352` are resolved and implementation-ready. A standalone Select master is still absent; this blocks only Select-specific final visuals. | Design owner | `Partially resolved — Select open` |
| Q-002 | Is the discovered design-system inventory the intended final Foundation source? | Confirmed by the supplied final-design status, matching native Variables export and final Input/Textarea/Checkbox/Radio sources. | Design owner | `Resolved` |
| Q-003 | Are Main Footer links `2151:17066` and `2151:17280` labeled with reversed viewports? | Resolved: `2151:17280` is Desktop columns and `2151:17066` is Mobile accordion | Design owner | `Resolved` |
| Q-004 | What are the correct Bottom Footer and Payment visibility references? | Resolved: Desktop bottom `2151:17305`, Mobile bottom `2151:17095`; Payment visible Desktop and hidden Mobile | Design owner | `Resolved` |
| Q-005 | What theme-owned path will contain the immutable variables export? | Resolved: `app/design/frontend/Secomm/launchpad/design-tokens/source/` | Technical owner | `Resolved` |
| Q-006 | Which Figma export tool/plugin and version produced this JSON, and what is the export date/reference? | Resolved as Figma native Variables export; export date/reference still optional metadata | Technical/Design owner | `Resolved` |
| Q-007 | Are zero CTA values in `lg` and `xl` intentionally unsupported sizes, or incomplete Figma values? | `buttons/cta` is excluded from current production generation because no dedicated CTA Button master was found; revisit with a CTA component | Design owner | `Deferred` |
| Q-008 | Is the non-monotonic spacing mapping (`9xl=128`, `10xl=96`) intentional? | Resolved and verified: `spacing/9xl` now exports as `80` with alias `global_sizes/12` | Design owner | `Resolved` |
| Q-009 | Should Tailwind utility breakpoints remain defaults, or map to the exported viewport tokens? | Resolved: keep Tailwind defaults; use 768px/1280px only for typography-mode transitions | Technical owner | `Resolved` |
| Q-010 | Which Button component set is the current production source? | Button page `2410:25885` is the parent inventory. Current Button `2410:25972` and Base Button `2410:26973` are canonical; Legacy Button `2410:25890` and Legacy Icon Button `2410:25923` are excluded. | Design owner | `Resolved — 2026-09-24` |
| Q-011 | How is the fifth Button size `2xl` handled when the applications export has four named modes? | Final Master Component is authoritative for all five sizes. Do not force a name-based mapping; reuse exported values where exact and publish component-owned size hooks for the complete S/M/L/XL/2XL API. | Design/Technical owner | `Resolved — 2026-09-23` |
| Q-012 | How should Button sizes below the approved 44×44 project touch-target baseline be handled? Base/Button includes controls at 32px, 36px and 40px. | Preserve the designed visual size while providing a minimum 44×44px interaction area | Design/Technical owner | `Resolved — 2026-09-22` |
| Q-013 | How do the duplicate-named `Fill Icon Sets` groups differ, and which style/naming inventory is production-canonical? | Resolved: `2001:10642` is fill; `2001:13716` is outline. Both use 12/16/24/32/40; directional families add four directions. Outline optical variants are size-specific. | Design/Frontend owner | `Resolved — 2026-09-23` |

## 19. Sign-off

| Role | Name | Decision | Date / reference |
|---|---|---|---|
| Design authority | `Designer` | `Approved by user confirmation` | `SLP-246 — 2026-09-22` |
| Technical authority | `Tech Lead` | `Approved by user confirmation` | `SLP-246 — 2026-09-22` |
| Product/scope owner | `Tech Lead` | `Approved by user confirmation` | `SLP-246 — 2026-09-22` |
| Icon Foundation scope | `User / project owner` | `Approved on-demand Hyvä workflow amendment` | `2026-09-23` |
