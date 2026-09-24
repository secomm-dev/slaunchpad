# Hyvä Global Style Foundation — Input Template

> Template tái sử dụng cho Magento 2 theme sử dụng Hyvä và Tailwind CSS v4
> CSS-first. Copy file này thành một input record riêng cho mỗi theme, điền các
> mục áp dụng và giữ `TBD` cho thông tin chưa được quyết định.
>
> File đã điền là đầu vào cho skill `hyva-global-style-foundation`; nó không
> thay thế Full Spec, ticket, acceptance criteria hoặc implementation plan theo
> governance của project.

## 0. Metadata

| Field | Value |
|---|---|
| Project | `TBD` |
| Theme/Foundation name | `TBD` |
| Input owner | `TBD` |
| Designer / design approver | `TBD` |
| Technical approver | `TBD` |
| Design status | `Draft \| Final candidate \| Approved` |
| Input version | `TBD` |
| Last updated | `YYYY-MM-DD` |
| Related ticket / feature | `TBD` |

## 1. Target Theme and Product Ownership

### 1.1 Theme inheritance

| Field | Value |
|---|---|
| Target Magento theme | `Vendor/theme` |
| Target theme path | `app/design/frontend/Vendor/theme` |
| Parent theme | `Hyva/default \| Vendor/parent-theme` |
| Tailwind version | `4.x` |
| Tailwind entry file | `web/tailwind/tailwind-source.css` |
| Store view(s) | `TBD` |
| Locale(s) | `TBD` |

### 1.2 Product layer

Chọn đúng một ownership chính:

- [ ] Commerce Core
- [ ] Core Add-on
- [ ] Industry Core — tên ngành: `TBD`
- [ ] Industry Add-on — tên ngành/add-on: `TBD`
- [ ] Customer-specific theme

Các theme dự kiến kế thừa foundation này:

- `TBD`

Các giá trị hoặc hành vi đặc thù ngành/khách hàng không được đưa vào foundation:

- `TBD`

## 2. Scope Contract

### 2.1 In scope

Đánh dấu các phần thuộc lần triển khai này:

- [ ] Immutable Figma/DTCG token source
- [ ] Token audit và deterministic token transformer
- [ ] Production mode allowlist
- [ ] Font loading và font-family mapping
- [ ] Primitive-to-semantic color mapping
- [ ] Typography foundation
- [ ] Spacing scale
- [ ] Border width và radius
- [ ] Shadows/effects
- [ ] Breakpoints
- [ ] Container và gutters
- [ ] Global document/body defaults
- [ ] Global link và focus treatment
- [ ] Global Button API/styles
- [ ] Global Form API/styles
- [ ] Hyvä custom-icon workflow và icon-library contract
- [ ] Supporting primitives thực sự được Button/Form sử dụng
- [ ] Representative-page validation

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

- `None \| TBD`

## 3. Figma Sources

> Dùng URL có `node-id` cụ thể. Không chỉ cung cấp link root của Figma file.

| Input | Figma URL / node ID | Status | Notes |
|---|---|---|---|
| Foundations overview | `TBD` | `Draft \| Final \| Approved` | `TBD` |
| Color primitives | `TBD` | `TBD` | `TBD` |
| Semantic colors | `TBD` | `TBD` | `TBD` |
| Typography | `TBD` | `TBD` | `TBD` |
| Spacing/sizing | `TBD` | `TBD` | `TBD` |
| Radius/borders | `TBD` | `TBD` | `TBD` |
| Shadows/effects | `TBD` | `TBD` | `TBD` |
| Grid/container | `TBD` | `TBD` | `TBD` |
| Button master component | `TBD` | `TBD` | `TBD` |
| Form master components | `TBD` | `TBD` | `TBD` |
| Link/focus examples | `TBD` | `TBD` | `TBD` |
| Icon library | `TBD` | `TBD` | Ghi rõ reference-only hay Hyvä custom-icon workflow thuộc scope |

### 3.1 Representative responsive frames

| Viewport | Page/frame | Figma URL / node ID | Designed width |
|---|---|---|---|
| Desktop | `TBD` | `TBD` | `TBD px` |
| Tablet | `TBD` | `TBD` | `TBD px` |
| Mobile | `TBD` | `TBD` | `TBD px` |

Mục đích của các frame này là validate foundation; chúng không đưa toàn bộ page
composition vào scope.

## 4. Variables and Token Export

| Field | Value |
|---|---|
| Export path / attachment | `TBD` |
| Export format | `DTCG JSON \| Tokens Studio JSON \| Figma Variables JSON \| Other` |
| Export tool/plugin and version | `TBD` |
| Exported at | `YYYY-MM-DD` |
| Matches final design | `Yes \| No \| Partially` |
| Collections included | `TBD` |
| Known incomplete collections | `TBD` |
| Documentation-only collections | `TBD` |

Rules:

- Export files là immutable input, không chỉnh tay hoặc normalize in place.
- Giữ nguyên collection, modes, aliases và Figma variable IDs nếu export hỗ trợ.
- Ghi rõ export mới thay thế toàn bộ hay chỉ cập nhật một phần export trước.

## 5. Production Mode Allowlist

Chỉ các mode được duyệt dưới đây mới được generate vào production CSS.

| Role | Approved production mode | Audit-only modes | Rationale / approval |
|---|---|---|---|
| Color | `TBD` | `TBD` | `TBD` |
| Brand | `TBD` | `TBD` | `TBD` |
| Font | `TBD` | `TBD` | `TBD` |
| Typography base | `TBD` | `TBD` | `TBD` |
| Typography responsive | `TBD` | `TBD` | `TBD` |

Dark mode:

- [ ] Không có
- [ ] Có trong export nhưng audit-only
- [ ] Được duyệt cho production — approval: `TBD`

## 6. Font Contract

| Field | Value |
|---|---|
| Primary font family | `TBD` |
| Secondary/display font | `None \| TBD` |
| Required weights | `TBD` |
| Styles | `normal \| italic \| TBD` |
| Font source | `TBD` |
| File format | `woff2 \| variable woff2 \| TBD` |
| Required subsets | `latin \| vietnamese \| TBD` |
| Fallback stack | `TBD` |
| Self-hosting permitted | `Yes \| No \| Pending` |
| License evidence/location | `TBD` |
| Loading strategy | `preload subset/weight: TBD` |

## 7. Semantic Color Contract

> Không cần copy toàn bộ primitive palette vào bảng này. Ghi semantic roles mà
> component và Hyvä hooks được phép sử dụng.

| Semantic role | Figma variable/token | Usage | Status |
|---|---|---|---|
| Primary action | `TBD` | Button/link/action | `Approved \| TBD` |
| On primary | `TBD` | Content on primary action | `TBD` |
| Secondary action | `TBD` | Secondary action | `TBD` |
| Page background | `TBD` | Document background | `TBD` |
| Surface | `TBD` | Cards/forms/surfaces | `TBD` |
| Primary text | `TBD` | Main content | `TBD` |
| Secondary text | `TBD` | Supporting content | `TBD` |
| Border | `TBD` | Default border | `TBD` |
| Focus | `TBD` | Focus outline/ring | `TBD` |
| Error | `TBD` | Error state | `TBD` |
| Success | `TBD` | Success state | `TBD` |
| Warning | `TBD` | Warning state | `TBD` |
| Disabled | `TBD` | Disabled content/control | `TBD` |

Additional semantic roles:

- `TBD`

## 8. Typography Contract

| Style/role | Figma token/style | Mobile | Desktop | Notes |
|---|---|---|---|---|
| Body | `TBD` | `TBD` | `TBD` | `TBD` |
| Body small | `TBD` | `TBD` | `TBD` | `TBD` |
| Label | `TBD` | `TBD` | `TBD` | `TBD` |
| Heading 1 | `TBD` | `TBD` | `TBD` | `TBD` |
| Heading 2 | `TBD` | `TBD` | `TBD` | `TBD` |
| Heading 3 | `TBD` | `TBD` | `TBD` | `TBD` |
| Heading 4+ | `TBD` | `TBD` | `TBD` | `TBD` |

Responsive typography breakpoint token/value: `TBD`

Nếu design không định nghĩa một semantic role, ghi `Not defined`; không tự nhân
bản một style gần giống mà không ghi nhận assumption.

## 9. Responsive, Breakpoint and Container Contract

### 9.1 Breakpoints

| Name | Token/value | Intended transition |
|---|---|---|
| `sm` | `TBD` | `TBD` |
| `md` | `TBD` | `TBD` |
| `lg` | `TBD` | `TBD` |
| `xl` | `TBD` | `TBD` |
| `2xl` | `TBD` | `TBD` |

Giữ breakpoint mặc định Tailwind hay override: `TBD`

### 9.2 Containers and gutters

| Viewport/range | Max width | Left/right gutter | Alignment | Notes |
|---|---|---|---|---|
| Mobile | `TBD` | `TBD` | `TBD` | `TBD` |
| Tablet | `TBD` | `TBD` | `TBD` | `TBD` |
| Desktop | `TBD` | `TBD` | `TBD` | `TBD` |
| Wide desktop | `TBD` | `TBD` | `TBD` | `TBD` |

## 10. Button Foundation Contract

### 10.1 Variants and sizes

| Item | Approved values / Figma nodes | Notes |
|---|---|---|
| Variants | `TBD` | e.g. primary, secondary, tertiary |
| Sizes | `TBD` | e.g. sm, md, lg |
| Icon-only | `In scope \| Out of scope \| TBD` | `TBD` |
| Full-width behavior | `TBD` | `TBD` |

### 10.2 States

| State | Designed | Source of truth / FE fallback |
|---|---|---|
| Default | `Yes \| No` | `TBD` |
| Hover | `Yes \| No` | `TBD` |
| Active/pressed | `Yes \| No` | `TBD` |
| Focus-visible | `Yes \| No` | `TBD` |
| Disabled | `Yes \| No` | `TBD` |
| Loading | `Yes \| No \| Out of scope` | `TBD` |

## 11. Form Foundation Contract

### 11.1 Controls in scope

- [ ] Text input
- [ ] Password input
- [ ] Email/number/search inputs
- [ ] Textarea
- [ ] Select
- [ ] Checkbox
- [ ] Radio
- [ ] Label
- [ ] Help text
- [ ] Validation/error message
- [ ] Form group/layout primitives

Other controls: `TBD`

### 11.2 States

| State | Designed | Source of truth / FE fallback |
|---|---|---|
| Empty/default | `Yes \| No` | `TBD` |
| Hover | `Yes \| No` | `TBD` |
| Focus-visible | `Yes \| No` | `TBD` |
| Filled | `Yes \| No` | `TBD` |
| Disabled/read-only | `Yes \| No` | `TBD` |
| Error | `Yes \| No` | `TBD` |
| Success | `Yes \| No \| Out of scope` | `TBD` |

## 12. Hyvä Custom Icon Foundation Contract

| Item | Value |
|---|---|
| Icon workflow in scope | `Yes \| No \| TBD` |
| Figma icon-library node | `TBD` |
| Export policy | `On demand per component \| Versioned full-library package \| TBD` |
| Theme asset path | `Hyva_Theme/web/svg/{style}/{semantic-name}.svg` |
| Naming | `Figma semantic name → lowercase kebab-case; no size suffix by default` |
| Size policy | `One canonical SVG per glyph/style; size-specific only for demonstrated optical differences` |
| Monochrome color | `currentColor` |
| Runtime renderer | `Hyva\\Theme\\ViewModel\\SvgIcons::renderHtml()` |
| Accessibility | `Containing control owns accessible name; decorative SVG uses aria-hidden=true` |
| Full-library export approved | `No \| Yes — approval/work item: TBD` |
| Workflow guide | `.ai/guides/HYVA_CUSTOM_ICON_WORKFLOW.md` |

Required decisions/findings:

- `TBD — icon style groups, naming conflicts, multi-color artwork exceptions,
  representative consumers and child-theme override policy.`

## 13. Accessibility and Missing-State Authority

| Decision | Value |
|---|---|
| Accessibility target | `WCAG 2.2 AA \| TBD` |
| FE may propose missing states | `Yes \| No` |
| Keyboard/focus authority | `Design \| FE using Hyvä/WCAG \| TBD` |
| Contrast issue owner | `Designer \| TL \| TBD` |
| Reduced-motion policy | `TBD` |
| Minimum touch target | `TBD` |

Quy tắc cho state bị thiếu:

`TBD — recommended: FE proposes using approved semantic tokens, Hyvä patterns
and WCAG 2.2 AA; designer/TL approves any visual-system decision.`

## 14. Conflict Resolution and Decision Authority

Thứ tự ưu tiên được phê duyệt:

1. `TBD`
2. `TBD`
3. `TBD`
4. `TBD`

Recommended default:

1. Explicit approved decision/clarification
2. Final Master Component
3. Semantic Figma Variables
4. Foundation specification
5. Representative page instance
6. Hyvä pattern
7. FE proposal following WCAG 2.2 AA

| Conflict type | Decision owner | Escalation path |
|---|---|---|
| Token vs Master Component | `TBD` | `TBD` |
| Master Component vs page instance | `TBD` | `TBD` |
| Design vs accessibility | `TBD` | `TBD` |
| Design vs Hyvä technical constraint | `TBD` | `TBD` |

Không silently resolve conflict. Phân loại mỗi finding thành `reuse`, `add`,
`normalize`, `conflict`, `unresolved` hoặc `excluded`.

## 15. Runtime Validation Contract

### 14.1 Representative pages

| Page purpose | Local/stage route | Store view / locale | Required checks |
|---|---|---|---|
| Typography/content | `TBD` | `TBD` | responsive, reflow, localization |
| Buttons/forms | `TBD` | `TBD` | states, keyboard, validation |
| Commerce surface | `TBD` | `TBD` | token regression only |

### 14.2 Target environments

| Item | Value |
|---|---|
| Environment | `Local \| Development \| Staging` |
| Browser(s) | `TBD` |
| Viewport widths | `TBD` |
| Device/browser exclusions | `TBD` |
| Auth-required pages | `TBD` |

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
| `TBD` | `TBD` | `TBD` | `TBD` |

Examples: dark mode, alternate brand, incomplete component tokens,
documentation-only variables, Header/Footer/Menu, page implementation.

## 17. Approval Checklist

### Design/input readiness

- [ ] Figma links point to final or explicitly approved nodes
- [ ] Variable export matches the final design version
- [ ] Production modes are explicitly approved
- [ ] Font source, weights, subsets and license are confirmed
- [ ] Typography breakpoint is confirmed
- [ ] Container/gutters are confirmed
- [ ] Button/Form source of truth is confirmed
- [ ] Icon library source and on-demand/full-export policy are confirmed
- [ ] Missing-state authority is confirmed
- [ ] Conflict-resolution authority is confirmed
- [ ] Representative pages and locales are confirmed
- [ ] Out-of-scope areas are explicit

### Governance readiness before implementation

- [ ] Work item exists
- [ ] Full Spec or valid embedded Mini-Spec exists
- [ ] Acceptance criteria are reviewable
- [ ] Implementation plan references the specification
- [ ] Required human decisions/approvals are recorded

## 18. Open Questions

| ID | Question | Impact if unresolved | Owner | Status |
|---|---|---|---|---|
| Q-001 | `TBD` | `TBD` | `TBD` | `Open` |

## 19. Sign-off

| Role | Name | Decision | Date / reference |
|---|---|---|---|
| Design authority | `TBD` | `Approved \| Changes requested` | `TBD` |
| Technical authority | `TBD` | `Approved \| Changes requested` | `TBD` |
| Product/scope owner | `TBD` | `Approved \| Changes requested` | `TBD` |
