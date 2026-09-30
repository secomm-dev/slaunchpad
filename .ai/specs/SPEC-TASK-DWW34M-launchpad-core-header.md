---
id: SPEC-TASK-DWW34M
type: specification
title: Secomm Launchpad Core Hyva Header
feature_id: NONE
specification_level: FULL
status: VALID
owner: TASK-DWW34M
external_ref: SLP-246
created: 2026-09-24
updated: 2026-09-28
---

# SPEC-TASK-DWW34M — Secomm Launchpad Core Hyvä Header

Specification ID: SPEC-TASK-DWW34M
Feature ID: NONE
Specification Level: FULL

## 1. Goal

Implement the reusable Launchpad Core Header from the approved Figma master
components using Magento 2.4.8-p5, Hyvä 3.x, Alpine.js and Tailwind CSS v4.
The Header must preserve Magento runtime data and Hyvä behavior while providing
stable extension slots for the separate Snowdog Menu implementation.

## 2. Design sources

- Desktop Header component set: Figma `LAUNCHPAD-CORE`, node `2151:9517`.
- Mobile/Tablet Header component set: node `2115:5849`.
- Custom icon library: node `5:28677`; only Header-consumed glyphs are exported.
- Global foundation: `SPEC-TASK-6V8H2P` and its semantic tokens/utilities.

The master components are canonical for shell geometry and visible actions. Page
frames may be requested only when a contextual state cannot be resolved from
the component sets and approved behavior below.

## 3. Expected behavior

1. Header is a 64px full-width semantic shell. Desktop uses 40px horizontal
   padding; Mobile uses 8px; Tablet uses 32px. Desktop uses equal flexible
   `minmax(0, 1fr)` navigation/utility regions around a fixed 120×18 logo slot
   with 24px column gaps; the logo remains centered independently of either
   side's content width. The navigation region owns non-wrapping horizontal
   overflow and hides its scrollbar without disabling scrolling.
2. Below the desktop breakpoint the shell renders menu + logo on the left and
   Search + Account + Cart on the right. Wishlist remains a Desktop action,
   matching the supplied responsive master component.
3. Homepage starts with the contextual on-hero/light-ink treatment approved in
   the analysis; non-home pages use the solid/light treatment. When sticky is
   active after scroll, Header becomes a full-width solid surface with shadow.
4. Sticky behavior is enabled by default and controlled at Store View scope by
   `secomm_theme/header/sticky_enabled` under Stores → Configuration → Secomm →
   Theme → Header. Disabling it removes sticky/scroll treatment without removing
   Header functionality.
5. Search uses the existing Hyvä search form/suggestions contract; Account uses
   Magento customer session links; Wishlist follows Magento enablement; Cart
   preserves private-content count, cart link/drawer behavior and accessible
   labels; language switcher renders only when more than one language is active.
6. Existing cart drawer and authentication popup remain functional. Header
   actions have keyboard-visible focus, accurate expanded state and Escape /
   outside-click behavior where applicable.
7. Header uses exact, locally committed Figma SVG assets through Hyvä
   `SvgIcons::renderHtml()`. No temporary Figma URLs, Lucide substitutions or
   icon fonts remain for Header-consumed actions.
8. Header provides Desktop navigation and Mobile menu-trigger slots. Snowdog
   hierarchy, mega-menu composition and mobile drawer internals remain owned by
   the separate Menu workstream.

## 4. Constraints and rules

- Do not modify Hyvä, Snowdog or Mageplaza vendor/source modules in place.
- Use PHTML + Alpine.js + Tailwind CSS v4 CSS-first conventions; no jQuery,
  RequireJS, Knockout or `tailwind.config.js`.
- Component layout belongs in PHTML utility classes. Only reusable/global hooks
  and state selectors that utilities cannot express cleanly belong in source CSS.
- Reuse the approved Global Style semantic tokens instead of raw Figma values
  where an exact semantic token exists.
- `Secomm_ThemeHelper` owns Magento configuration and typed providers only; it
  must not own Header markup or CSS.
- New storefront strings must be translatable and present in both theme/module
  locale dictionaries when explicit dictionaries are required.
- Preserve staged Global Style changes; Header changes are a separate workstream.

## 5. Out of scope

- Snowdog data authoring, Desktop mega-menu panel and Mobile/Tablet menu drawer.
- Footer, page content and unrelated Global Style changes.
- New search engine behavior, custom account business logic or cart redesign.
- Dark-mode runtime. The Header treatments are contextual visual variants, not a
  site-wide dark-mode system.

## 6. Acceptance criteria

- AC-001: Desktop Header matches node `2151:9517` at 1440px for shell geometry,
  logo, action order, spacing and contextual color treatments.
- AC-002: Mobile 375px and Tablet 640px match node `2115:5849`; no overflow at
  320px and intermediate widths.
- AC-003: Search, Account, Wishlist and Cart use Magento/Hyvä runtime contracts;
  guest/customer, wishlist enabled/disabled and empty/non-empty cart states work.
- AC-004: Language switcher is hidden for one active language and functional for
  multiple active languages.
- AC-005: Sticky enabled/disabled, homepage/non-home and pre/post-scroll states
  behave as specified; sticky background/shadow spans the viewport width.
- AC-006: Header custom logo/icons match Figma glyph, viewBox and effective
  24×24 (logo 120×18) geometry, use `currentColor` where monochrome, and have
  correct accessible ownership.
- AC-007: Keyboard navigation, focus-visible, Escape/outside-close, reduced
  motion and screen-reader names satisfy the WCAG 2.2 AA contract.
- AC-008: `npm run build`, PHP syntax, XML validation, module compile/config
  smoke checks and `git diff --check` pass; evidence records verified versus
  deferred Menu behavior explicitly.
- AC-009: Additional Desktop level-1 items remain reachable through a
  scrollbar-hidden horizontal menu viewport; the page itself does not gain
  horizontal overflow and the logo stays centered.

## 7. Documented design gaps

- Tablet light variant is not explicitly shown; it follows the same contextual
  treatment contract as Mobile/Desktop.
- Account is retained next to Wishlist on Desktop per approved product decision,
  even where a source frame omitted it.
- Menu panel/drawer visuals are validated in the Menu workstream, not counted as
  Header acceptance failures.
