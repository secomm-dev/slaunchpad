# TASK-XY9RZF — Mirasvit SEO Autolink compatibility evidence

Date: 2026-09-17

## Root cause

- CMS page ID 9 contains valid payloads for `card_b`, `product_highlights_c`, `testimonial_a` and `generic_content_b`.
- Magento CMS filtering rendered all four components (`6116` bytes).
- Mirasvit `TextProcessorService::addLinks()` returned an empty string after its legacy anchor regex exhausted the PCRE backtrack limit.
- Mirasvit's CMS page plugin replaced the valid page output with that empty string.

## Correction

- Added a frontend-only after plugin for Mirasvit `TextProcessorService::addLinks()`.
- The plugin restores source HTML only when source is non-empty, contains a Secomm UI component marker and the processed result is null/blank.
- Successful Autolink output and non-Secomm CMS content remain unchanged.
- Warning logs contain only source length, not CMS content.

## Verification

- PHP lint: passed.
- XML validation: passed.
- `git diff --check`: passed.
- Module unit suite: all `93 tests, 518 assertions` passed; the runner reports the pre-existing missing `allure/allure.config.php` extension configuration warning.
- Magento DI compilation: passed.
- CMS page ID 9, cache MISS: all four component markers rendered.
- CMS page ID 9, cache HIT: all four component markers rendered.
- Runtime warning confirmed fallback execution with source length metadata only.
