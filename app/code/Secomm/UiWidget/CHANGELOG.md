# Changelog

## Unreleased

- Fixed all schema-driven `media-image` controls to persist storefront media URLs instead of temporary Admin directive URLs.
- Resolved portable widget media paths to the current store's absolute media base URL during storefront rendering.
- Added schema-driven Admin validation for required fields, conditional required fields and minimum repeater item counts before widget insertion.

## 1.4.0 - 2026-08-28

- Documented the component onboarding/upstream update workflow and verified the product-theme override contract with a non-shipped local fixture.
- Added `usp_c` (`Compact Benefits`) with bounded icon/text items and Hyvä UI `usp/C-compact` 2.8.0 responsive visual parity.
- Added `usp_b` (`Benefit Cards`) with bounded icon/media cards, compound optional CTAs and Hyvä UI `usp/B-cards` 2.8.0 responsive visual parity.
- Added `usp_a` (`Icon Benefits`) with bounded icon/media benefits and Hyvä UI `usp/A-icons` 2.8.0 responsive visual parity.
- Added `testimonial_b` (`Testimonial Card`) with semantic quote markup, responsive Gallery avatar and Hyvä UI `testimonial/B-card` 2.8.0 visual parity.
- Added `testimonial_a` (`Customer Testimonial`) with semantic quote markup, optional Gallery avatar/rating and Hyvä UI `testimonial/A-simple` 2.8.0 visual parity.
- Added `slider_b` (`Logo Marquee`) with ordered Gallery logos, allowlisted animation controls and Hyvä UI `slider/B-marquee` 2.8.0 visual behaviour.
- Added `slider_a` (`Content Slider`) with ordered Gallery-backed slides, `x-snap-slider` controls and Hyvä UI `slider/A-basic` 2.8.0 visual behaviour.
- Added `shortcuts_a` (`Shortcut Links`) with ordered icon/image links and Hyvä UI `shortcuts/A-simple` 2.8.0 responsive visual parity.
- Added `product_highlights_c` (`Product Highlights`) with ordered manual highlights, Gallery-backed media, trusted CMS content and Hyvä UI `product-data/C-highlights` 2.8.0 visual parity.
- Added `modal_a` (`Information Modal`) with trusted CMS content, instance-safe native dialog behaviour and Hyvä UI `modal/A-simple` 2.8.0 visual parity.
- Added `generic_content_b` (`Visual Content`) with Gallery media, trusted CMS content, optional quote/CTA and Hyvä UI `generic-content/B-visual` 2.8.0 visual parity.
- Added `embed_a` (`Video Embed`) with provider-matching YouTube/Vimeo URL validation, poster lazy loading, privacy controls and Hyvä UI `embed/A-basic` 2.8.0 visual behaviour.
- Added `categories_b` (`Pattern Category Grid`) with ordered allowlisted pattern/color items and Hyvä UI `categories/B-grid-patterns` 2.8.0 responsive visual parity.
- Added `categories_a` (`Image Category Grid`) with ordered Gallery-backed items and Hyvä UI `categories/A-grid-images` 2.8.0 responsive visual parity.
- Added `card_b` (`Media Card`) with Hyvä UI `card/B-media` 2.8.0 default visual parity, Gallery-backed media and an opt-in right-media position.
- Added `card_a` (`Feature Card`) with Hyvä UI `card/A-default` 2.8.0 visual parity, responsive media and trusted CMS content.
- Added reusable `media-image` Admin controls with Magento Media Gallery selection, thumbnail preview and removal for Banner A, Banner B and Card A.
- Fixed Magento Media Gallery URL construction for `media-image` and hid the persisted URL input from the Admin authoring UI.

## 1.3.1 - 2026-08-26

- Restored Hyvä UI 2.8.0 layout and visual behaviour for Banner A, Banner B, Banner C, Accordion A and Generic Content A.
- Added module-owned Hyvä Tailwind source registration and the upstream Accordion `details-animate` utility.
- Removed layout extensions that changed the upstream base appearance while retaining existing schema-version-1 payload compatibility.
- Added upstream-aligned eyebrow and lead content slots to Generic Content A.

## 1.3.0 - 2026-08-25

- Added the `banner_c` text-banner component with trusted CMS content and allowlisted layout, palette and CTA options.
- Added the `banner_b` split-banner component with trusted CMS content, responsive media and allowlisted presentation options.
- Added the `accordion_a` B1 component with bounded ordered panels, trusted rich text and native accessible details behavior.
- Improved Accordion panel authoring with full-width controls and a larger, easier-to-resize rich-text editor.
- Corrected the Secomm UI options and repeater-item offsets inside Magento's CMS Insert Widget popup without changing global Admin field styles.
- Added the `generic_content_a` B1 component with allowlisted layout variants.
- Added explicit trusted rich-text authoring through Magento WYSIWYG and storefront rendering through the CMS block filter.
- Replaced variant-letter Admin labels with semantic component names while preserving stable IDs.
- Fixed dynamic options initialization inside Magento's AJAX Insert Widget dialog, including multiple forms with duplicate Magento-generated field IDs.

## 1.2.0 - 2026-08-24

- Added the production `banner_a` component adapted from Hyvä UI 2.8.0 `banner/A-default`.
- Added responsive media, optional CTA, safe palette/appearance variants and accessibility semantics.
- Verified the fashion-theme presentation override path and added Hyvä Tailwind source registration; the temporary override fixture is not shipped.

## 1.1.0 - 2026-08-24

- Added registry-driven Admin component options, including media and ordered collection controls.
- Added deterministic versioned parameter codec, schema normalization and persistence validation.
- Added fail-closed storefront payload validation.

## 1.0.0 - 2026-08-24

- Added the `Secomm_UiWidget` Magento module foundation.
- Declared the single `Secomm UI` widget type.
- Added component definition, registry and safe template resolver contracts.
- Added Admin component options source and bilingual translations.
