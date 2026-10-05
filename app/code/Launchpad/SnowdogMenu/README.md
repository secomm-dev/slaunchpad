# Launchpad_SnowdogMenu

## Purpose

Storefront navigation for the header — **Snowdog Menu** with configurable
desktop/mobile identifiers and a shared-by-default hierarchy, the Launchpad
node-editor banner extension (`SLP-245` / `TASK-08343C`), plus the legacy
storefront navigation toggle (`SLP-129` / `TASK-SXW5RB`).

## Header menu identifiers

- Admin path: **Stores > Configuration > Secomm > Theme > Menu**.
- **Desktop Menu Identifier** defaults to `hyva-topmenu-desktop`.
- **Mobile Menu Identifier** is optional. Empty means mobile uses the effective
  Desktop Menu Identifier; set it only when mobile needs a separate Snowdog
  hierarchy.
- Identifier selection is store-scoped. A frontend TemplateResolver plugin
  keeps the Launchpad renderer templates active for either configured menu,
  including category current-state markup.

## Node banner content (TASK-08343C / FEAT-ZNJ4KF)

- Admin: Content > Menus > Top Menu > node > Edit — below the image section:
  - **Banner content** (WYSIWYG: paragraph/br, bold/italic/link) — optional;
  - **Show banner content on mobile** — Yes/No, default **No**; gates the
    banner CONTENT on mobile (the image always renders), for both the editor
    content and the CMS fallback.
- Desktop mega panel: banner content renders under the feature image with
  precedence over the CMS block `launchpad-menu-feature-<nodeId>` (the
  CMS block is the fallback when the editor is empty; a node with neither
  shows no copy region).
- Mobile drawer: group panel renders the feature image always and the banner
  content only when the node flag is Yes.
- Parent navigation: the category name links to its Snowdog-resolved
  destination; only the trailing chevron opens child columns/panels. Wrapper
  nodes without a destination render text plus the disclosure button. No
  duplicate "See all" rows are rendered.
- Desktop navigation state: level-1 labels use the Figma navigation-item
  indicator for hover, open panel, and current category branch. Exact current
  category links emit `aria-current="page"`; ancestors are visual-only. The
  indicator uses the gray-solid token on a light header and white on a
  contextual header.
- Nested state: desktop submenu rows use the level-1 underline for hover and
  the current category path; click-selected/open parents retain the selected
  brand-soft surface. Mobile root,
  group, and accordion rows use the Back-row brand-100 surface and brand text
  for current/open states while retaining medium label weight and dividers;
  current rows add 8px start padding.
- Cache context: Snowdog block cache keys include the current category ID
  because current/ancestor state is rendered server-side.
- Storage: companion table `launchpad_snowdog_menu_node_banner`
  (node_id PK/FK -> snowmenu_node ON DELETE CASCADE; banner_content NULL;
  show_banner_content_mobile NOT NULL DEFAULT 0) — requires
  `bin/magento setup:upgrade` after deploying.
- Persistence/plugins: banner values ride the standard serialized_nodes
  payload; `Plugin\SaveRequestProcessorPlugin` stages them per node and
  `Plugin\NodeRepositorySavePlugin` persists them after each node save with
  `block_html`/`full_page` invalidation. Legacy nodes without a banner row
  behave exactly as before (flag default No).
- Banner HTML is trusted admin-authored content rendered through the Magento
  page-template filter; the WYSIWYG toolbar is limited to
  paragraph/br, bold, italic, link, lists (no source editing).

## Legacy: navigation toggle (SLP-129)

Selects between the **native Hyvä category navigation** and **Snowdog Menu**
navigation without disabling the `Snowdog_Menu` module — the Snowdog admin
menu builder stays available either way.

## Features

- **Stores > Configuration > Hyva Theme > Snowdog Navigation > General > Enable Snowdog Menu Navigation** (`snowdog_navigation/general/enabled`, default **No**, `canRestore`; tab `hyva_themes` khai báo bởi `Hyva_ThemeModule`).
- Scope: default / website / store view.
- **No** (default): the native Hyvä top menu renders (`topmenu_mobile` + `topmenu_desktop`, restored via theme layout `remove="false"` in `Secomm/launchpad`); every `Snowdog\Menu\Block\Menu` output is gated empty by the frontend plugin; the Snowdog Alpine collapse plugin is skipped (theme template guard).
- **Yes**: Snowdog renderers consume the configured identifiers. By default,
  both use `hyva-topmenu-desktop`; each effective identifier must exist for
  the store or that renderer is empty.

## How it works

1. `ViewModel\Config` reads the navigation toggle and desktop/mobile menu
   identifiers at store scope, including the mobile-to-desktop fallback.
2. `Plugin\Snowdog\Menu\Block\Menu` (frontend-scope `afterToHtml` on `Snowdog\Menu\Block\Menu`) returns `''` while disabled — one plugin covers the desktop, mobile and footer menu blocks. When `Snowdog_Menu` is disabled the target class does not exist and the plugin is never invoked.
3. Theme `Magento_Theme::html/header/topmenu.phtml` (override in `Secomm/launchpad`) branches on the view model: native children vs the Snowdog mobile child; the Snowdog desktop block renders separately in `header.container`.

## Notes

- After changing the value in Admin, flush the cache (`bin/magento cache:flush`) — the top menu is cached in `block_html` (ttl 3600).
- Banner content uses the companion declarative-schema table documented
  above; the legacy navigation toggle itself adds no schema or cron.
- Third-party `Snowdog_Menu` is never modified — extension is via plugins,
  admin overrides, and theme templates.
