# TASK-DWW34M — Implementation Plan: Launchpad Core Header

| Field | Value |
|---|---|
| Specification | [SPEC-TASK-DWW34M](../specs/SPEC-TASK-DWW34M-launchpad-core-header.md) |
| External reference | `SLP-246` |
| Mode | B |
| Status | Completed |
| Date | 2026-09-25 |

## Ordered implementation

1. Audit Header master components, child nodes, assets and current Hyvä/Snowdog
   layout contracts; record any unresolved contextual state before code.
2. Scaffold `Secomm_ThemeHelper` with registration/module metadata, Admin ACL,
   Store View scoped sticky config, default enabled value, typed provider,
   README/CHANGELOG and focused unit test where practical.
3. Create theme-local layout composition for language, wishlist and Header
   arguments without replacing Magento runtime blocks.
4. Export only the Header logo/icon subset, normalize SVGs under
   `Hyva_Theme/web/svg/{style}/`, and verify vector geometry/currentColor/security.
5. Implement the Header PHTML shell and logo/action templates using Tailwind
   utilities, Alpine state and existing Hyvä private-content/search/cart behavior.
6. Implement contextual and config-gated sticky behavior, including full-width
   background/shadow, reduced motion and focus management.
7. Build CSS; run PHP/XML/static checks and Magento module/config smoke checks.
8. Deploy/refresh local static assets only if the browser serves materialized
   copies, then compare 375/640/1440 states against Figma and record evidence.

## Verification matrix

- Viewports: 320, 375, 640, 768, 1024, 1280, 1440 and 1920px.
- Context: homepage/non-home; sticky enabled/disabled; top/scrolled.
- Data: guest/customer; wishlist enabled/disabled; cart empty/non-empty;
  one/multiple languages; vi_VN/en_US.
- Interaction: mouse, keyboard, Escape, outside click, focus-visible and reduced
  motion.
- Assets: Figma glyph match, non-empty local SVG, viewBox, callsite and rendered
  geometry for every Header asset.

## Scope boundary

Header supplies navigation placement and mobile trigger only. Snowdog hierarchy,
mega-menu, drawer and category-content QA execute under the separate Menu plan.
