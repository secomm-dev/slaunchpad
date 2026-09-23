# TASK-ZQ9ZE1 — Video Embed Admin validation fix

Date: 2026-09-17

## Mini-spec

- Mark `embed_a.provider` as required in the component schema and Admin form.
- Display the `Accessible Video Title` validation message with the same spacing and Magento error treatment as other fields in the Insert Widget popup.
- Keep the change scoped to `Secomm_UiWidget`; do not alter Magento core or third-party code.

## Approach and correction

- Added `required: true` to the `provider` schema field, preserving the existing `youtube` default and provider allowlist.
- Added a validation-only `name` plus native `required` and Magento `required-entry` rules to every required dynamic control. Magento's widget popup validator ignores unnamed controls, which previously allowed the required Video URL and Accessible Video Title fields to bypass client-side validation. These names are outside `parameters[...]`, so they are not persisted in the widget directive.
- Deliberately avoided `data-validate` because the legacy Widget popup initializes jQuery metadata without `meta: 'validate'`; that attribute is consequently misread as a nonexistent `validate` rule and throws from `jquery.validate.js`.
- Required dynamic rows now receive Magento's `_required` class so their labels consistently show required state.
- Added a popup-scoped CSS override for Secomm UI validation labels. This neutralizes Magento's legacy `.popup-window label.mage-error { margin: -29px ... }` rule without affecting other Admin widgets.
- Added a unit assertion for the provider required contract.

## Verification

- PHP lint: passed.
- JavaScript syntax check: passed.
- `git diff --check`: passed.
- `EmbedADefinitionTest`: `2 tests, 15 assertions`, all assertions passed.
- PHPUnit reports the pre-existing missing `allure/allure.config.php` runner warning.
- Magento is in developer mode; Admin JS/CSS assets are symlinked to module sources.
- Magento `config`, `layout`, `block_html` and `full_page` caches were cleaned.
- Browser QA could not proceed past the local Admin login screen because the available browser sessions were not authenticated; no credentials or Admin data were changed.

## All-component validation audit

The shared Admin renderer was checked against all 21 registered Batch 1 definitions. The audit identified and covered four schema contracts:

| Validation contract | Components |
| --- | --- |
| Required scalar/editor fields | `accordion_a`, `banner_a`, `banner_b`, `banner_c`, `card_a`, `card_b`, `categories_a`, `categories_b`, `embed_a`, `generic_content_a`, `generic_content_b`, `modal_a`, `product_highlights_c`, `shortcuts_a`, `slider_a`, `slider_b`, `testimonial_a`, `testimonial_b`, `usp_a`, `usp_b`, `usp_c` |
| Required Media Gallery image fields | `banner_a`, `banner_b`, `card_a`, `card_b`, `categories_a`, `generic_content_b`, `product_highlights_c`, `slider_a`, `slider_b` |
| Required collections / `min_items` | `accordion_a`, `categories_a`, `categories_b`, `product_highlights_c`, `shortcuts_a`, `slider_a`, `slider_b`, `usp_a`, `usp_b`, `usp_c` |
| Conditional `required_with` pairs | `banner_a`, `banner_b`, `banner_c`, `card_a`, `card_b`, `categories_a`, `categories_b`, `generic_content_b`, `shortcuts_a`, `slider_a`, `slider_b`, `testimonial_a`, `testimonial_b`, `usp_a`, `usp_b` |

Renderer corrections:

- Required image hidden inputs now participate in Magento validation instead of being excluded.
- Required collections receive a validation sentinel and cannot be inserted below `min_items`.
- Conditional fields are toggled required only when a configured peer has a value, scoped to the same root component or repeater row.
- Validation-only controls remain outside `parameters[...]` and therefore do not alter widget directives or payload data.
- Full module suite: all `93 tests, 519 assertions` passed; the runner still reports the pre-existing missing Allure configuration warning.

## Media Gallery target regression follow-up

- Reproduced from the Admin stack trace: Magento's `insertImageAction` can receive a non-jQuery target from the WYSIWYG adapter and then fail at `targetElement.data(...)`.
- Secomm `media-image` inputs now carry a module-owned target marker. A scoped Admin RequireJS mixin resolves only those marked targets directly as jQuery objects and delegates all other targets to Magento unchanged.
- JavaScript syntax and `git diff --check` pass after the change.
- Full module regression suite remains green: `93 tests, 519 assertions` on PHP 8.4.18 in DDEV.
- Magento `config`, `layout`, `block_html` and `full_page` caches were cleaned before manual Admin retest.
- Pre-commit schema inventory covered all 21 registry definitions: 75 required declarations, 27 `required_with` declarations, 10 required collections and 9 required Media Gallery fields.
- Magento DI compilation, module XML validation, JavaScript syntax, PHP lint, PHP coding-standard error gate and `git diff --check`: passed.
- Frontend smoke test after cache clean rendered all eight widget markers currently configured on `/secomm-ui`: `card_b`, `embed_a`, `generic_content_b`, `modal_a`, `product_highlights_c`, `testimonial_a`, `usp_a` and `usp_b`.
