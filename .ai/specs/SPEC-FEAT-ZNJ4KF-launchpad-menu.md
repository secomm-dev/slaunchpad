---
id: SPEC-FEAT-ZNJ4KF
type: specification
title: Secomm Launchpad Menu (Desktop mega panel + Mobile/Tablet drawer)
feature_id: FEAT-ZNJ4KF
specification_level: FULL
status: VALID
owner: TASK-08343C
external_ref: SLP-245
created: 2026-09-29
updated: 2026-10-05
---

# SPEC-FEAT-ZNJ4KF — Secomm Launchpad Menu

Specification ID: SPEC-FEAT-ZNJ4KF
Feature ID: FEAT-ZNJ4KF
Specification Level: FULL

Input sources: Figma page `2949:93615` (Desktop `2151:11236`, Mobile root
`2115:5380`, Mobile level 1 `2949:77708`, Mobile level 2 `2949:93394`) — design
context and screenshots re-read 2026-09-29; plan revision
`.ai/evidence/menu-design-review/2026-09-29-plan-revision.md`; design analysis
`.ai/project/design/secomm-launchpad-header-menu-footer-analysis.md` §7–§8;
Hyvä UI references `menu/C-vertical-dropdown-4-column` and
`menu-mobile/A-scroll`; implementation directive 2026-09-29 (scope approval);
My Account decision answered by user 2026-09-29 (remove — Language only).

## 1. Goal

Implement the Desktop mega panel and Mobile/Tablet drawer for the Launchpad
Header using Snowdog Menu as the runtime data source, adapting the Hyvä UI
reference behavior to theme-local Snowdog templates, on top of the TASK-DWW34M
Header shell and level-1 row.

## 2. Expected behavior

### 2.1 Data contract

1. ONE managed Snowdog hierarchy is the single content source: both renderers
   consume the identifier `hyva-topmenu-desktop`. The theme layout points the
   mobile renderer block at that identifier and gives it its own template via
   the Snowdog `template` / `subMenuTemplate` block arguments (TemplateResolver
   resolves them per menu id, yielding separate theme-local template files
   without touching `app/code/Snowdog/Menu`). The `hyva-topmenu-mobile` Admin
   menu becomes unused by the theme; content changes are authored once.
2. Category/URL/label data stays runtime (Snowdog node types and Magento
   category URLs). No Figma labels, counts or demo copy are hard-coded.
3. Feature visual = the level-0 branch node's own image where its node type
   supports one (category / custom URL). Feature copy comes from the node
   banner editor, falls back to optional CMS block
   `launchpad-menu-feature-<nodeId>`, and degrades without empty furniture.
   Mobile copy additionally requires the node's mobile-display flag.
4. Parent navigation: the parent title navigates to its resolved Snowdog URL;
   a separate trailing chevron discloses its child column/panel. Wrappers
   without a destination render text plus the disclosure control. No
   duplicate See-all row is rendered.
5. Depth policy: Desktop appends a column for every opened branch and uses the
   responsive horizontal rail defined in §9. Mobile renders the root, group
   panel, and inline single-open child accordions.
6. Store scope, Snowdog cache identities, node-type rendering and escaping are
   preserved. The Launchpad-owned companion schema and Admin fields are
   defined in §8; no third-party table or vendor module is modified.

### 2.2 Desktop panel

- Opening a level-1 item shows the full-width panel below the Header shell
  (escapes the level-1 scroll viewport through the grid shell's containing
  block): feature image 432×381 (object-cover) with Body 3 copy, then up to
  three navigation columns separated by 1px `gray-soft-tertiary` dividers.
- Column layout: 40px panel padding, 32px gaps, item rows padded 8px, Label XL
  (Inter Medium 18/28) with 16px chevron; selected parent = `brand-soft`
  background, 6px radius, `brand-secondary-on-brand-alt` text.
- Selecting a parent appends its child column; changing an earlier selection
  resets every deeper branch. No default deeper selection. The responsive
  visibility and horizontal-scroll rules are defined in §9.
- Activation: hover opens only on fine pointers as an enhancement;
  click/Enter/Space toggle deterministically (HMF-04). Panel closes on
  delayed mouseleave (~300ms, hover paths only), outside click and Escape;
  focus returns to the triggering level-1 control; `aria-expanded` is kept
  accurate. Panel is hover-stable (entering it cancels the close timer).
- Leaf items navigate; keyboard focus rings stay visible; long lists scroll
  inside the panel without page-level overflow.

### 2.3 Mobile/Tablet drawer

- A-scroll baseline: native `<dialog>` + `x-htmldialog.noscroll` (plugin
  verified loaded), viewport-height drawer with its own scrollable content
  area, slide transition, Escape/backdrop/close-button close, focus restored
  to the trigger, body scroll released, resize across the desktop breakpoint
  closes and cleans up.
- Header of all three states: logo slot 160px (drawer logo asset ~156.8×24),
  close button 44×44 (`brand-100` surface, 24px glyph) and the search bar
  directly below the top bar.
- Root: level-0 rows (Label L 16/24, 12px vertical padding, divider,
  chevron-right 16px for parents); Settings section with the Language row
  (globe 16px + current flag 24px + current language + chevron) and an inline
  disclosure listing ALL active stores (current included, underlined with
  `primary/brand-500`) with runtime store switch URLs.
- Group panel: Back row (`brand-100`, chevron-left, `brand-primary` text,
  focus restored to the opening row), then children — parent titles link to
  their destinations and separate chevrons control single-open inline
  accordions (chevron-down rotates); leaves are links. The
  branch feature image (300px tall, object-cover) sits below the content.
- My Account is intentionally NOT rendered in Settings (user decision
  2026-09-29); no Currency, social links or demo Contact.
- Search works in all three states: dedicated form IDs (no duplicate DOM IDs),
  submit via Enter and button through the Hyvä search contract; virtual
  keyboard keeps the drawer usable (internal scroll area).
- Tablet has no source frame; the drawer follows the same responsive contract
  as the Header (full-width up to the Header's `lg` breakpoint; 8px gutters
  below 640px, 32px from 640px, matching Header gutters).

### 2.4 Integration and regression

- Header level-1 row styling, logo centering, contextual colors, sticky
  behavior, search popup, account popup, minicart, wishlist, language popup
  and global-message offset are unchanged.
- Snowdog-disabled native Hyvä fallbacks keep working (the topmenu child must
  stay non-empty when Snowdog is enabled so the native fallback does not
  trigger).
- No double scroll lock or focus trap with existing Snowdog logic (the vendor
  mobile template is replaced, not stacked).
- Reduced motion: transitions are `motion-safe`.

## 3. Constraints and rules

- No edits to `app/code/Snowdog/Menu`, Hyvä vendor or theme-module sources.
- PHTML + Alpine.js + Tailwind CSS v4 CSS-first; no jQuery/RequireJS/Knockout;
  no `tailwind.config.js`; no manual generated-CSS edits.
- Exact committed Figma SVG assets (16px chevron set, close, globe-alt) via
  Hyvä `SvgIcons`, `currentColor` where monochrome; existing theme flags and
  search glyph are reused where identical.
- Global Style semantic tokens over raw values; new storefront strings in both
  `vi_VN.csv` and `en_US.csv`.
- Layout XML changes stay inside the theme's `Snowdog_Menu/layout/` and
  `Magento_Theme`/`Magento_Store` template additions needed for drawer
  children.

## 4. Out of scope

- Snowdog Admin authoring UX beyond the node banner fields in §8, footer, and
  page content.
- Desktop nested hover beyond the approved top-level hover enhancement.
- My Account drawer row (removed by user decision), Currency, socials.
- Authentication/login business logic changes.

## 5. Acceptance criteria

- AC-M01: Desktop 1440px matches node `2151:11236` (geometry, typography,
  dividers, selected state); the three Mobile states at 375px match nodes
  `2115:5380`, `2949:77708`, `2949:93394`.
- AC-M02: Desktop parent selection updates the next column and resets deeper
  branches; leaves navigate; hover (fine pointer), click/Enter/Space,
  delayed mouseleave, outside click and Escape behave as specified.
- AC-M03: Mobile root → group → back works with focus restoration; accordion
  opens/closes single-open; leaf URLs are runtime-accurate.
- AC-M04: Drawer search submits via Enter and button, has unique DOM IDs, and
  stays usable with the virtual keyboard.
- AC-M05: Feature image renders in Desktop panel and Mobile group states from
  the managed source; mobile copy obeys the per-node flag; missing
  image/content degrades without empty furniture.
- AC-M06: Long labels, vi/en lengths, empty branches and missing features do
  not overflow; depth beyond the policy is not rendered.
- AC-M07: Body scroll position restores; no leftover scroll lock/backdrop on
  resize across the breakpoint; no conflicts with Search/Cart/Account
  overlays or sticky on/off.
- AC-M08: QA at 320/375/640/768/1024/1280/1440/1920px; tablet widths are
  responsive-contract checks only.
- AC-M09: Language lists all active stores including current with selected
  indicator; switching uses real store URLs; single shared hierarchy flows to
  both renderers; Snowdog-off fallback unchanged.
- AC-M10: PHP/XML lint, Tailwind production build, `git diff --check`,
  behavior tests and evidence recorded under `.ai/evidence/TASK-08343C/`.

---

## 8. Amendment — Node banner content (2026-10-02, extension of TASK-08343C)

### 8.1 Requirement

Per-node banner content editing in the Snowdog Admin node editor:
- **Banner content**: optional WYSIWYG field below the image upload section;
  supports paragraph, line break, bold/italic, link. No Page Builder, no new
  editor library. Image Alt Text keeps its own meaning.
- **Show banner content on mobile**: Yes/No below the editor; default **No**
  for new and legacy nodes (absence of value = No).
- Help text documents the CMS-block fallback convention.

### 8.2 Frontend behaviour

- Desktop: banner content renders under the feature image when non-empty;
  precedence over the CMS-block fallback (`launchpad-menu-feature-<nodeId>`).
- Fallback order: editor content (if real content — `<p><br></p>` and similar
  empty-HTML shells count as empty) → CMS block → nothing (no empty region).
- Mobile: content hidden when the flag is No (image still renders); shown
  under the image in the branch panel when Yes. The flag governs BOTH the
  editor content and the CMS fallback.
- Editor-empty + no CMS block + no image → no reserved empty region.

### 8.3 Storage (approved and implemented)

Companion table in `Launchpad_SnowdogMenu` (schema via db_schema in the
module; no third-party table alteration):

```sql
CREATE TABLE launchpad_snowdog_menu_node_banner (
  node_id int(10) unsigned NOT NULL COMMENT 'Snowdog node ID',
  banner_content mediumtext NULL COMMENT 'Banner WYSIWYG HTML',
  show_banner_content_mobile smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (node_id),
  CONSTRAINT FK_LAUNCHPAD_SNOWDOG_NODE_BANNER FOREIGN KEY (node_id)
    REFERENCES snowmenu_node (node_id) ON DELETE CASCADE
);
```

Data scope follows the node (menu/store scoping inherited from the node row —
no store-specific translation layer, per directive).

### 8.4 Integration (no Snowdog/vendor file edits)

- **Admin form**: RequireJS `paths` overrides the `menuNodes` entry module;
  the Launchpad copy loads
  `Launchpad_SnowdogMenu/view/adminhtml/web/vue/menu-type.vue`
  (adds WYSIWYG textarea + Yes/No below the image block; binds
  `item.banner_content` / `item.show_banner_content_mobile` so the values
  ride the standard serialized_nodes flow).
- **Persistence**: plugin AFTER `SaveRequestProcessor::processNodeObject()`
  (real node id + raw node data both available; new nodes already carry their
  persisted id from the creation loop) stages banner values on the node
  object; plugin AFTER `NodeRepositoryInterface::save()` upserts/deletes the
  companion row. A dirty flag invalidates `block_html` and `full_page` once
  after the tree save; unchanged saves do not clean caches.
- **Editor pre-fill**: plugin AFTER `Tab\Nodes::renderNodes()` appends the
  stored values to each node's JSON payload.
- **Orphan hygiene**: the FK `ON DELETE CASCADE` removes companion rows through
  the standard node/menu deletion paths. No cross-menu purge is used.
- **Frontend**: `MenuFeature` reads the companion row (batched reader) and
  applies the empty-content rule; desktop panel + mobile panel templates
  render content through `Template\FilterProvider` for
  directive/widget/media-URL processing — FilterProvider is NOT a sanitizer;
  banner content is trusted admin-authored WYSIWYG output. Legacy nodes
  without rows behave exactly as before.

### 8.5 Acceptance criteria (banner scope)

- AC-B1: Admin — node edit form shows WYSIWYG + Yes/No below the image
  section; values survive Save/reload; switching nodes never leaks content
  between nodes; empty editor saves as empty (CMS fallback active).
- AC-B2: Legacy nodes (no banner row) load/edit/render unchanged; mobile
  flag defaults No.
- AC-B3: Desktop renders editor content with precedence over CMS fallback;
  empty editor → CMS fallback; both empty → no region.
- AC-B4: Mobile flag gates content (not image): No → image only; Yes →
  content under image from the same source order.
- AC-B5: Rich content (paragraph/br/bold/italic/link, Vietnamese) renders
  through the Magento template filter without raw-HTML risks; long content
  does not overflow.
- AC-B6: Cache — content/flag/CMS-fallback changes reflect on the storefront
  with cache enabled (save-path invalidation, no manual flush).

---

## 9. Amendment — Responsive Desktop navigation columns (2026-10-04)

- The earlier three-visible-column rule is superseded by this revision.
- At desktop widths below 1440px, the available navigation rail is divided
  into two equal-width columns.
- From 1440px, three navigation columns must fit fully in the available rail.
  Each column grows up to the approved 300px design width and may reduce
  equally when the banner and panel gutters leave less than 900px.
- Every deeper column keeps the same width and extends the rail horizontally;
  users can scroll the rail instead of squeezing existing columns. Opening a
  parent automatically scrolls the rail to the newly revealed column without
  moving keyboard focus. Changing an earlier selection resets every deeper
  selection.
- The feature image/copy column remains fixed and outside the horizontal
  navigation rail. Mobile depth and accordion behavior are unchanged.

Acceptance:
- At 1024/1280px, two visible navigation columns have equal usable widths
  and long labels truncate without hiding their chevrons.
- At 1440px and above, three active columns are fully visible without
  horizontal scrolling; at wider viewports they grow to 300px.
- When a parent opens a fourth or deeper column, the rail becomes horizontally
  scrollable only from column 4 onward, retains every existing column width,
  and automatically scrolls the new column into view; leaves and parent title
  destinations remain reachable.

---

## 10. Amendment — Parent link and disclosure actions (2026-10-04)

- This amendment supersedes section 2.1 item 4 and every earlier requirement
  for a `See all %1` row.
- A parent node title resolves through the Snowdog node provider and navigates
  directly to its category/custom URL. A wrapper without a destination renders
  its title as text.
- Only the trailing chevron button opens or closes child columns, panels, or
  accordions. The disclosure button exposes its action and expanded state to
  assistive technology.
- Desktop and mobile must not render duplicate `See all` rows.

Acceptance:
- Clicking a parent category name navigates to that node's resolved URL.
- Clicking the trailing chevron opens/closes children without navigation.
- Keyboard users can focus and activate the title link and disclosure button
  independently.

---

## 11. Amendment — Desktop navigation item states (confirmed 2026-10-05)

Design source: Figma component `2151:9582` (`NavigationItem`).

- Desktop level-1 items keep Label M typography and expose a 1px indicator
  under the label.
- Inactive items have no visible indicator. Hover, keyboard-active/open, and
  the current category branch expand the indicator to the full label width.
- Indicator color is `bg/gray-solid-primary` on the normal light header and
  `bg/white` on the contextual dark/image header.
- An exact current category link exposes `aria-current="page"`. When the
  current category is a descendant, its level-1 ancestor receives the visual
  current state without incorrectly claiming `aria-current="page"`.
- The indicator transition respects the existing motion contract and does not
  change link or chevron click behavior.

Acceptance:
- Exact current category and its top-level branch remain visibly indicated.
- Snowdog block cache entries vary by current category ID so a state cached on
  one category page cannot mark that item current on another category page.
- Hover and an open mega panel show the same full-width indicator defined by
  the component in both header color modes.
- Inactive items render with zero-width indicators, and focus remains visible
  through the existing focus outline.

---

## 12. Amendment — Current/open states at every menu level (confirmed 2026-10-05)

Design sources: desktop navigation item `2151:9582`; mobile active/back row
`2949:77725`.

- Desktop level-1 current state is server-rendered independently from Alpine's
  open-state class so hydration cannot remove it.
- Desktop submenu parent and leaf rows use a 1px label underline for hover and
  current-category-path states, matching level 1. Click-selected/open parents
  retain the `brand-soft-primary` surface and
  `brand-secondary-on-brand-alt` text. Hover must not fall through to
  Snowdog's default link-color-only treatment.
- Mobile root, group, accordion parent, and accordion child rows use the Back
  row state: `primary/brand-100` background, `text/brand-primary`, existing
  bottom divider, and medium label weight. Open disclosure rows and current
  category-path rows retain this state; current rows add 8px inline-start
  padding so their text does not touch the active surface edge.
- Exact category links continue to expose `aria-current="page"`; category
  ancestors receive the same visual branch-current state without that ARIA
  value.

Acceptance:
- Reloading a current category page does not remove the desktop level-1
  indicator after Alpine initialization.
- Desktop nested hover/current states share the level-1 underline;
  click-selected/open parents keep the approved row surface and text color.
- Mobile current and open states are visible at every rendered depth and match
  node `2949:77725`; text remains medium weight.

---

## 13. Amendment — Menu identifiers and desktop presentation (confirmed 2026-10-05)

- Desktop level-0 labels are uppercase in the desktop renderer only. Stored
  Snowdog titles and the mobile renderer preserve their authored casing.
- Desktop submenu leaf links always use the light-panel ink color, including
  while the homepage Header is in its contextual white-text state.
- Stores > Configuration > Secomm > Theme exposes a Menu group immediately
  after Header, with store-scoped Desktop Menu Identifier and optional Mobile
  Menu Identifier fields.
- Desktop defaults to `hyva-topmenu-desktop`. An empty Mobile Menu Identifier
  reuses the effective desktop identifier; a non-empty value selects an
  independent Snowdog hierarchy for mobile.
- Configured identifiers change data selection only. Both renderers and their
  node types continue using the Launchpad theme templates, without vendor
  edits.

Acceptance:
- Homepage desktop leaf links remain readable on the white mega-panel.
- Desktop level-0 labels render uppercase while the same mobile labels retain
  their stored casing.
- Admin notes explain both defaults and the mobile fallback. Store-scope
  values select the expected desktop/mobile menu and preserve Launchpad UI.
