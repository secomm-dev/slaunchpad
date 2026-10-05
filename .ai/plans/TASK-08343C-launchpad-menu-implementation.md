# TASK-08343C — Launchpad Menu implementation plan

## Metadata

| Field | Value |
|---|---|
| Specification | [SPEC-FEAT-ZNJ4KF](../specs/SPEC-FEAT-ZNJ4KF-launchpad-menu.md) |
| Feature | `FEAT-ZNJ4KF` |
| External reference | `SLP-245` |
| Mode | A |
| Status | Implemented and validated; confirmed 2026-10-05 |
| Design sources | Page `2949:93615`; Desktop `2151:11236`, states `2151:9582`; Mobile `2115:5380`, `2949:77708`, `2949:77725`, `2949:93394` |
| Scope | Desktop mega panel and Mobile/Tablet drawer in the existing Header |

## 1. Outcome and scope

Implement one Snowdog menu hierarchy that drives both renderers:

- Desktop mega panel with a feature banner and progressive category columns.
- Mobile/Tablet drawer with search, language settings, group panels and
  single-open accordions.
- Category titles navigate to their configured destination. Only the trailing
  chevron discloses child categories.
- Current, hover and open states follow the approved Figma references.
- Snowdog Admin supports per-node banner content and an option to show that
  content on mobile.

## 2. Architecture decisions

1. **One hierarchy, two renderers.** Theme layout points both desktop and
   mobile blocks at `hyva-topmenu-desktop`. Snowdog `TemplateResolver` assigns
   theme-local desktop/mobile templates without editing vendor code.
2. **Reuse Snowdog node rendering.** Leaf rows use `renderMenuNode()`.
   Parent URLs resolve through the node provider; wrapper nodes remain text.
   A separate chevron button owns disclosure, so a duplicate See-all row is
   unnecessary.
3. **Feature content.** The level-0 node supplies the feature image. Banner
   copy uses the node editor value first, then optional CMS block
   `launchpad-menu-feature-<nodeId>`, then no content.
4. **Banner storage.** `Launchpad_SnowdogMenu` stores banner fields in the
   companion table `launchpad_snowdog_menu_node_banner`, keyed to the Snowdog
   node with FK cascade. This avoids modifying the vendor table.
5. **Renderer state.** Desktop owns `openSubmenuId` and branch selection
   `{l1, l2, l3}`. Mobile owns drawer state, one active group panel and one
   open accordion. Both consume the same persisted hierarchy.
6. **Drawer behavior.** Mobile uses native `<dialog>` with
   `x-htmldialog.noscroll`, focus restoration and breakpoint cleanup.

## 3. Affected areas

| Area | Planned change |
|---|---|
| Theme layout/templates | Wire the shared identifier; implement desktop panel, mobile drawer, submenu renderers, category state template and drawer children |
| `Launchpad_SnowdogMenu` | Add banner model/resource/management, Admin persistence/prefill plugins and storefront ViewModel helpers |
| Admin UI | Extend the Snowdog node editor with banner WYSIWYG and mobile visibility checkbox; retain textarea fallback |
| Theme configuration | Add desktop/mobile Snowdog identifiers under Secomm > Theme > Menu, with mobile-to-desktop fallback |
| Schema | Add companion table, declarative schema and whitelist |
| Assets/i18n | Add menu SVG assets and vi/en strings; rebuild Tailwind CSS |
| Documentation/evidence | Keep spec, plan, task record, current state and QA evidence aligned |

## 4. Implementation sequence

### Phase 1 — Shared menu wiring

1. Configure desktop and mobile blocks to consume `hyva-topmenu-desktop`.
2. Assign theme-local renderer and submenu templates.
3. Add drawer logo, search and language child blocks with unique IDs.

### Phase 2 — Desktop mega panel

1. Implement open/close, hover delay, Escape, outside-click and focus return.
2. Render feature image/content and progressive category columns.
3. Show two columns below 1440px and three columns from 1440px; allow
   horizontal scrolling for column 4+ and scroll the newly opened column into
   view.
4. Keep category names as destination links and disclosure on chevrons only.
5. Apply persistent current underline, hover underline and brand-soft selected
   parent state at every rendered depth.
6. Keep leaf text ink-colored on the white panel and uppercase level-0 labels
   in the desktop renderer only.

### Phase 3 — Mobile/Tablet drawer

1. Implement root-to-group navigation, Back/Close/Escape and focus/scroll
   restoration.
2. Render single-open accordions with separate category links and disclosure
   buttons.
3. Build full-width search with an overlaid submit button.
4. Render active stores with the current-language flag and selection state.
5. Apply mobile current/open surfaces, medium text and current-row padding.

### Phase 4 — Banner editor extension

1. Add declarative companion schema and persistence services.
2. Extend Snowdog node serialization and Admin prefill without direct SQL.
3. Initialize TinyMCE eagerly when available; preserve plain textarea fallback
   and synchronize/destroy the editor during component lifecycle changes.
4. Render desktop banner content using editor → CMS block → empty precedence.
5. Gate all mobile banner copy behind `show_banner_content_mobile`.
6. Invalidate `block_html` and `full_page` once per changed tree save; preserve
   data belonging to other menus.

### Phase 5 — Validation and handoff

1. Run PHP/XML lint, Magento DI compile, Tailwind production build and
   `git diff --check`.
2. Verify desktop/mobile behavior, accessibility state, cache persistence,
   cross-menu isolation and responsive widths 320–1920px.
3. Record per-AC results and screenshots under
   `.ai/evidence/TASK-08343C/` and synchronize project state documents.

### Phase 6 — Configurable menu identifiers

1. Add store-scoped desktop and mobile identifier fields below Header config.
2. Default desktop to `hyva-topmenu-desktop`; resolve blank mobile to desktop.
3. Pass identifiers to Snowdog blocks during layout argument evaluation.
4. Map configured header-menu identifiers to the canonical Launchpad template
   namespace so separate data trees retain the same desktop/mobile UI.
5. Cover configured, default and fallback behavior with unit tests.

## 5. Acceptance and validation

Acceptance criteria are canonical in SPEC-FEAT-ZNJ4KF §5 and §8. The task
record maps results for AC-M01…AC-M12 and the banner criteria.

Completed validation includes:

- Shared hierarchy persistence and both storefront renderers.
- Deep-tree desktop columns, mobile accordions and responsive overflow.
- Banner editor persistence, editor/CMS fallback, mobile visibility matrix,
  cache invalidation and cross-menu isolation.
- Current/open/hover category states and exact `aria-current="page"` semantics.
- PHP/XML lint, Magento DI compile, Tailwind production build and diff checks.

Manual device follow-up remains for virtual keyboard/safe-area behavior and
logged-in visual QA. These checks do not change the implementation scope.

## 6. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Desktop columns become too narrow | Fixed visible-column policy by breakpoint plus horizontal overflow for deeper levels |
| Parent disclosure prevents category navigation | Separate link and chevron controls with independent accessible labels |
| Current state disappears after Alpine hydration | Keep server-rendered current classes independent from reactive open state |
| One cached current item appears on every category | Add current category ID to the Snowdog block cache key |
| Admin editor fails to load TinyMCE | Lazy-load TinyMCE and retain a functional textarea fallback |
| Saving one menu removes another menu's banner data | Persist by node ID and rely on FK cascade; no cross-menu purge |
| Excess cache clearing | Dirty detection and one invalidation after the full tree save |
| Local fixture data leaks into delivery | Exclude fixture state, backups, scripts and `pub/media/snowdog/**` from commits |

## 7. Explicitly out of scope

- My Account drawer row, Currency and social links.
- Footer and authentication changes.
- Vendor changes in `app/code/Snowdog/Menu`.
- Shipping local fixture state, backup/snapshot JSON, probe/test scripts or
  demo media.
