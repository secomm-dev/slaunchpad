# TASK-XY9RZF — Admin media and required-field correction evidence

Date: 2026-09-08

## Scope

- Shared `media-image` control requests Magento Media Gallery output with `force_static_path`.
- Storefront templates resolve portable media paths against the current store media base URL.
- Shared component editor validates schema `required`, `required_with` and collection `min_items` before BuildWidget.
- No component-specific templates or third-party modules changed.

## Verification

- Registry audit: `components=21 required_fields=74 collections=10`.
- Module unit suite: 86 tests, 504 assertions, all tests passed.
- Final module unit suite after media resolver: 90 tests, 510 assertions, all tests passed.
- PHPUnit process warning only: project Allure configuration file is absent; no test failed.
- `node --check app/code/Secomm/UiWidget/view/adminhtml/web/js/component-options.js`: passed.
- `php -l app/code/Secomm/UiWidget/view/adminhtml/templates/widget/component-options.phtml`: passed.
- `git diff --check`: passed.
- Local Magento mode: developer; config/layout/block_html/full_page/translate caches cleaned.
- Magento DI compilation passed after adding the media resolver dependency.
- Storefront proof on `/secomm-ui`: stored `/media/.renditions/wysiwyg/collection-banner-1.jpg` rendered as `https://slaunchpad.ddev.site/media/.renditions/wysiwyg/collection-banner-1.jpg`.

## Manual QA status

Browser QA could not be completed in-run because macOS Accessibility/Screen Recording permission for Computer Use was not granted. Required follow-up:

1. In CMS Block, open Insert Widget and choose a component with a required image.
2. Confirm Insert is blocked while required fields are empty and inline errors are shown.
3. Select an image, insert the widget, decode/check payload and confirm the image value starts with the storefront media base URL and does not contain `/admin/cms/wysiwyg/directive/`.
