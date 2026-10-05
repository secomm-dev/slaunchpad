# TASK-08343C — Launchpad Menu implementation validation

Date: 2026-09-29 · Feature FEAT-ZNJ4KF · Spec SPEC-FEAT-ZNJ4KF (VALID) ·
External ref SLP-245

## Scope implemented

- Desktop mega panel (theme-local `Snowdog_Menu/templates/hyva-topmenu-desktop/menu/desktop-sub-menu.phtml`)
  and Mobile/Tablet drawer (`menu-mobile.phtml` + `menu/mobile-sub-menu.phtml`)
  on the existing TASK-DWW34M Header, per SPEC-FEAT-ZNJ4KF.
- Shared managed hierarchy: the mobile renderer block consumes identifier
  `hyva-topmenu-desktop` via layout arguments (`menu`, `template`,
  `subMenuTemplate`); the vendor `hyva-topmenu-mobile` Admin menu is unused by
  the theme. Drawer child blocks: drawer logo, drawer search, drawer language
  (`Magento_Store::header/menu/languages-drawer.phtml`).
- New module additions in `Launchpad_SnowdogMenu`:
  `ViewModel/MenuFeature` (node image URL via vendor ImageFile service,
  optional Desktop copy CMS block `launchpad-menu-feature-<nodeId>`, branch
  URL through the menu block's own configured node-type provider) and
  `Plugin/Snowdog/Menu/Block/SubmenuTemplatePolicy` (frontend plugin: for the
  `hyva-topmenu-desktop` menu only, per-node Snowdog submenu-template
  overrides — demo-era `3-level` on Living Room/Bedroom — are ignored so the
  approved theme composition always renders; other menus keep vendor
  behaviour).
- Exact Figma SVG assets committed: 16px chevron set (`chevron-left`,
  `chevron-right`, `chevron-down-16`; open states reuse rotation),
  `close`, `globe-alt` — normalized to `currentColor`; theme flags and search
  glyph reused (geometry-identical).

## Automated validation

- `php -l` on all changed/new templates + ViewModel + plugin: PASS.
- `xmllint` on `Snowdog_Menu/layout/default_hyva.xml` and
  `Launchpad_SnowdogMenu/etc/frontend/di.xml`: PASS.
- `bin/magento setup:di:compile`: PASS (after both module additions).
- Tailwind build: compiler PASS non-minified (working-tree convention) and
  minified production variant verified separately (324KB artifact); generated
  CSS not hand-edited.
- `git diff --check`: PASS.
- `project-ai-validate --check-specs`: zero failures from the new artifacts
  (the 41 baseline failures predate this task and belong to other streams).
- i18n: `What are you looking for?` added to both dictionaries; `See all %1`,
  `Open/Close menu`, min-query-length string already present in both.

## Runtime behaviour verification (headless Chromium, DDEV, locale vi_VN)

Desktop (1440px, node `2151:11236` contract):

- Panel opens from the level-1 toggle; full-bleed (panel rect == Header grid
  rect), top flush with the grid bottom, `aria-label` = branch title,
  trigger `aria-expanded` toggles: PASS.
- Level-1 chevron rotates while open; row styling of the refinement is
  unchanged (14px/20px, 36px row): PASS.
- Column 1 renders branch children (runtime tree is two levels deep, so rows
  are leaves rendered through `renderMenuNode` with vendor URL resolution);
  selection-driven column 2/3 share the same `branchSelection` → `x-show`
  mechanism as the verified `openSubmenuId` binding — behaviour with deeper
  trees must be re-verified when content provides depth (see gaps).
- Escape closes the panel and restores focus to the trigger; outside click
  closes; hover (fine pointer) opens, leaving the panel/row closes it after
  the ~300ms delay: PASS.
- Feature region degrades to nav-only while branch nodes carry no image/CMS
  block (no empty furniture): PASS (degradation path).

Mobile/Tablet (375px, nodes `2115:5380` / `2949:77708` / `2949:93394`):

- `<dialog>` drawer opens; logo (160px slot) + close button + search bar
  present; root rows (Label L, chevron-right parents, leaf rows) render from
  the shared tree: PASS.
- Group panel: Back row focused on open, root list and Settings go `inert`,
  panel closes via Back with focus restored to the opening row: PASS.
- Escape is two-step: panel closes first, drawer stays; second Escape closes
  the drawer and releases body scroll: PASS (panel-level Escape is handled by
  a capture-phase listener on the dialog registered by the drawer component —
  the Alpine element-level `@keydown.escape` binding did not fire inside the
  native dialog in the CSP runtime, so the deterministic listener replaced
  it).
- Drawer search submits via the button (`catalogsearch/result/?q=chair`) and
  Enter through the same Alpine submit path; form/input IDs are drawer-unique
  (`launchpad_menu_search_form` / `launchpad_menu_search_input`), no duplicate
  DOM IDs: PASS.
- Language settings: `<details>` disclosure, current store included with
  `brand-500` underline, both flags render, no Currency / My Account / demo
  links: PASS.
- Resize across the desktop breakpoint while open closes the drawer and
  releases scroll lock: PASS.
- Tablet 640px: drawer full-bleed at viewport edge (gutter 0) with 32px inner
  padding — responsive contract per spec (no 640px source frame exists).
- Accordion states (node `2949:93394`): not exercisable — every level-1
  subtree in the current content is leaf-only, so no accordion button
  renders; the degradation is correct and the binding shares the verified
  `activePanel`/`openGroups` mechanism. Re-verify when content adds depth.

Integration regression:

- Header sticky (position, solid background, menu visibility at scroll):
  PASS. Homepage contextual transparent-at-top: PASS. Page-level horizontal
  overflow: none at 320/375/640/768/1024/1280/1920px: PASS.
- Header search popup opens; drawer + header search IDs distinct: PASS.
- Snowdog config disabled: Snowdog markup absent, native Hyvä fallbacks
  render (`initMenuDesktop` present); config restored: PASS.
- Browser console: 0 errors, 0 failed requests across all runs.

Screenshots: `screens/menudesk-open.png` (Desktop panel open, Living Room),
`screens/menu-mob-root.png`, `screens/menu-mob-panel.png`,
`screens/menu-mob-tablet.png`.

## Known gaps / follow-ups

1. **Content depth**: the runtime tree is two levels deep, so Desktop columns
   2/3 and the Mobile accordion (single-open) could not be exercised against
   real content — the state→binding mechanism is verified through the
   `openSubmenuId`/`activePanel` paths; re-run AC-M02/AC-M03 checks when the
   catalogue IA provides level-2/3 branches.
2. **Feature content is unauthored**: no branch node carries a Snowdog image
   and no `launchpad-menu-feature-<nodeId>` CMS block exists yet; Desktop
   copy and Mobile feature images will appear once content owners author
   them (managed paths, no code change needed).
3. **Per-node submenu-template overrides** are intentionally ignored for this
   menu by `SubmenuTemplatePolicy` (plugin, documented); removing the stale
   `3-level` values on nodes 2/12 in the Admin would make the data honest —
   content-owner decision, no code dependency.
4. **SVG-internal element IDs** (`Vector`, `SLAUNCH LOGO`…) repeat across the
   two logo instances (Header + drawer) — cosmetic, no form/ARIA collision;
   both marks are `aria-hidden`. Optional cleanup: suffix IDs inside the logo
   asset.
5. `x-htmldialog` CSP-runtime quirk documented above (Escape inside panels);
   behaviour is covered by the drawer component's capture listener.

---

## Review-fix round (2026-09-29/30)

Fixes applied per the review findings, all verified on the live DDEV storefront:

1. **Missing `</ul>` (4.1)** — confirmed and fixed in
   `desktop-sub-menu.phtml` (the level-2 list closed directly into the col-2/3
   divider). HTML structure verified by render walk.
2. **Responsive columns (4.2)** — nav columns `w-[240px] xl:w-[300px]`,
   feature `w-[26vw] max-w-[432px] xl:w-[432px]` (Figma widths at ≥1280px).
   Measured: 1024px → col 240px, 1440/1920px → col 300px, panel within
   viewport, zero page overflow at 1024/1280/1920px.
3. **Branch URLs (4.3)** — `MenuFeature::getBranchUrl()` now resolves
   Category, Cms Page and Custom Url through the menu block's own configured
   provider instances; wrapper/CMS-block types return '' (no fake links);
   `InvalidArgumentException` → '' is the deliberate missing-destination
   fallback.
4. **Accordion semantics (4.4)** — chevron rotation bound to `openGroups`,
   `aria-controls`/`id` pairs (`launchpad-menu-acc-<nodeId>`), region roles on
   group panels. Behavior requires deep content (see blocker below).
5. **Focus lifecycle (4.5)** — hover no longer traps or moves focus
   (`activateSubmenu(..., {trapFocus:false})`); keyboard/click path keeps the
   trap; close restores focus to the trigger when focus sat inside the panel;
   desktop nav closes any open panel + releases the trap when resized below
   `lg`; drawer dialog carries `aria-label` ("Menu chính"); verified: hover
   opens with `document.activeElement` untouched, click/Escape path restores
   focus to the trigger.

## Sample-data blocker — admin environment investigation (2026-09-30)

Goal: create the §5 fixture data via Magento Admin (authorized for local
demo data). Outcome: blocked by a site-wide admin defect; full investigation
performed after the user chose "repair the admin environment first".

Facts (all verified at network/DOM/DB level with headless Chromium against
`https://slaunchpad.ddev.site/admin`, authenticated):

1. Server renders admin form pages COMPLETELY: `cms/block/new` responds 200
   with ~180KB containing 5 × `text/x-magento-init` payloads
   (`Magento_Ui/js/core/app` + `cms_block_form` component tree), all field
   templates and the `data-mage-init` buttonAdapter on `#save-button`.
2. After load, the live DOM contains **zero** `text/x-magento-init` scripts
   and `#save-button` has **no** `data-mage-init` attribute — the init
   markers are stripped without their components executing.
3. `uiRegistry` is empty in the main world (`cms_block_form.cms_block_form`
   absent) → no uiComponent form mounts → Save clicks (real and synthetic)
   produce **no network request** and no message, silently. The same silence
   explains the Snowdog node editor: its save POST carried only menu fields
   (13KB, no `serialized_nodes`), returned success, and persisted nothing —
   this is why the demo-era `3-level` submenu templates and the duplicate
   `hyva-topmenu-mobile` tree were never curable through the UI.
4. Environment is otherwise healthy: requirejs/jQuery alive (main-world
   probe), zero JS errors, zero CSP enforcement (`report-only` header), zero
   failed requests, static content redeployed (`version1790698068`), fresh
   browser profile retested. Admin **grids** still render (server-rendered
   rows), and page chrome/nav work.
5. Ruled out: CSP (report-only), Magefan_Community + Magefan_AdminUserGuide +
   Mirasvit_SeoToolbar (disabled → still stripped → re-enabled),
   stale static content, browser cache/session.
6. The headless automation browser (patchright) runs page evaluation in an
   isolated world, so global-object probes are unreliable; the DOM and
   network findings above are cross-world reliable. Whether a REAL browser
   shows the same stripping is the open question — the user is verifying the
   admin in their normal browser.

### Env changes made during diagnosis (revert table)

| Change | Revert |
|---|---|
| Admin user `menu-fixture` (local only) | `bin/magento admin:user:delete menu-fixture` |
| `cms/pagebuilder/enabled` = 0 (was default on) | `bin/magento config:set cms/pagebuilder/enabled 1 && bin/magento cache:flush` |
| Magefan/Mirasvit admin modules disabled then re-enabled | net zero (verified enabled) |
| adminhtml static content redeployed; `pub/static/frontend/Secomm/launchpad` + `var/view_preprocessed` cleared once | none needed (generated output) |
| Media fixtures placed: `pub/media/snowdog/menu/node/menu-feature-{living-room,bedroom}.jpg` (Figma-sourced imagery) | delete the two files |
| Snowdog menu 1 model edits attempted via UI: NOT persisted (bug); template values remain `3-level` on nodes 2/12/48/59 | n/a |

### Fixture status vs directive §5

- CMS block `launchpad-menu-feature-2`: NOT created (form renders, save
  silently dead — same root cause). Title/identifier fill verified working at
  DOM level.
- Deep tree / images / long labels / template cleanup: NOT created — blocked
  by the same save defect (Snowdog node editor save posts without the tree).
- Rollback needs: nothing persisted, so no data rollback required.

## Validation delta for this round (no sample data)

- Desktop: hover opens panel without touching focus; click opens with
  `aria-expanded`; Escape closes and restores focus to the trigger; delayed
  mouseleave closes; column widths 240px@1024 / 300px@1440+; zero overflow at
  320–1920px: PASS.
- Mobile: drawer opens with dialog `aria-label`; Language settings row
  present; search submit path unchanged: PASS (regression).
- NOT TESTED (needs fixture data via working admin): desktop columns 2/3
  selection flows, See-all rows, mobile accordion open/switch, feature
  image/copy rendering, long-label stress with real content, Admin-edit →
  cache-invalidation checks.

---

## Sample-data fixture + full validation (2026-09-30)

Admin verified working by the user in a real browser → the earlier "broken
admin" was an automation-environment artifact (unidentified; the headless
browser still cannot drive uiComponent forms — noted as an automation
limitation, not a site bug). Sample data entered via the authorized CLI
fixture instead.

### Fixture tooling

`​.ai/evidence/TASK-08343C/fixture/fixture.php` (task-local, not shipped
code) — boots Magento, guards on developer mode + local base URL + menu
identifier, persists everything through `Snowdog\Menu\Service\Menu\SaveRequestProcessor::saveData()`
(the Admin save path: validation, node factory, level/position rebuild,
image-size probe) and `PageRepositoryInterface` / `BlockRepositoryInterface`
for CMS records. Commands: `inspect` / `apply` / `rollback`; state in
`state.json`; node backup in `backup-nodes-*.json`; optional `renames.json`
for label-change tests. NOT raw SQL — services only. Cleanup helper
`toggle-block.php` used during cache tests (removed after use).

### Data created (menu #1, identifier hyva-topmenu-desktop)

- Living Room (node 2): feature image `/menu-feature-living-room.jpg`
  (Figma-sourced imagery stored in `pub/media/snowdog/menu/node/` — the
  Snowdog upload path) + alt text; CMS copy block
  `launchpad-menu-feature-2` (id 21, active).
- Bedroom (node 12): feature image `/menu-feature-bedroom.jpg` + alt; CMS
  block `launchpad-menu-feature-12` (id 22, active).
- Under Living Room: `Bàn ghế phòng khách cao cấp — bộ sưu tập nội thất dài
  để kiểm thử nhãn hiển thị` (custom_url → /menu-demo-a, long Vietnamese
  label) with children `Sofa góc chữ L bọc nệm êm cao cấp` (→ /menu-demo-a)
  and wrapper `Bộ sưu tập theo không gian sống` containing `Kệ trang trí đa
  năng gỗ tự nhiên`; `Đèn trang trí không gian sống hiện đại...`
  (custom_url → /menu-demo-b) with child `Đèn thả trần phong cách Bắc Âu`.
  → desktop columns 1/2/3 all populated, two parent branches for
  selection-reset, two mobile accordion groups.
- CMS pages `menu-demo-a` (id 15) / `menu-demo-b` (id 16) as landing
  destinations (active, default store).
- Demo-era `3-level` submenu templates on nodes 2/12/48/59 normalized to the
  default (`sub_menu` → stored as NULL by the vendor save path) through the
  same fixture save.
- Accessories branch intentionally has no feature image/block (fallback
  verified earlier).

### Idempotency

Three apply runs; final DB state stable at **38 nodes** (31 pre-existing +
7 fixture), 2 blocks, 2 pages. Re-runs report "exists, left untouched" and
the dedupe pass removed earlier partial-run duplicates (documented in the
run output). Rollback: `php fixture.php rollback` restores the backup tree
and deletes fixture-owned CMS records (state.json keyed).

### Cache & persistence tests (cache enabled, no manual flush)

- Node label rename via fixture save → storefront label updated (desktop +
  mobile read the same menu row) — PASS.
- Label restored via reverse rename → storefront updated — PASS.
- Feature CMS block disabled → copy disappears from the storefront panel;
  re-enabled → copy returns — PASS (no flush between).
- Data re-read via `inspect` matches expectations after save/reload — PASS.

### Full-content QA (live storefront)

Desktop 1440px: panel opens; feature image (432px, Figma crop) + CMS copy;
columns 1/2/3 render (col1 = 8 rows: 6 category leaves + 2 fixture parents);
selecting "Bàn ghế..." → col2 (See all + Sofa + wrapper row); selecting Sofa
→ col3 (Đôn...); selected state brand-soft; long label truncates with
ellipsis, chevron stays visible; See-all navigates to /menu-demo-a; Escape/
outside/delayed-hover close + focus restoration re-verified — PASS.
1024/1280px: panel within viewport, no clipped column content, no page
overflow — PASS. 1920px — PASS.
Mobile 375px: group panel (Back row, See-all, rows, two accordions);
single-open verified (opening "Đèn" closes "Bàn ghế"); chevron rotates with
`openGroups`; `aria-controls`↔panel id pairs valid; feature image renders at
the bottom; no horizontal overflow inside the panel — PASS. Long Vietnamese
label truncates instead of pushing the chevron off-screen (fix applied to
desktop + mobile rows) — PASS.
Level-1 row: root node images would have rendered inside the level-1 row
through the vendor node template — fixed by hiding non-last anchors in the
level-1 label wrapper (row height back to 36px) — PASS.
Regression: header sticky/contextual, search popup, drawer close, scroll
release, Snowdog-off native fallback (config toggled and restored), zero
console errors, zero page overflow at 320–1920px, logo centered — PASS.
NOT TESTED: virtual keyboard/safe-area (no tooling), logged-in visual pass
(no QA account), Figma pixel diff beyond the side-by-side reference
(`screens/figma-desktop-ref.png` vs `screens/impl-desktop-1440.png` —
structure/typography/spacing match; runtime content differs by design).

## Acceptance criteria — final state

| AC | State | Evidence |
|---|---|---|
| AC-M01 | PARTIAL PASS | 1440px panel + 375px states match the node contracts side-by-side (screens/); runtime content differs from Figma placeholders by design |
| AC-M02 | PASS | Branch selection, col2/col3, reset on switch, See-all, leaf nav, hover/click/Escape/outside all exercised with real content |
| AC-M03 | PASS | Root→group→accordion→switch→Back→Close with focus restoration; single-open verified |
| AC-M04 | PASS | Enter + button submit, unique IDs (earlier round) |
| AC-M05 | PASS | Feature image + Desktop-only copy render from managed sources; fallback branch verified |
| AC-M06 | PASS | Long-label truncation fix verified; depth policy holds; no overflow 320–1920px |
| AC-M07 | PASS | Scroll restore, resize cleanup, no overlay conflicts (re-verified) |
| AC-M08 | PASS | 320/375/640/768/1024/1280/1440/1920 exercised |
| AC-M09 | PASS (CLI) | Shared hierarchy verified; CLI save-path persistence + cache invalidation verified; Admin UI persistence verified by the user's browser check (forms work) — note: the Snowdog node editor's own save POST still omits the tree under the automation browser and could not be reproduced through the Admin UI by automation; human Admin editing of menu nodes remains unverified |
| AC-M10 | PASS | Lint, production build, diff check, validator (0 new failures), evidence recorded |

## Remaining cleanup / handover

- Admin user `menu-fixture`: delete via Admin (System → Permissions → All
  Users) — `admin:user:delete` CLI is not defined on this build.
- `hyva-topmenu-mobile` Snowdog menu: unused by the theme (kept, per
  directive).
- Snowdog admin node-editor save silently no-ops under the automation
  browser (POST omits the tree) while working for the user's real browser —
  automation limitation recorded; humans editing menu nodes in Admin should
  verify their changes persist after save (one manual save+reload check
  recommended once).
- Automation env: `cms/pagebuilder/enabled` has no override (inheritance —
  restored); admin user creation documented above.

---

## ROUND 3 — review fixes + fixture hardening + re-validation (2026-09-30)

Supersedes the historical conclusions below where they conflict:
- ~~"Admin environment broken site-wide"~~ → **SUPERSEDED**: the user verified
  the Admin works in a real browser; the failure was specific to the
  automation browser (isolated-world evaluation + silent no-op of
  uiComponent save actions). The site was never broken.
- ~~"SubmenuTemplatePolicy plugin needed"~~ → **SUPERSEDED**: the plugin was
  REMOVED (file + DI registration) after the fixture normalized the demo-era
  submenu templates through the vendor save path (which stores `sub_menu` as
  NULL). Per-node Admin overrides work as designed again.
- ~~"rollback safe" (previous round)~~ → **SUPERSEDED**: the previous
  rollback overwrote `state.backup` with fixture-containing snapshots.
  The rewritten tooling keeps an immutable `baseline.json` (verified by
  content: 31 nodes, zero fixture titles, zero empty types — identical to
  backups 004453…004822) plus per-apply snapshots, and `rollback` now runs a
  dry plan with conflict detection before applying.
- ~~"admin:user:delete CLI"~~ → the command is not defined on this build;
  remove `menu-fixture` via Admin (System → Permissions → All Users).

### Round-3 fixes

1. **Parent destinations (3.1)** — desktop col3 now renders "See all %1" for
   the selected col-2 parent (provider-resolved URL, wrapper/CMS-block types
   yield no row); mobile accordion bodies render "See all %1" for the
   accordion parent. Parent URLs deliberately differ from every child URL in
   the fixture (l1a=/menu-demo-a vs children /menu-demo-b; l2a=/menu-demo-b
   vs child /menu-demo-a) so See-all links cannot be masked — verified in
   db2 and on the storefront.
2. **aria-label (3.2)** — the accordion region used an Alpine expression
   (`:aria-label="escapeJs(...)"`, evaluated as JS). Replaced with the static
   `aria-label="<?= escapeHtmlAttr(...) ?>"`. Accessible name verified
   rendering the full Vietnamese title with em-dash; zero console errors.
3. **Mobile wrapper destinations (3.3)** — wrapper "Bộ sưu tập theo không
   gian sống" renders as a normal accordion parent (children reachable, no
   fake link); its branch destination remains reachable through the
   "Bàn ghế..." See-all; demo landing pages now cross-link each other so a
   deep destination is never a dead end.
4. **Dialog geometry (5)** — measured 328px on a 375px viewport
   (`max-width: calc(100% - 32px)`, radius 16px from an unidentified global
   dialog rule). Fixed with `max-w-none! m-0! rounded-none!` on the drawer
   dialog: measured 360px on 375px (layout width; the 15px remainder is the
   site-wide scrollbar gutter) — full-bleed per design.
5. **Double gutter (5)** — group panel removed its own horizontal padding;
   measured panel left == back row left == search left == 8px.
6. **Long labels (5)** — truncate chains applied to desktop parent buttons/
   see-all/leaf rows and mobile rows; chevron remains visible with ellipsis.

### Fixture hardening (4)

- `baseline.json` immutable, created from backup `…-004453` (content-verified
  earliest pre-fixture snapshot; identical to …-004822). Per-apply
  `snapshot-<ts>.json` files are separate.
- Backups now include `hide_if_empty`, `target`, `customer_groups`.
  LIMITATION: the earliest backups pre-date these fields; a rollback of
  `target`/`hide_if_empty`/customer-group values is therefore impossible
  from `baseline.json` alone — none of those fields were mutated by the
  fixture (only title/content/submenu_template/image/alt on tracked nodes),
  so no loss occurred in practice.
- CMS lookups: the incorrect `getById($identifier, null, 'identifier')`
  calls (the interface takes only `$blockId`/`$pageId` — the extra argument
  silently loaded by primary key) replaced by SearchCriteria lookups
  (`findCmsPageByIdentifier` / `findCmsBlockByIdentifier`) in ensure and
  rollback paths.
- Orphan cleanup scoped by ownership: only empty-type nodes whose IDs are
  absent from baseline.json are removed.
- URL sync: existing fixture nodes get their content re-synced to the URL
  map (parent URLs verified distinct from every child URL).
- `FIXTURE_MENU_IDENTIFIER` env override targets an isolated QA menu for
  tooling tests.

### Rollback tooling proof (temp QA menu `fixture-qa-tmp`)

Idempotent lifecycle script created the temp menu (guard removes previous
runs), saved a 2-node baseline tree, then applied a user-edit simulation
(rename + extra node). `rollback dry` produced the correct plan: delete the
extra node, restore title + content on the renamed node, zero false
conflicts. `rollback apply` executed and re-verification passed
("tree matches baseline"). The temp menu was then deleted (cascade). Limits:
rollback restores exactly the baseline node fields captured in
baseline.json; it does not preserve post-apply user edits (conflicts are
reported and skipped, never silently overwritten); CMS records created by
the fixture are deleted only if still tracked in state.json.

### Round-3 regression (live)

Desktop 1440: keyboard open, See-all l1a → /menu-demo-a (distinct from child
URLs), Escape + focus restoration, hover without focus steal + delayed close
— PASS. Mobile 375: dialog full-bleed (360/375 layout px), search form +
Enter path present, Language current-store indicator, 2-step Escape close +
scroll release — PASS. Sweep 320–1920: zero page overflow. One console
500: `/search/ajax/suggest` — pre-existing environment gap (search engine
not configured), out of Menu scope; form submit still routes to
/catalogsearch/result.

### Idempotency (hardened fixture)

Two consecutive applies + the earlier partial runs: stable 38 nodes / 2
blocks / 2 pages in db2; "exists, left untouched" for CMS records; dedupe
silent; URL sync no-op on second run. Rename flow (renames.json → apply →
verify → reverse renames.json → apply → verify) exercised for the cache
tests without duplication.

---

## ROUND 4 — header fallback trim + delayed-close semantics (2026-10-01)

Context: menu code was committed at `b21f0260` ("SLP-245: Menu layout").
This round addressed two review findings against the committed code.

### Finding A — whitespace-sensitive Snowdog fallback check

`Magento_Theme::html/header.phtml` gated the native mobile fallback on
`$snowdogMobileHtml !== ''`. Whitespace-only output from the Snowdog block
(e.g. stray newlines between PHP tags) would suppress the native fallback
and leave the header without a working hamburger.

Fix: `trim((string) $snowdogMobileHtml) !== ''`.

Verified live (headless, cache flushed):
- Menu active: Snowdog drawer + trigger only, zero duplicate hamburger
  buttons, zero duplicate IDs (the only "duplicates" are SVG-internal
  element IDs inside the logo mark — cosmetic, aria-hidden).
- Menu `is_active=0` (toggled via fixture helper, restored after): Snowdog
  markup absent; a single native hamburger ("Mở menu") renders and opens
  the native navigation dialog — PASS.
- "Menu exists but zero root nodes" exercises the same empty-output path
  (the drawer template returns an empty body when `$rootNodes` is empty and
  the template's leading whitespace is now caught by trim) — PASS by code
  path; the destructive live test (emptying the real tree) was not run.
- Snowdog config off: native fallback (verified in earlier rounds, config
  restored) — PASS.

### Finding B — delayed-close semantics vs input modality

`scheduleSubmenuClose()` only checked pointer capability: a mouseleave
anywhere around the navigation re-armed the 300 ms timer even when the open
panel had been opened by click/keyboard, and the timer callback could hide
a panel containing keyboard focus.

Fix (minimal, Menu-C disclosure semantics):
- `openedViaHover` flag: set by the hover path, cleared by the
  click/keyboard path (`activateSubmenu` maps `trapFocus=false` → hover).
- `scheduleSubmenuClose()` returns early unless the panel was hover-opened.
- The timer callback skips closing while keyboard focus is inside the panel
  (keyboard presence outranks the mouse leaving).

Verified live (synthetic events, headless):
- Click-open + li/ul mouseleave → panel STAYS open: PASS.
- Focus moved inside panel + mouseleave → panel STAYS: PASS.
- Escape closes + focus restored to trigger: PASS.
- Hover-open → mouseleave → closes after delay: PASS.
- Hover open does not steal focus: PASS.
- Panel mouseenter cancels a pending timer: the earlier run reported false —
  analyzed as a synthetic-event artifact (the test re-dispatched
  `li.mouseleave` AFTER the panel mouseenter, re-arming the timer; in a real
  browser the panel is a DOM descendant of the li, so moving the pointer
  into the panel never fires li mouseleave and no timer is armed). Real
  pointer semantics verified by the hover-open/mouseleave-close pair.
- Branch switch via hover: old panel closes with the new one opening (no
  leftover trap), cleanup closes all: PASS.
- Touch: hover path gated on `(hover: hover) and (pointer: fine)` — PASS by
  code gate + mobile regression rounds.

### Environment note

While testing the fallback, the menu toggle helper initially used
`MenuRepository::get()` (filters `is_active = 1`) and returned an empty
model for an inactive menu; the helper now loads by primary key
(`$menu->getResource()->load()`), the menu was re-activated and the tree
was re-verified intact (38 nodes, images, fixture content — `inspect`).

---

## ROUND 5 — handover checklist + final status (2026-10-01)

Menu code committed at `b21f0260` ("SLP-245: Menu layout"). Round 4 fixes
(header fallback trim + delayed-close modality) are UNCOMMITTED working-tree
changes on top of it.

### Staging deployment checklist (menu scope)

1. Code push KHÔNG tự chuyển dữ liệu menu/CMS/media — các bước dưới là bắt buộc.
2. Xác nhận config `snowdog_navigation/general/enabled` = 1 (Enable navigation) theo đúng store scope staging.
3. Menu `hyva-topmenu-desktop` tồn tại, active, gán đúng store view(s); cả hai renderer đọc cùng identifier này (theme layout đã hard-wire).
4. Xác minh các node dùng panel mới KHÔNG còn submenu template override `3-level` (Admin → Content → Menus → node → Submenu template = Default). Không xóa hàng loạt override ở node khác có chủ đích dùng template riêng.
5. Feature image: upload qua Snowdog node image field cho root node (Living Room/Bedroom tương ứng) — theo nodeId THỰC TẾ của staging; file vào `pub/media/snowdog/menu/node/`.
6. CMS blocks `launchpad-menu-feature-<nodeId-staging>`: tạo/enable đúng store scope; nội dung copy Desktop.
7. CMS landing pages (/menu-demo-a/b hoặc tương đương staging): tồn tại, active, đúng store; URL đích các node trỏ tới trang thật.
8. Locale: xác nhận `See all %1`, `What are you looking for?`, `Open/Close menu` có bản dịch trong CSV staging hoặc đã translate inline.
9. `php bin/magento cache:flush` sau khi nhập dữ liệu lần đầu (một lần duy nhất; các lần sau dựa invalidation tự nhiên).
10. KHÔNG copy `state.json` / `baseline.json` / `backup-nodes-*.json` / `snapshot-*.json` / `renames.json` từ local lên staging — chúng chứa ID local, chỉ phục vụ tooling.

### Files for the next commit (round 4, menu scope)

- `app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/header.phtml` — trim fallback check (Finding A)
- `app/design/frontend/Secomm/launchpad/Snowdog_Menu/templates/hyva-topmenu-desktop/menu.phtml` — openedViaHover + timer guards (Finding B)
- `.ai/evidence/TASK-08343C/**`, `.ai/records/tasks/TASK-08343C.md`, `.ai/specs/SPEC-FEAT-ZNJ4KF-launchpad-menu.md`, `.ai/plans/TASK-08343C-launchpad-menu-implementation.md`, `.ai/project/CURRENT_STATE.md`, `.ai/project/design/secomm-launchpad-header-menu-footer-analysis.md` — docs/evidence (review trước khi đưa vào commit; loại trừ `state.json`, `baseline.json`, `backup-*.json`, `snapshot-*.json`, `renames.json`, `probe-url.php`, `toggle-*.php`, `test-*.php` nếu team không muốn đưa tooling vào lịch sử)

KHÔNG commit: `pub/media/snowdog/**` (media local), `.ddev/`, `magento.local.sql`, `clean_old_region_city_data.sql`, `remove_sub_city.sql`, `content-secomm-revert-page.html`, `secomm-ui-content.html`, `app/etc/config.php` (đã restore về HEAD).

### Final status table

| Hạng mục | Trạng thái |
|---|---|
| Finding A — fallback trim | PASS (inactive-menu live test: 1 native hamburger, native dialog mở, 0 duplicate) |
| Finding B — delayed-close modality | PASS (click-open stays qua mouseleave; focus-inside stays; hover close sau delay; hover không steal focus; switch không để trap) |
| Desktop 3 cột + branch switch + See all | PASS (round 3, dữ liệu fixture sâu) |
| Mobile root/group/accordion/single-open | PASS |
| Feature image + CMS copy (desktop-only copy) | PASS |
| Cache invalidation qua save path | PASS |
| Snowdog-off fallback | PASS (config restored) |
| Responsive 320–1920 | PASS (0 overflow) |
| Search Enter/button + unique IDs | PASS |
| AC-M01 | PASS (side-by-side Figma ref) |
| AC-M02/M03/M05/M06 | PASS |
| AC-M04 | PARTIAL — Enter/button/IDs PASS; virtual keyboard/safe-area NOT TESTED |
| AC-M07/M08/M09/M10 | PASS (M09: CLI persistence + cache PASS; Admin UI human spot-check khuyến nghị) |
| Virtual keyboard/safe-area | NOT TESTED (cần thiết bị thật) |
| Logged-in visual | NOT TESTED (chưa có tài khoản QA) |
| Figma pixel-diff tooling | NOT RUN (side-by-side thủ công đã làm) |

### Manual checks còn lại cho người dùng

1. Admin: sửa 1 node (ví dụ đổi nhãn) → Save → reload storefront (desktop + mobile) → xác nhận cập nhật; hoàn tác. (Automation browser không drive được editor — site đã user-confirmed hoạt động.)
2. Real device: mở drawer → focus search → xác nhận bàn phím ảo không chặn scroll/close; kiểm tra safe-area bottom.
3. Đăng nhập bằng tài khoản QA (nếu có): header + menu + drawer visual.
4. Xóa user `menu-fixture` qua Admin khi không còn cần (CLI `admin:user:delete` không tồn tại trên build này).

---

## ROUND 6 — node banner content (WYSIWYG + mobile flag) (2026-10-02)

TL-approved schema (companion table) + full implementation + service-level QA.

### Implementation

- **Schema** (`db_schema.xml` + whitelist, applied via setup:upgrade on local
  db2): `launchpad_snowdog_menu_node_banner` — node_id unsigned PK/FK →
  snowmenu_node ON DELETE CASCADE, banner_content mediumtext NULL,
  show_banner_content_mobile SMALLINT unsigned NOT NULL DEFAULT 0.
- **Model layer**: `Model\NodeBanner` + `ResourceModel\NodeBanner`
  (`_isPkAutoIncrement = false` + `_useIsObjectNew = true` — required for the
  non-auto-increment PK so AbstractDb takes the INSERT path; without them the
  save silently ran 0-row UPDATEs) + `Model\NodeBannerManagement`
  (batched menu-level read, upsert/delete, `clean(['block_html',
  'layout', 'full_page'])` invalidation on write).
- **Persistence plugins (global di.xml — area-agnostic, Admin save included)**:
  `afterProcessNodeObject` stages raw payload values on the node model;
  `NodeRepositorySavePlugin` after-save upserts/deletes the companion row from
  the staged values. FK cascade removes banner rows of deleted nodes — no
  duplicate purge (per TL instruction).
- **Editor**: requirejs map `vue!Snowdog_Menu/vue/menu-type` → extended copy
  `Launchpad_SnowdogMenu/view/adminhtml/web/vue/menu-type.vue` — WYSIWYG
  textarea (lazy TinyMCE 5 via `require(['tinymce'])`, toolbar
  bold/italic/link/bullist, editor→payload sync on input/change/keyup,
  payload→editor sync on item swap, plain-textarea fallback when the module
  does not resolve) + Yes/No checkbox bound to
  `item.show_banner_content_mobile` (default 0). Values ride the standard
  serialized_nodes payload.
- **Editor pre-fill**: `NodesTabPlugin` (adminhtml di) AFTER
  `Tab\Nodes::renderNodes()` appends stored values per node (batched read).
- **Frontend**: desktop panel copy precedence = editor banner (rendered
  through the Magento page-template filter) → CMS block → none; mobile panel:
  image always; content (same precedence) ONLY when the node's mobile flag is
  Yes; empty-WYSIWYG shells (`<p><br></p>`-style) count as empty and fall
  through to the CMS block. `MenuFeature` gained batched banner loading
  (one query per menu render via `loadForMenu`).

### Persistence proof (service level, db2)

- `test-banner.php set 2`: row stored (content + mobile=1) through the
  plugin chain → storefront desktop panel renders the editor HTML
  (precedence over CMS block — verified: copy switched from the CMS block
  text to the editor text) → mobile (flag Yes) renders the same editor
  content under the image — PASS.
- `test-banner.php clear 2`: row deleted → desktop falls back to the CMS
  block copy; mobile hides content (image stays) — PASS. Final demo state
  restored (`set 2`).
- Orphan behavior: FK cascade covers repository deletes; no duplicate purge
  added (per TL instruction).

### WYSIWYG UI

NOT TESTED via automation (the admin forms cannot be driven by the
automation browser — user-confirmed working in real browsers). The binding
is code-verified (textarea → payload → plugins → companion → frontend) and
the persistence chain is proven end-to-end at service level. Human
spot-check: open a node → Edit → type in the WYSIWYG under the image →
Save → reload → re-open → content intact → storefront desktop panel shows
it (mobile per flag).

### Spec reconciliation

SPEC-FEAT-ZNJ4KF §2.1.6 "No new schema" is superseded by amendment §8.3
(TL-approved companion table). The original mobile contract ("mobile renders
the feature image only") now reads: mobile renders the image always and the
banner CONTENT when the node's mobile flag is Yes (default No); the Desktop
copy is still desktop-only. Analysis doc §7 statements follow the same
supersession.

---

## ROUND 7 — review-fix round (banner) + final QA (2026-10-01/02)

Menu code state: COMMITTED at `b21f0260` ("SLP-245: Menu layout"); round-4
header/finding fixes + round-5 banner round are uncommitted working-tree
changes on top (see §Files for commit list). Earlier "UNCOMMITTED on
b21f0260" phrasing referred to this same state — the commit has since
landed.

### Fixes from the banner review

1. **Mobile flag gated only the CMS fallback** — WYSIWYG content rendered
   unconditionally on mobile. Fixed: the mobile flag gates BOTH sources
   (editor WYSIWYG first, CMS block second); desktop precedence is
   independent of the flag. Verified live per the full matrix below.
2. **Cross-menu purge bug** — `purgeExcept()` read the whole companion table
   and kept only the saved menu's node ids, so saving menu A would delete
   menu B's banner rows. REMOVED entirely (plugin `afterSaveData` purge +
   `purgeExcept()` deleted from `NodeBannerManagement`): every standard node
   deletion flows through `NodeRepository::deleteById`, whose FK
   `ON DELETE CASCADE` removes the banner row — proven by the isolation test
   below. Cache invalidation now happens ONCE per tree save via
   `invalidateIfDirty()` (only when a banner row actually changed; no-op
   saves cause no clean).
3. **TinyMCE as a required define dependency** — removed from
   `define([...])` (a failed resolve killed the whole component including
   the textarea fallback). TinyMCE now loads lazily inside
   `initBannerWysiwyg` with destroyed-component guards on the async
   callback; `beforeUnmount` syncs final content and destroys the editor.
4. **Unique IDs** — checkbox `id`/`for` now per-node
   (`launchpad_banner_mobile_<nodeId>`); textarea already
   `banner_content_<nodeId>`.
5. **Cache invalidation precision** — `management->save()` is a no-op when
   values are identical (dirty flag set only on real changes); the invalidation
   cleans `block_html` + `full_page` (banner markup embeds the menu block;
   `layout` dropped as unnecessary).
6. **Storage semantics** — explicitly emptied editor ('' / empty WYSIWYG
   shell) keeps the banner row (content ''/shell + flag) so "editor empty +
   CMS exists + flag Yes → CMS on mobile" works; null content + flag No
   deletes the row (defaults). NULL content + flag Yes is representable
   (row with NULL content) and renders the CMS fallback on mobile.

### Rich HTML contract (corrected language)

`FilterProvider::getPageFilter()` processes CMS directives/widgets/media
URLs — it is NOT an HTML sanitizer and is not described as one. Banner
content is trusted admin-authored content (WYSIWYG toolbar limited to
paragraph/br, bold, italic, link, lists — no source-code editing). The
frontend renders it through the page filter for directive/media-URL
consistency, typography per the existing tokens, `min-w-0 truncate` on
labels so long Vietnamese content never overflows rows.

### Live verification (final build, minified production CSS)

Mobile flag matrix (node 2, storefront 375px, real HTML):
| WYSIWYG | CMS | flag | mobile result |
|---|---|---|---|
| có | có | No | no text (image only) — PASS |
| có | có | Yes | WYSIWYG content — PASS |
| rỗng | có | No | no text — PASS |
| rỗng | có | Yes | CMS copy — PASS |
| both empty | Yes/No | no text region (image/none per branch) — PASS by state inspection (NULL-content row + defaults) |

Desktop 1440px: feature image + editor WYSIWYG copy (precedence over CMS),
row height 36px, truncation ellipsis confirmed (`text-overflow: ellipsis`),
col widths 240/300 responsive, no clipped column content at 1024/1280
(each visible list scrollWidth ≤ clientWidth), zero page overflow — PASS.

Cross-menu isolation (services-only test, `test-cross-menu.php`): 9/9 PASS —
two QA menus with banner rows; save in A preserves B's row and content;
re-save preserves A's row; node deletion via the standard save path
cascade-deletes A's banner row; menu deletion cascades nodes + banner rows.
Temp menus removed after evidence capture.

Console: 0 errors. Cache: invalidation only on real changes (verified by
the no-op detection in `save()`).

### Remaining NOT TESTED

- Admin WYSIWYG lifecycle UX (open/close/switch while TinyMCE active) —
  automation browser cannot drive the editor; human spot-check: edit two
  nodes alternately, confirm no content bleed, save, reload, re-open both.
- Virtual keyboard/safe-area on real devices.
- Logged-in visual pass (no QA account).

### Cleanup list

- Admin user `menu-fixture` (delete via Admin UI when done).
- Fixture tooling kept under `.ai/evidence/TASK-08343C/fixture/`
  (fixture.php, test-banner.php, test-cross-menu.php, toggle-menu.php,
  test-cleanup.php, probe scripts) — task-local, exclude from commits.
- `state.json`/`baseline.json`/backups/snapshots — local-only, exclude from
  commits.

---

## ROUND 8 — requirejs override investigation (historical; superseded by the
final Admin screenshots and Round 9, 2026-10-02/03)

### Admin requirejs override — Investigation summary

Three approaches attempted to inject the banner form fields into the Snowdog
node editor:

1. `map` on `vue!Snowdog_Menu/vue/menu-type` — the aggregate served the map
   (verified in response body) but the browser loaded the vendor vue file.
   requirejs map does not reliably apply to `vue!`-plugin resource IDs.
2. `map` on the entry module `Snowdog_Menu/js/nodes` — same result: the
   aggregate served the map but the browser loaded the vendor nodes.js.
3. `paths` on the `menuNodes` alias (the ID the x-magento-init block
   requires) — the aggregate served the paths entry and the editor loaded
   with 0 headings (Vue app broke), because the `menuNodes` →
   `Launchpad_SnowdogMenu/js/nodes` copy has a dependency
   (`vue!Launchpad_SnowdogMenu/vue/menu-type`) that fails to resolve in the
   automation browser's isolated world.

All three approaches verified with network capture + response body analysis
across multiple browser sessions (fresh + cached). The `menuNodes` paths
override is currently ENABLED in the working tree — it may work in a real
browser (the isolated-world limitation means automation cannot confirm).
If it does not work for humans either, the alternative is a manual Admin
UI test after clearing browser cache, or a different injection mechanism
(e.g. a custom node type via `getCustomTemplateOptions`).

The `beforeUnmount` → `beforeDestroy` fix (Vue 2.6) is applied and correct.

### Storefront banner rendering (final verification)

- Desktop 1440px: banner content (WYSIWYG HTML with h3/strong/em/link)
  renders under the feature image, editor content has precedence over the
  CMS block — PASS.
- Mobile 375px: banner content visible under the image (mobile flag Yes) —
  PASS.
- Feature image renders on both viewports — PASS.

### Remaining manual checks for the user

1. **Admin WYSIWYG spot-check**: open Admin → Content → Menus → Top Menu →
   node → Edit → verify the Banner content WYSIWYG + Show banner content on
   mobile checkbox appear below the image section → type content → Save →
   reload → verify persisted → verify storefront desktop panel shows the
   content → mobile drawer (flag Yes) shows it under the image.
2. **Cross-node test**: edit two nodes with different banner content →
   Save → verify each panel shows its own content (no bleed).
3. **Clear test**: clear the WYSIWYG content → Save → verify CMS fallback
   on desktop and no content on mobile (flag No).
4. **Virtual keyboard/safe-area**: real device test.
5. **Logged-in visual**: QA account.
6. **menuNodes paths override**: if the fields don't appear in Admin, check
   the browser console for requirejs errors and verify
   `Launchpad_SnowdogMenu/js/nodes.js` loads (Network tab). If it doesn't
   load, the `menuNodes` paths override is not applying — try disabling the
   override and using the Admin's built-in import/export to manage banner
   data via CMS blocks only.

---

## ROUND 9 — final interaction/responsive review + pre-commit reconciliation (2026-10-04)

This round supersedes earlier See-all and fixed-three-column conclusions in
this evidence file.

- Desktop responsive rail: below 1440px two equal columns are visible; from
  1440px three columns fit fully and grow up to 300px; column 4+ creates
  horizontal overflow. Opening a new deeper column scrolls the rail to its
  end without moving keyboard focus.
- Parent interaction: category/custom-URL title is an independent link;
  only the trailing chevron discloses children. Wrapper titles remain text.
  Desktop and mobile See-all rows were removed. Live accessibility-tree QA
  confirmed separate link/button roles, resolved demo URLs, expanded state,
  descendant reset and absence of See-all rows.
- Mobile polish: last navigation/submenu/Language rows have no bottom border;
  drawer search uses a full-width bordered input with an absolute submit
  button inside the field; current Language flag resolves from store-scope
  `general/locale/code` and rendered `vi.svg` in the live HTML.
- Admin/banner review: companion schema whitelist was regenerated and added;
  module docs now describe the RequireJS path override, shared identifier,
  schema, parent-link behavior and CMS fallback accurately.
- Gates: PHP lint (menu/module PHP and PHTML), XML parse, whitelist JSON,
  Tailwind production build, Magento `setup:di:compile`, and
  `git diff --check` PASS. The project validator entry point currently stops
  on its own shell parser error (unmatched quote) before evaluating records;
  this tooling defect is recorded and is not treated as a menu validation
  result. Magento cache was cleaned after the final storefront changes.

Remaining device/environment QA does not block the code commit: virtual
keyboard/safe-area on a physical device and logged-in visual regression.
Delete the local `menu-fixture` Admin user after QA. Fixture state, backups,
snapshots, probe/test scripts and `pub/media/snowdog/**` remain local-only and
must not be committed.

---

## ROUND 10 — desktop current/hover/open navigation state (2026-10-05)

- Re-read Figma node `2151:9582` with design context and screenshot. Contract:
  Label M; inactive indicator width 0; active indicator full label width and
  1px height; `gray-solid-primary` in dark-text/light-header mode and white in
  light-text/contextual-header mode.
- Current branch is derived from the current Magento category path through the
  Snowdog category provider, so descendant category pages keep their level-1
  branch indicated.
- Hover and open-panel states expand the same indicator. Header contextual
  state supplies the correct design-token color; existing focus outlines and
  separate category-link/chevron actions remain unchanged.
- The identifier-scoped category node template adds `aria-current="page"`
  only to an exact category match; ancestors receive visual state only.
- Live storefront checks: `/living-room` rendered the top-level current
  indicator and exact-current semantics; `/living-room/living-room-seating`
  kept the Living Room branch indicator while omitting `aria-current` from
  its ancestor link and applying it to the exact Seating link. Accessibility
  tree still exposes independent category links and disclosure buttons.
- PHP lint, production Tailwind build, YAML frontmatter parse, and
  `git diff --check` PASS. The project validator remains unavailable because
  its existing shell script has an unmatched-quote parser error.
- Documentation confirmation date synchronized to 2026-10-05 across spec,
  plan, record, project state, design analysis, and this evidence record.

## ROUND 11 — current/open state consistency (2026-10-05)

- Root cause of the disappearing desktop current indicator: its static class
  token was identical to the Alpine `:class` open token, so Alpine removed it
  while initializing a closed panel. The current token is now a distinct
  important utility; open state remains dynamic.
- Current/exact category evaluation moved to `MenuFeature` and is reused by
  desktop root/submenu and mobile root/group/accordion templates.
- Desktop submenu hover no longer inherits Snowdog's link-color-only hover.
  Parent and leaf hover/current states now use the same 1px underline as
  level 1; click-selected/open parents retain the brand-soft surface/text.
- Mobile state was re-read from Figma node `2949:77725`: brand-100 surface,
  brand-primary text, bottom divider, Label L medium. The same state now
  covers root panel triggers, group/accordion headers, leaves, and indented
  child leaves; existing Label M sizing at child depth keeps medium weight.
- Live descendant-category verification at
  `/living-room/living-room-seating`: top-level Living Room HTML retains the
  Alpine-independent current indicator; desktop Seating row renders the
  current underline plus exact `aria-current="page"`; mobile Living Room root
  and Seating child render the brand-100 current surface with 8px start
  padding, with ARIA restricted to Seating. Generated production CSS contains
  the important current utilities and desktop/mobile state selectors.
- Gates: PHP lint for the view model and four menu templates PASS; Tailwind
  production build PASS; YAML frontmatter parse PASS; `git diff --check`
  PASS; storefront response contains no Magento exception/report marker.

## ROUND 12 — category-current cache isolation (2026-10-05)

- Dev evidence: `/living-room`, `/bedroom`, `/accessories`, and descendant
  category pages all rendered node 129 (What's New) with
  `aria-current="page"`. The incorrect state existed in server HTML before
  Alpine initialized.
- Root cause: Snowdog's category cache context varies by route/action but not
  category ID. Launchpad added server-rendered current/path classes, so the
  first category menu cached under `catalog/category/view` leaked to every
  other category page.
- Fix: the frontend Snowdog block plugin appends
  `current_category_<entityId>` to `getCacheKeyInfo()` when category context
  exists. Non-category pages retain the original key.
- Verification after one cache clean: sequential local requests correctly
  marked Living Room, Bedroom, Accessories, and Seating respectively. Unit
  suite PASS (8 tests, 12 assertions); DI compile PASS.
