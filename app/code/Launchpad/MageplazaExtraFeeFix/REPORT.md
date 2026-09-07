# Fix: Mageplaza ExtraFee `reset(null)` TypeError — Implementation Report

## Root cause

`Mageplaza_ExtraFee` 4.5.4 (controller `app/code/Mageplaza/ExtraFee/Controller/Product/ExtraFee.php:170`):

```php
$params = $this->_request->getParams();
if ($product->getTypeId() == 'configurable') {
    if (empty(reset($params['super_attribute']))) {
```

When the AJAX request for a configurable product's extra fee does not carry `super_attribute`
(first page load, no option selected yet), `$params['super_attribute']` is `null`.
PHP 8 requires `reset()` to receive an `array`, so `reset(null)` throws
`TypeError: reset(): Argument #1 ($array) must be of type array, null given`.

## Why a plugin

- The Mageplaza baseline was just committed for Architect review and must stay untouched
  (upgrade-safe: module updates won't wipe the fix).
- A **before-plugin on `execute()`** can normalize the request before the controller reads it —
  the smallest possible interception, no class replacement, no event observer, no route override.
- Preference was ruled out: it would replace the whole controller and drift from upstream on updates.

## Files added

| File | Purpose |
|---|---|
| `registration.php` | Module registration (`Launchpad_MageplazaExtraFeeFix`) |
| `etc/module.xml` | Module definition, `sequence` on `Mageplaza_ExtraFee` |
| `etc/frontend/di.xml` | Plugin on `Mageplaza\ExtraFee\Controller\Product\ExtraFee` (frontend area — the route is frontend) |
| `Plugin/Controller/Product/ExtraFeePlugin.php` | `beforeExecute()`: if `super_attribute` is missing/`null` → `setParam('super_attribute', [])`; any other value is left byte-for-byte untouched |
| `Test/Unit/Plugin/Controller/Product/ExtraFeePluginTest.php` | Unit tests |

## Validation / test results

PHPUnit (PHP 8.3.20, `vendor/bin/phpunit` + `dev/tests/unit/framework/bootstrap.php`):

```
.......                  6 / 6 (100%)
OK, but there were issues!
Tests: 6, Assertions: 6, PHPUnit Deprecations: 1
```

Covered cases:

1. missing `super_attribute` → normalized to `[]`
2. `null` `super_attribute` → normalized to `[]`
3. `[]` → unchanged (no `setParam` call)
4. valid selections `[93 => 12]`, `[93 => "12"]`, multi-attribute → unchanged (no `setParam` call)

Static checks: `php -l` clean on all 3 PHP files; both XML files well-formed.
`git diff d3e1b635 -- app/code/Mageplaza` is empty → no Mageplaza code modified.

> Note: the module still needs `bin/magento setup:upgrade` (+ config flush) on the target
> environment to be enabled — intentionally not run here (no production changes in this task).

## Commit

See git history: fix + this report committed together and pushed to `origin/dev/development/thanhle`.
