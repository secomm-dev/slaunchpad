# TASK-DWW34M — Header shell and Snowdog level-1 refinement plan

| Field | Value |
|---|---|
| Specification | [SPEC-TASK-DWW34M](../specs/SPEC-TASK-DWW34M-launchpad-core-header.md) |
| External reference | `SLP-246` |
| Mode | B follow-up |
| Status | Proposed |
| Design sources | Desktop `2151:9517`; Mobile/Tablet `2115:5849` |
| Scope | Header shell, responsive alignment and Snowdog level-1 row only |

## 1. Verified design contract

### Desktop

- Header is 64px high with 40px horizontal and 12px vertical padding.
- Logo has a fixed 120px slot and an approximately 117.6×18px visual asset.
- Primary Navigation and Utility Navigation use 24px gaps.
- Level-1 labels use Label M: Inter Medium, 14px/20px; dropdown label and
  12px chevron use a 4px internal gap and 8px vertical padding.
- Figma's 1440px frame models both side regions as 400px. The approved runtime
  contract supersedes that fixed width: the logo must remain centered while the
  left and right regions consume equal flexible space.

### Mobile and Tablet

- Header remains 64px high with vertically centered 40×40 action targets.
- Mobile at 375px uses 8px outer padding, 16px Menu-to-Logo gap and 8px gaps
  between Search, Account and Cart.
- Tablet at 640px uses 32px outer padding and 16px action gaps.
- Logo keeps the same 120×18px slot; every visible glyph is 24×24px.

## 2. Target layout architecture

### Desktop shell

Use a three-column grid rather than fixed 400px side columns:

```text
minmax(0, 1fr) | 120px logo | minmax(0, 1fr)
                 24px gaps
```

Equivalent width rule for each side is:

```text
(available inner width - logo width - 2 × column gap) / 2
```

At the canonical 1440px viewport this is calculated inside the 40px gutters,
but it continues to work at wider and intermediate Desktop widths. The middle
column, not the relative amount of content on either side, owns logo centering.

### Overflow ownership

- The left grid cell uses `min-w-0` and owns horizontal overflow.
- Snowdog's level-1 list uses one non-wrapping row with 24px item gaps and
  content width (`w-max`/equivalent), not `justify-between`.
- The row is horizontally scrollable when items exceed the available left
  region; scrollbar is visually hidden while wheel, trackpad, touch and
  keyboard access remain available.
- The logo and Utility Navigation never shrink. Right actions retain their
  designed 24px gap and are not overlapped by menu content.

### Mobile/Tablet shell

- Keep two groups with `justify-between`: Menu + Logo on the left and Search +
  Account + Cart on the right.
- Apply exact 40px action boxes and center every 24px SVG both horizontally and
  vertically.
- Mobile uses 8px action gaps; Tablet uses 16px. Menu-to-Logo remains 16px at
  the canonical 375/640px frames.
- Add a narrow-width safeguard below 375px by reducing only the Menu-to-Logo
  gap to 8px. Do not scale icons, reduce touch targets or introduce horizontal
  page overflow.

## 3. Ordered implementation

1. **Refactor Header grid** — replace Desktop
   `400px / 120px / 400px` and side `w-[400px]` utilities with equal
   `minmax(0,1fr)` columns, a fixed 120px logo column and 24px column gaps.
2. **Separate overflow boundaries** — make only the Desktop primary-navigation
   viewport horizontally scrollable; apply the existing scrollbar-hiding
   utility and preserve keyboard focus visibility while scrolled.
3. **Add a theme-local Snowdog Desktop template override** — do not edit
   `app/code/Snowdog/Menu`; reduce the level-1 renderer to a non-wrapping row
   with 24px gaps and remove the vendor `container`, 80px height,
   `justify-between`, per-item `px-6`, forced primary color and selected-panel
   styling that conflict with the Header master.
4. **Apply level-1 visual contract** — Label M 14/20 medium, 8px vertical
   padding, 4px label-chevron gap, exact 12px custom chevron, contextual
   `currentColor`, semantic hover/focus/active states, and no mega-menu panel
   restyling in this task.
5. **Normalize Mobile/Tablet geometry** — align Menu, Logo, Search, Account and
   Cart on the same center line; enforce 40×40 action boxes, 24×24 glyphs,
   mobile/tablet gutters and responsive gaps from the verified nodes.
6. **Build and static validation** — run Tailwind build, PHP/XML lint and
   `git diff --check`; clear Magento layout/block/FPC cache only as required.
7. **Browser QA and evidence** — compare 375px, 640px and 1440px directly to
   Figma; additionally test 320, 768, 1024, 1280 and 1920px, long labels,
   additional level-1 items, both contextual Header treatments and sticky
   pre/post-scroll states.

## 4. Acceptance criteria

- Logo center equals viewport center at every Desktop width, independent of
  left/right content widths and guest/customer action variants.
- Desktop regions contain no fixed 400px width; both side cells are equal and
  shrink safely through `minmax(0,1fr)`.
- Extra level-1 items remain on one row and are reachable through horizontal
  scrolling without a visible scrollbar or page-level horizontal overflow.
- Level-1 typography, 24px inter-item gap, 4px label-chevron gap and 12px
  chevron match node `2151:9517`.
- At 375px and 640px, all Header actions share one vertical center line and
  match the design gutters/gaps from node `2115:5849`.
- At 320px there is no overlap or horizontal overflow; action targets stay at
  least 40×40px and glyphs remain 24×24px.
- Search, account, language, wishlist, minicart, sticky behavior and Snowdog
  runtime data continue to work.

## 5. Explicitly out of scope

- Mega-menu panel layout, content regions, animation and panel responsive QA.
- Mobile drawer hierarchy and inner-panel redesign.
- Snowdog Admin content authoring or catalog restructuring.
- Header icon asset changes unrelated to alignment.

