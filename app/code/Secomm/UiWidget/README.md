# Secomm_UiWidget

Shared Magento widget foundation for approved Secomm UI components on Hyvä storefronts.

## Current status

This release contains the reusable foundation and dynamic parameter layer:

- one Magento widget type labelled `Secomm UI`;
- explicit component registry and definition contracts;
- source provenance and schema version metadata;
- safe registered-template resolution;
- an Admin component source model;
- registry-driven scalar, media and ordered collection fields;
- deterministic versioned payload validation for directives, instances and storefront rendering.

The production registry contains all 21 approved Batch 1 components: `accordion_a`, `banner_a`, `banner_b`, `banner_c`, `card_a`, `card_b`, `categories_a`, `categories_b`, `embed_a`, `generic_content_a`, `generic_content_b`, `modal_a`, `product_highlights_c`, `shortcuts_a`, `slider_a`, `slider_b`, `testimonial_a`, `testimonial_b`, `usp_a`, `usp_b` and `usp_c`.
Generic Content A, Accordion A, Banner B and Banner C consume the explicit trusted-rich-text contract.

## Architecture rules

- Runtime templates are owned by this module or overridden by a Hyvä product theme.
- Never render or scan `vendor/hyva-themes/hyva-ui` at runtime.
- Component IDs and schema versions are persisted compatibility contracts.
- CMS parameters never select arbitrary PHP classes or template paths.
- Product/category selection is manual-only.
- Luma and non-Hyvä themes are not supported.
- Only fields declared as `trusted-rich-text` use Magento WYSIWYG and the CMS block filter. Other output remains context-escaped.
- Image fields use the `media-image` Admin control: Magento Media Gallery selection, immediate thumbnail preview and removal while preserving the persisted string payload contract.
- Imported templates preserve the pinned Hyvä UI layout and visual behaviour; local differences are limited to data plumbing, validation/escaping, instance-safe semantics and removal of demo fallbacks.
- The module registers its templates and Tailwind utilities through `hyva_config_generate_before`, so every consuming Hyvä theme scans the shipped component classes.

See `SPEC-FEAT-J06WXZ` and `DEC-FEATJ06WXZ-001` for the canonical contract.
Trusted rich text is governed by `DEC-FEATJ06WXZ-002`.

## Registering a component

Components are added to the registry through dependency injection with an object implementing `ComponentDefinitionInterface`. Each definition must provide a stable ID, label, registered Magento template alias, schema version, field metadata, source component/version and sort order.

Component registration is intentionally explicit. Updating the Hyvä UI Composer package does not add or change runtime components.

### Banner A provenance

- Internal ID: `banner_a`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `banner/A-default`.
- Local modifications: removed demo title/Unsplash fallback, replaced arbitrary colors/classes with allowlisted values and added registry payload/media fields plus instance-safe semantics. Upstream layout, overlay, card, gradient and CTA behaviour are preserved.
- Default template: `Secomm_UiWidget::components/banner/a.phtml`.

### Banner B provenance

- Internal ID: `banner_b`; Admin label: `Split Banner`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `banner/B-split`.
- Local modifications: removed demo content and remote image fallback, replaced arbitrary colors/classes with allowlisted values and added responsive media/trusted CMS fields. Upstream grid, reverse order, spacing and CTA behaviour are preserved.
- Default template: `Secomm_UiWidget::components/banner/b.phtml`.

### Banner C provenance

- Internal ID: `banner_c`; Admin label: `Text Banner`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `banner/C-text`.
- Local modifications: removed demo content and arbitrary CSS values, added trusted CMS content, semantic heading and allowlisted alignment/palette/CTA options. Upstream full-width content, spacing and CTA behaviour are preserved.
- Default template: `Secomm_UiWidget::components/banner/c.phtml`.

### Accordion A provenance

- Internal ID: `accordion_a`; Admin label: `Accordion`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `accordion/A-basic`.
- Local modifications: ordered Admin panels, trusted CMS content and instance-safe native single-open groups. Upstream wrapper, divider, summary, animation and spacing classes are preserved.

### Generic Content A provenance

- Internal ID: `generic_content_a`; Admin label: `Rich Text Content`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `generic-content/A-text`.
- Local modifications: dynamic eyebrow, heading, lead and trusted CMS body slots. Upstream article typography, header spacing and responsive two-column body are preserved.

### Card A provenance

- Internal ID: `card_a`; Admin label: `Feature Card`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `card/A-default`.
- Local modifications: replaced demo media/content with required responsive media, trusted CMS content and a validated CTA contract. Upstream card wrapper, picture breakpoint, prose spacing and interactive overlay link behaviour are preserved.
- Default template: `Secomm_UiWidget::components/card/a.phtml`.

### Card B provenance

- Internal ID: `card_b`; Admin label: `Media Card`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `card/B-media`.
- Local modifications: replaced demo media/content with a required Gallery-backed image, trusted CMS content and a validated CTA contract. The default left-media layout preserves the upstream card wrapper, 150px media, prose spacing and interactive overlay link behaviour; right media is an explicit opt-in presentation extension.
- Default template: `Secomm_UiWidget::components/card/b.phtml`.

### Categories A provenance

- Internal ID: `categories_a`; Admin label: `Image Category Grid`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `categories/A-grid-images`.
- Local modifications: replaced demo category links and Unsplash images with bounded ordered Admin items using Gallery-backed media and validated URLs. The upstream mobile snap slider, desktop two-column grid, first-item span, responsive heights, image hover and label gradient are preserved by default; disabling the mobile slider is opt-in.
- Default template: `Secomm_UiWidget::components/categories/a.phtml`.

### Categories B provenance

- Internal ID: `categories_b`; Admin label: `Pattern Category Grid`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `categories/B-grid-patterns`.
- Local modifications: replaced demo category links with bounded ordered Admin items and allowlisted pattern/background selectors. The upstream SVG patterns, mobile snap slider, desktop grid/first-item height, hover transform and typography are preserved by default; disabling the mobile slider is opt-in.
- Default template: `Secomm_UiWidget::components/categories/b.phtml`.

### Embed A provenance

- Internal ID: `embed_a`; Admin label: `Video Embed`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `embed/A-basic`.
- Local modifications: combined the upstream YouTube/Vimeo templates behind an allowlisted provider field, added provider-matching URL validation, Gallery-backed posters, fixed aspect-ratio choices and privacy mode. Upstream poster/play-button lazy loading, player colors, iframe permissions, rounding and responsive sizing are preserved without demo fallback data.
- Privacy mode uses YouTube's no-cookie player or Vimeo `dnt=1`; required frame/poster hosts are declared in the module CSP whitelist.
- Default template: `Secomm_UiWidget::components/embed/a.phtml`.

### Generic Content B provenance

- Internal ID: `generic_content_b`; Admin label: `Visual Content`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `generic-content/B-visual`.
- Local modifications: replaced demo media/copy with Gallery-backed media, explicit eyebrow/heading/lead/body/quote slots and validated optional CTA fields. The default left-media presentation preserves the upstream mobile title overlay, desktop heading placement, prose styling and quote treatment; right media and CTA are opt-in extensions.
- Default template: `Secomm_UiWidget::components/generic-content/b.phtml`.

### Modal A provenance

- Internal ID: `modal_a`; Admin label: `Information Modal`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `modal/A-simple` native-dialog example.
- Local modifications: replaced demo copy with required trigger/title/trusted-content/action fields and generated instance-safe dialog IDs and Alpine data names. The upstream trigger, native `<dialog>`, warning icon, responsive content layout, action footer, transitions and `x-htmldialog.noscroll` behaviour are preserved.
- Default template: `Secomm_UiWidget::components/modal/a.phtml`.

### Product Highlights C provenance

- Internal ID: `product_highlights_c`; Admin label: `Product Highlights`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `product-data/C-highlights`.
- Local modifications: converted the CMS demo into bounded ordered Admin rows with Gallery-backed media, trusted CMS content and an allowlisted image position. No product context is read. Each row preserves the upstream mobile stack, desktop 192px square media, spacing and optional reverse layout.
- Default template: `Secomm_UiWidget::components/product-highlights/c.phtml`.

### Shortcuts A provenance

- Internal ID: `shortcuts_a`; Admin label: `Shortcut Links`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `shortcuts/A-simple`.
- Local modifications: replaced fixed CMS links with bounded ordered Admin items, validated URLs, an allowlisted Lucide icon selector and optional Gallery image replacement. Upstream blue wrapper, three-column grid, responsive icon sizing/alignment, label typography and desktop-only descriptions are preserved.
- Default template: `Secomm_UiWidget::components/shortcuts/a.phtml`.

### Slider A provenance

- Internal ID: `slider_a`; Admin label: `Content Slider`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `slider/A-basic`.
- Local modifications: replaced demo slides with bounded ordered Gallery-backed slides, accessible labels and compound optional CTA fields. Upstream CSS snap track, `x-snap-slider` pager, arrow positions, responsive media heights and caption overlay typography are preserved; CTA is opt-in and absent from the base appearance.
- Default template: `Secomm_UiWidget::components/slider/a.phtml`.

### Slider B provenance

- Internal ID: `slider_b`; Admin label: `Logo Marquee`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `slider/B-marquee`.
- Local modifications: replaced bundled demo SVGs with bounded ordered Gallery-backed logo items and validated optional links. Allowlisted speed/direction and hover/focus pause controls extend the fixed upstream animation while preserving its default 40-second leftward motion. The duplicated ARIA-hidden row, responsive spacing, borders, masks and reduced-motion pause remain intact.
- Default template: `Secomm_UiWidget::components/slider/b.phtml`.

### Testimonial A provenance

- Internal ID: `testimonial_a`; Admin label: `Customer Testimonial`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `testimonial/A-simple`.
- Local modifications: replaced demo content with escaped quote, author, optional role/company and Gallery-backed avatar fields. An optional rating is an opt-in extension and is absent by default. Semantic `figure`, `blockquote` and `figcaption` elements preserve the upstream layout, typography, quote mark, circular portrait and author treatment.
- Default template: `Secomm_UiWidget::components/testimonial/a.phtml`.

### Testimonial B provenance

- Internal ID: `testimonial_b`; Admin label: `Testimonial Card`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `testimonial/B-card`.
- Local modifications: replaced demo content with escaped quote, author, optional role/company and Gallery-backed avatar fields. Optional rating remains an opt-in extension. Semantic `figure`, `blockquote` and `figcaption` elements retain the upstream slate card, overlapping mobile portrait, desktop media layout, decorative quote and author treatment.
- Default template: `Secomm_UiWidget::components/testimonial/b.phtml`.

### USP A provenance

- Internal ID: `usp_a`; Admin label: `Icon Benefits`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `usp/A-icons`.
- Local modifications: replaced fixed CMS content with a bounded ordered benefit collection, an explicit Lucide icon allowlist, optional Gallery image replacement and validated optional links. The upstream centered eyebrow/heading, two-column responsive grid, circular icon treatment, spacing and typography are preserved.
- Default template: `Secomm_UiWidget::components/usp/a.phtml`.

### USP B provenance

- Internal ID: `usp_b`; Admin label: `Benefit Cards`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `usp/B-cards`.
- Local modifications: replaced fixed CMS cards with a bounded ordered collection, explicit Lucide icon allowlist, optional Gallery image replacement and compound opt-in CTA fields. The upstream centered eyebrow/heading, responsive two-column card grid, blue circular icon treatment, spacing and typography are preserved.
- Default template: `Secomm_UiWidget::components/usp/b.phtml`.

### USP C provenance

- Internal ID: `usp_c`; Admin label: `Compact Benefits`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `usp/C-compact`.
- Local modifications: replaced fixed CMS rows with a bounded ordered collection of escaped label/text pairs and an explicit Lucide icon allowlist. The upstream mobile horizontal cards, desktop three-column/vertical treatment, blue circular icons, spacing and typography are preserved.
- Default template: `Secomm_UiWidget::components/usp/c.phtml`.

## Parameter persistence

Component-specific values use canonical JSON encoded as Base64URL inside the Magento widget `payload` parameter. The persisted envelope includes format version `1`. Limits are 16 KiB encoded, six nested levels and 50 rows per collection.

The Admin helper renders fields from the server-owned component schema. JavaScript manages only editing, media selection and collection ordering. Magento validates the component ID, schema version, payload format and field types before building a directive or saving a widget instance. Storefront rendering fails closed for tampered data.

## Theme overrides

Default templates will use Magento template aliases such as:

```text
Secomm_UiWidget::components/banner/a.phtml
```

A Hyvä product theme can override presentation at:

```text
Secomm_UiWidget/templates/components/banner/a.phtml
```

The registry, schema and data-provider contracts remain module-owned. The compatibility gate proved this override path with a temporary local fixture; no product-theme POC override is shipped until a real presentation difference is required.

## Adding or updating an upstream component

1. Compare the pinned `source_component` and `source_version` with the target Hyvä UI release.
2. Classify upstream changes as data-only, visual/behavioral, security fix or breaking schema change.
3. Port manually into a module-owned definition/template; never include or scan the vendor component at runtime.
4. Keep existing component IDs stable. Increase `schema_version` only with an explicit migration/backward-compatibility plan.
5. Add or update schema, parity and validation tests, both locale dictionaries, provenance documentation and the eligibility matrix.
6. Run PHP/XML/DI checks, the full module unit suite, Tailwind production build and responsive storefront QA at 390px, 768px and 1440px.
7. Re-run at least one product-theme override and verify unknown/tampered component, schema, enum, URL and payload inputs still fail closed.
