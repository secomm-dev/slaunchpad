---
id: TASK-ZQ9ZE1
type: task
title: Deliver Batch One Content Components
project_code: SLP
parent: {type: feature, id: FEAT-J06WXZ}
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec; canonical parent ../../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md
risk: medium
status: done
created: 2026-08-24
updated: 2026-08-28
external_refs: {}
legacy_ids: []
ticket_ref:
decisions: [DEC-FEATJ06WXZ-001, DEC-FEATJ06WXZ-002]
decision_assessment:
components: []
source_areas: [app/code/Secomm/UiWidget/view/frontend/templates/components/]
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-08-28
supersedes: []
---

# [SLP][FEAT-J06WXZ][TASK-ZQ9ZE1] Deliver Batch One Content Components

## Summary

Port/adapt toàn bộ component B1 đã duyệt bằng contracts được proof bởi Banner A.

## Mini Spec

### Goal
Cung cấp các content/manual-collection widgets trong matrix B1 với dynamic fields và quality nhất quán.

### Expected Behavior
Mỗi B1 component đăng ký explicit, persist/render dữ liệu động, không demo fallback, responsive/a11y và hỗ trợ theme override.

### Constraints / Rules
Chỉ B1 matrix; implement reviewable slices; provenance/schema version; repeater limits; static Tailwind classes; vi/en strings.

### Out of Scope
B2 providers/components, excluded/deferred inventory và thay đổi foundation contract không qua review.

### Acceptance Criteria
- AC-001: Mọi B1 row được implement hoặc có TL-approved defer reason ghi trong matrix.
- AC-002: Schema/required/default/validation documented cho từng component.
- AC-003: Repeater components giữ order và pass payload limits/round-trip.
- AC-004: Multi-instance Alpine/Tailwind/a11y/security regression pass.
- AC-005: No vendor modification/demo URL/raw class input.

## Approach

Plan: [FEAT-J06WXZ implementation plan](../../plans/FEAT-J06WXZ-implementation-plan.md), task 4.

## Implementation Notes

Implementation started on 2026-08-25 after TL approval of TASK-JN2SH6.

Slice 1 approved on 2026-08-25: establish the trusted rich-text contract and deliver `generic_content_a` as its first B1 consumer. Rich HTML is limited to explicit `trusted-rich-text` fields, authored with Magento WYSIWYG and rendered through the CMS block filter.

Hardening approved on 2026-08-25: Admin-facing labels are semantic (`Hero Banner`, `Rich Text Content`) while stable IDs remain unchanged. Dynamic options use an executable RequireJS initializer because Prototype AJAX `evalScripts()` cannot safely consume `text/x-magento-init` JSON fragments in the CMS Insert Widget dialog.

Slice 2 implemented on 2026-08-25: deliver `accordion_a` with bounded ordered panels, repeated trusted-rich-text editors, native accessible details behavior and single/multiple-open modes. A one-line Snowdog Tailwind v4 compatibility fix was required to unblock the full theme build, matching the previously approved exception for mandatory build blockers.

Accordion Admin UX hardening on 2026-08-25: nested panel controls now use the full Widget Options width, while `Panel Content` starts at and cannot shrink below 440px height. The HugeRTE resize handle responds in one keyboard/drag interaction.

Screenshot-led layout correction on 2026-08-25: the outer dynamic options area now uses 85% of the Magento field row, all dynamic labels use a stacked label/control layout, and panel/editor/action elements are bounded to their container. This removes label wrapping/overlap without returning to the original narrow editor.

Slice 3 implemented on 2026-08-25: deliver `banner_b` (`Split Banner`) from Hyvä UI `banner/B-split` with responsive media, trusted CMS content, compound CTA validation and allowlisted presentation options. Demo content and remote image fallback are removed.

Slice 4 implemented on 2026-08-25: deliver `banner_c` (`Text Banner`) from Hyvä UI `banner/C-text` with dynamic title, trusted CMS content, compound CTA validation and allowlisted layout/palette options. The component keeps its stable upstream-aligned ID while exposing a semantic Admin label.

Visual parity correction approved on 2026-08-26: delivered and future components must preserve the pinned Hyvä UI source layout and visual behaviour. Permitted local differences are restricted to Admin/widget data plumbing, validation/escaping, instance-safe semantics and removal of demo fallbacks. Correction order is Banner A → Banner B → Banner C → Accordion A → Generic Content A, followed by two-theme and responsive regression QA before further B1 expansion.

Correction implementation completed on 2026-08-26 for the five delivered components. The module now self-registers as a Hyvä Tailwind source, preserves the critical upstream layout/classes/interactions, and includes a template parity regression suite. The structural fashion-theme Banner A override was removed so it inherits the module parity baseline.

TL decision on 2026-08-26: second-theme visual QA is deferred and will be performed separately by TL; this does not block the next B1 slice. Slice 5 implements `card_a` (`Feature Card`) from Hyvä UI `card/A-default`, including its responsive picture, trusted CMS body and compound CTA contract.

Admin media UX extension approved on 2026-08-26: existing image fields use the reusable `media-image` control, retaining Magento Media Gallery and the persisted string contract while adding immediate preview and image removal. Applied to Banner A, Banner B and Card A without a schema-version migration.

Media chooser bug-fix approach approved by user report on 2026-08-27: build the chooser URL with Magento's native `/target_element_id/{id}/store/{store}/type/image/` path contract and pass `targetElementId` explicitly. Keep the persisted image input hidden so Admin sees only preview/status and Gallery/Remove actions. Verify Gallery opens without the `browser.js:112` exception and selecting an image updates preview/payload.

Slice 6 implemented on 2026-08-27: deliver `card_b` (`Media Card`) from Hyvä UI `card/B-media`. The default left-media output preserves the upstream structure/classes and adds required Gallery-backed media, trusted CMS body and the established CTA contract. The matrix-required right-media position is opt-in so existing/default visual parity remains unchanged.

Slice 7 implemented on 2026-08-27: deliver `categories_a` (`Image Category Grid`) from Hyvä UI `categories/A-grid-images`. Ordered bounded items expose label, Gallery-backed image, alt text and validated URL. The default output retains the upstream mobile snap slider and desktop grid; disabling the mobile slider is an explicit opt-in extension.

Slice 8 implemented on 2026-08-27: deliver `categories_b` (`Pattern Category Grid`) from Hyvä UI `categories/B-grid-patterns`. Ordered bounded items expose label, validated URL and allowlisted upstream pattern/background combinations. The default output retains the SVG patterns, mobile snap slider, desktop first-item span/height and hover behaviour; disabling the mobile slider remains opt-in.

Slice 9 implemented on 2026-08-27: deliver `embed_a` (`Video Embed`) from Hyvä UI `embed/A-basic`. The schema accepts only provider-matching YouTube/Vimeo URLs, allowlisted loading/aspect-ratio values, Gallery-backed poster, autoplay and enhanced privacy. The storefront preserves upstream poster/play-button lazy loading and iframe layout while using isolated Alpine state and module-owned CSP hosts.

Slice 10 implemented on 2026-08-27: deliver `generic_content_b` (`Visual Content`) from Hyvä UI `generic-content/B-visual`. Dynamic fields cover Gallery-backed media, eyebrow, heading, lead, trusted CMS body, quote and optional CTA. Default left-media output preserves the upstream mobile overlay, desktop prose/header treatment and quote styling; right-media and CTA are explicit opt-in extensions.

Slice 11 implemented on 2026-08-27: deliver `modal_a` (`Information Modal`) from the native-dialog example in Hyvä UI `modal/A-simple`. Required dynamic fields cover trigger, title, trusted CMS body and both close-action labels. Instance-safe dialog IDs and Alpine data names preserve multi-instance behaviour while retaining the upstream `x-htmldialog.noscroll`, warning icon, responsive content and action-footer treatment.

Slice 12 implemented on 2026-08-27: deliver `product_highlights_c` (`Product Highlights`) from Hyvä UI `product-data/C-highlights` in content-only mode. Ordered bounded rows expose title, trusted CMS content, Gallery media, alt/loading and allowlisted left/right image position without reading product context. Each row preserves the upstream mobile stack, desktop square media, spacing and reverse variant.

Slice 13 implemented on 2026-08-27: deliver `shortcuts_a` (`Shortcut Links`) from Hyvä UI `shortcuts/A-simple`. Ordered bounded items expose label, description, validated URL, allowlisted Lucide icon and optional Gallery image replacement. The output preserves the upstream blue wrapper, three-column responsive grid, icon sizing/alignment, label typography and desktop-only descriptions.

Slice 14 implemented on 2026-08-27: deliver `slider_a` (`Content Slider`) from Hyvä UI `slider/A-basic`. Ordered bounded slides expose Gallery media, alt, eyebrow, heading and compound opt-in CTA, while slider-level controls cover accessible label, arrow position, dots and eager first image. The output preserves the upstream CSS snap track, `x-snap-slider` pager/navigation, responsive image heights and caption overlay typography.

Slice 15 implemented on 2026-08-27: deliver `slider_b` (`Logo Marquee`) from Hyvä UI `slider/B-marquee`. Ordered bounded items expose Gallery logo, alt, dimensions and validated optional link. Allowlisted speed/direction and hover/focus pause extend the fixed upstream animation while the default preserves its 40-second leftward motion, duplicated ARIA-hidden row, responsive spacing, masks, borders and reduced-motion pause.

Slice 16 implemented on 2026-08-27: deliver `testimonial_a` (`Customer Testimonial`) from Hyvä UI `testimonial/A-simple`. Required escaped quote and author fields combine with optional role/company, Gallery-backed avatar and opt-in rating. Semantic quote markup preserves the upstream centered layout, typography, decorative quote mark, circular portrait and author treatment.

Slice 17 implemented on 2026-08-27: deliver `testimonial_b` (`Testimonial Card`) from Hyvä UI `testimonial/B-card`. Required escaped quote and author fields combine with optional role/company, Gallery-backed avatar and opt-in rating. Semantic quote markup preserves the upstream slate card, overlapping mobile portrait, desktop media layout, decorative quote and author treatment.

Slice 18 implemented on 2026-08-27: deliver `usp_a` (`Icon Benefits`) from Hyvä UI `usp/A-icons`. Required eyebrow/heading fields and a bounded ordered benefit collection expose title, escaped description, an allowlisted Lucide icon, optional Gallery image replacement and validated optional link. The output preserves the upstream responsive two-column grid, circular icon treatment, spacing and typography.

Slice 19 implemented on 2026-08-27: deliver `usp_b` (`Benefit Cards`) from Hyvä UI `usp/B-cards`. Required eyebrow/heading fields and a bounded ordered card collection expose title, escaped description, an allowlisted Lucide icon, optional Gallery image replacement and compound opt-in CTA. The output preserves the upstream responsive two-column card grid, blue circular icon treatment, spacing and typography.

Slice 20 implemented on 2026-08-27: deliver `usp_c` (`Compact Benefits`) from Hyvä UI `usp/C-compact`. A bounded ordered collection exposes escaped label/text pairs and an allowlisted Lucide icon. The output preserves the upstream mobile horizontal cards, desktop three-column/vertical treatment, blue circular icons, spacing and typography.

Batch 1 component implementation completed on 2026-08-27: all 21 approved B1 component IDs are registered with module-owned schemas/templates and pinned Hyvä UI 2.8.0 provenance. Final two-theme regression and closure remain governed by the separate compatibility gate in the implementation plan.

## Verification

- [x] AC-001..005 — tracked QA summary: `.ai/evidence/TASK-ZQ9ZE1/qa-summary.md`; final compatibility evidence: `.ai/evidence/TASK-XY9RZF/`.
- [x] Slice 1 unit suite: 25 tests, 57 assertions on PHP 8.4 DDEV (`--no-extensions`).
- [x] Slice 1 static checks: PHP syntax, JavaScript syntax, XML syntax and `git diff --check` pass.
- [x] QA 2026-08-25 after Magezon removal: native Widget Instance flow exposes `Secomm UI`, both registered components and Magento WYSIWYG for `trusted-rich-text`; no Admin console error.
- [x] QA 2026-08-25: CMS block `secomm_ui_qa` renders `generic_content_a` on Homepage; rich Vietnamese HTML and nested `{{store}}` directive resolve correctly. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-25-slice-1-qa.md`.
- [x] Accordion A browser stress verification: two repeater rows initialize two WYSIWYG editors; reordering preserves two editors; switching component tears them down and initializes only the selected component editor.
- [ ] Project spec validator is currently unavailable because `.ai/bin/project-ai-validate` has a pre-existing unmatched quote near line 831; no validator file was changed in this slice.
- [x] Independent sub-agent review findings addressed: overflow now clears the persisted payload and shows a blocking server-validation path; editor IDs are root-scoped and teardown removes the correct TinyMCE event registration.
- [x] Admin AJAX regression QA 2026-08-25: nested Insert Widget popup initializes independently when an outer Secomm UI form already exists; switching `Hero Banner` → `Rich Text Content` renders the correct 17 → 4 fields and one rich-text editor. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-25-admin-ajax-widget-hardening.md`.
- [x] Accordion A slice: 27 tests / 68 assertions; PHP/JS/XML/diff checks pass; full Tailwind v4 build passes; CMS block QA renders two ordered native details panels on Homepage. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-25-accordion-a-slice.md`.
- [x] Accordion panel editor UX QA: editor expanded from approximately 171px to 790px wide at the test viewport, initializes at 440px high and resizes from 440px to 460px with one resize-handle action.
- [x] Screenshot regression QA after layout correction: at 1280px the editor is 672px wide; at 1024px it is 518px wide. All labels remain one line and root/panel/editor report no horizontal overflow.
- [x] Scoped Admin offset regression QA in CMS Static Block ID 18: Page Builder `HTML Code` → `Insert Widget...` → `Secomm UI` → `Accordion` → `Add Item` keeps both outer options and nested Panel fields aligned after overriding the core `margin-left: -30px`; no global Admin field rule was changed. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-25-static-block-panel-item-offset-fixed.png`.
- [x] Banner B slice: 29 tests / 80 assertions; PHP/XML/diff checks, DI compilation and Tailwind v4 production build pass. Static Block Insert Widget QA exposes `Split Banner` and initializes its dynamic WYSIWYG fields. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-25-banner-b-slice.md`.
- [x] Static Block QA inventory now also includes the previously approved `banner_a`; the existing Generic Content, Accordion and Split Banner directives remain intact. Storefront `/home?___store=default` renders exactly one Hero Banner. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-25-banner-a-static-block.md`.
- [x] Banner C slice: 31 tests / 90 assertions; PHP/XML/diff checks, DI compilation and Tailwind v4 production build pass. Static Block ID 18 persists one `banner_c` directive alongside all existing QA widgets, and storefront renders exactly one Text Banner. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-25-banner-c-slice.md`.
- [x] Visual parity correction: 36 tests / 115 assertions; PHP/XML/JS/diff checks, DI compilation and Tailwind v4 production build pass. Existing schema-version-1 Static Block samples render with corrected Hyvä UI structure and Admin schemas reflect the upstream content slots. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-26-visual-parity-correction.md`.
- [x] Card A slice: 39 tests / 132 assertions; PHP/XML/JS/diff checks, DI compilation and Tailwind v4 production build pass. Static Block ID 18 persists exactly one Card A sample and storefront renders its media/content/CTA without internal horizontal overflow. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-26-card-a-slice.md`.
- [x] Media image preview extension: 40 tests / 140 assertions and Tailwind v4 production build pass. Banner A, Banner B and Card A expose two working Gallery/preview/remove controls; preview load and empty-state restoration were browser-verified without saving the QA Widget Instance. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-26-media-image-preview.md`.
- [x] Card B slice: 43 tests / 162 assertions; PHP/XML/JS/diff checks, DI compilation and Tailwind v4 production build pass. Static Block ID 18 persists exactly one Media Card sample; Homepage renders its loaded media/content/CTA with no console error or horizontal overflow at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-card-b-slice.md`.
- [x] Categories A slice: 46 tests / 185 assertions; PHP/XML/JS/diff checks, DI compilation and Tailwind v4 production build pass. Static Block ID 18 persists exactly one three-item Image Category Grid sample; Homepage preserves mobile snap scrolling, desktop first-item grid span and item order without page overflow or console errors at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-categories-a-slice.md`.
- [x] Categories B slice: 49 tests / 211 assertions; PHP/XML/JS/diff checks, DI compilation and Tailwind v4 production build pass, including generated SVG-pattern utilities. Static Block ID 18 persists exactly one four-item Pattern Category Grid sample; Homepage preserves mobile snap scrolling, desktop first-item span/height, patterns and colors without page overflow or console errors at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-categories-b-slice.md`.
- [x] Embed A slice: 53 tests / 237 assertions; provider-matching URL regression, PHP/XML/JS/diff checks, DI compilation and Tailwind v4 production build pass. Static Block ID 18 persists exactly one privacy-enabled Video Embed sample; Homepage verifies poster-to-iframe interaction, YouTube no-cookie URL, accessibility/CSP and 16:9 sizing without overflow or console errors at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-embed-a-slice.md`.
- [x] Generic Content B slice: 56 tests / 263 assertions and Tailwind v4 production build pass. Static Block ID 18 persists exactly one Visual Content sample; Homepage preserves the mobile overlay and desktop two-column treatment with loaded media, semantic responsive headings, configured rich content/quote/CTA, no horizontal overflow and no console errors at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-generic-content-b-slice.md`.
- [x] Modal A slice: 59 tests / 284 assertions; PHP/diff checks, DI compilation and Tailwind v4 production build pass. Static Block ID 18 persists exactly one Information Modal sample; Homepage verifies native-dialog open/close through both actions and Escape, focus transfer, unique ARIA labelling, scroll lock, responsive containment and a clean console at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-modal-a-slice.md`.
- [x] Product Highlights C slice: 62 tests / 307 assertions; PHP/diff checks, DI compilation and Tailwind v4 production build pass. Static Block ID 18 persists exactly one two-row Product Highlights sample; Homepage verifies ordered trusted content, loaded Gallery media, mobile stacking and desktop left/right variants with no overflow or console errors at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-product-highlights-c-slice.md`.
- [x] Shortcuts A slice: 65 tests / 330 assertions; PHP/diff checks, DI compilation and Tailwind v4 production build pass. Static Block ID 18 persists exactly one three-item Shortcut Links sample; Homepage verifies ordered URLs, two allowlisted Lucide icons, one loaded Gallery image, upstream responsive grid/alignment/description visibility, no overflow and a clean console at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-shortcuts-a-slice.md`.
- [x] Slider A slice: 68 tests / 358 assertions; PHP/diff checks, DI compilation and Tailwind v4 production build pass. Static Block ID 18 persists exactly one three-slide Content Slider sample; Homepage verifies `x-snap-slider` arrows/dots, current/disabled states, eager/lazy loading, upstream responsive heights, captions/CTA, no overflow and a clean console at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-slider-a-slice.md`.
- [x] Slider B slice: 71 tests / 386 assertions; PHP/diff checks, DI compilation, Tailwind v4 production build and static deployment pass. Static Block ID 18 persists exactly one five-item Logo Marquee sample; Homepage verifies loaded Gallery media, the ARIA-hidden non-interactive duplicate row, 40-second infinite motion, real translation, hover pause, responsive spacing and no horizontal overflow at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-slider-b-slice.md`.
- [x] Testimonial A slice: 74 tests / 409 assertions; PHP/diff checks, DI compilation, Tailwind v4 production build and static deployment pass. Static Block ID 18 persists exactly one Customer Testimonial sample; Homepage verifies semantic quote markup, escaped dynamic content, loaded Gallery avatar, optional localized five-star label, upstream typography/layout and no horizontal overflow at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-testimonial-a-slice.md`.
- [x] Testimonial B slice: 77 tests / 433 assertions; PHP/diff checks, DI compilation, Tailwind v4 production build and static deployment pass. Static Block ID 18 persists exactly one Testimonial Card sample; Homepage verifies semantic quote markup, loaded Gallery avatar, optional localized five-star label, the upstream overlapping mobile portrait and desktop flex treatment, and no horizontal overflow at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-testimonial-b-slice.md`.
- [x] USP A slice: 80 tests / 458 assertions; PHP/diff checks, DI compilation, Tailwind v4 production build and static deployment pass. Static Block ID 18 persists exactly one four-item Icon Benefits sample; Homepage verifies three allowlisted Lucide icons, one loaded Gallery replacement, one optional link, upstream responsive one/two-column layout and no horizontal overflow at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-usp-a-slice.md`.
- [x] USP B slice: 83 tests / 485 assertions; PHP/diff checks, DI compilation, Tailwind v4 production build and static deployment pass. Static Block ID 18 persists exactly one four-card Benefit Cards sample; Homepage verifies three allowlisted Lucide icons, one loaded Gallery replacement, one compound optional CTA, upstream responsive one/two-column card layout and no horizontal overflow at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-usp-b-slice.md`.
- [x] USP C slice: 86 tests / 504 assertions; PHP/diff checks, DI compilation, Tailwind v4 production build and static deployment pass. Static Block ID 18 persists exactly one three-card Compact Benefits sample; Homepage verifies allowlisted Lucide icons, upstream mobile/tablet row and desktop three-column treatments, and no horizontal overflow at 390px, 768px and 1440px. Evidence: `.ai/runtime/evidence/TASK-ZQ9ZE1/2026-08-27-usp-c-slice.md`.
- [x] Final responsive/two-theme compatibility gate completed on 2026-08-28; see `.ai/evidence/TASK-XY9RZF/2026-08-28-final-compatibility.md`.

## Related records

- Parent: FEAT-J06WXZ
- Matrix: B1 table
