# Launchpad_SnowdogMenu — Changelog

## 1.1.0 (unreleased)
- TASK-08343C (FEAT-ZNJ4KF, SLP-245): node banner content — per-node WYSIWYG
  banner + `show_banner_content_mobile` flag (default No). Companion table
  `launchpad_snowdog_menu_node_banner` (db_schema; `setup:upgrade` required).
  Persistence via `SaveRequestProcessor`/`NodeRepository` plugins
  (global di) + `NodesTab` editor pre-fill plugin (admin di); cache
  invalidation `block_html` + `full_page` once per tree save (only on real
  changes). RequireJS `menuNodes` path override of the node form Vue component adds the
  banner fields (WYSIWYG TinyMCE 5 lazy-loaded, plain-textarea fallback).
  Frontend: desktop banner content precedence over the
  `launchpad-menu-feature-<nodeId>` CMS block; mobile drawer shows the
  content under the feature image only when the node flag is Yes.
- TASK-08343C final menu behavior: responsive desktop navigation rail (two
  visible columns below 1440px, three from 1440px, auto-scroll for column
  4+); parent titles navigate while separate chevrons disclose children;
  mobile drawer divider/search/Language-flag alignment with the final design.
- TASK-08343C navigation-state follow-up (confirmed 2026-10-05): desktop
  level-1 inactive/current/hover/open indicators follow Figma `2151:9582`;
  current descendant pages retain their branch indicator and exact category
  links expose `aria-current="page"`.
- TASK-08343C state consistency: desktop nested hover/current rows use the
  level-1 underline while click-selected/open parents keep brand-soft; mobile
  current/open rows at every rendered level match Figma `2949:77725`, with
  8px start padding on current rows. Current-path detection is shared by both
  renderers and remains independent from Alpine hydration.

## 1.0.0
- TASK-SXW5RB (SLP-129): storefront navigation toggle `snowdog_navigation/general/enabled` (default No = native Hyvä navigation) — ViewModel `Config`, frontend plugin `Launchpad\SnowdogMenu\Plugin\Snowdog\Menu\Block\Menu` (gates `Snowdog\Menu\Block\Menu::toHtml`), theme layout restore + template guards live in `Secomm/launchpad`. `Snowdog_Menu` can stay enabled permanently.
