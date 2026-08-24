# LC-30.1 — llms.txt v2 conformance evidence (2026-08-24)

Branch: `task/lc-30-llms-v2-conformance` (base `dev/development/thanhle` @ `22997372`).

## Static validation

| Check | Command | Result |
|---|---|---|
| Unit tests | `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml app/code/Secomm/AiDiscoverability/Test/Unit` | 37 tests, 76 assertions, 0 failures (2 runner warnings: allure config + phpunit cache perms — pre-existing env issues) |
| PHPCS | `vendor/bin/phpcs --standard=Magento2 --extensions=php app/code/Secomm/AiDiscoverability` | 0 errors, 0 warnings |
| Lint | `php -l` on all changed PHP files | no syntax errors |
| DI compile | `bin/magento setup:di:compile` | success |

## Runtime smoke (single-store local docker, https://webhook.thanhaloha.io.vn/)

`seocomm_ai_discoverability/general/site_title` set to `Secomm Launchpad` (default scope)
to demonstrate the H1 title chain; caches flushed before probing.

| Probe | Result |
|---|---|
| `GET /llms.txt` | `200`, body below |
| `HEAD /llms.txt` | `200` |
| `POST /llms.txt` | `404` |
| `GET` + matching `If-None-Match` | `304` |
| Storefront home `<link rel="describedby">` | present |
| Storefront home | `200` |

Runtime body (abridged):

```
# Secomm Launchpad
> Default Store View

Locale: vi_VN
Currency: EUR

## Priority Pages
- [Default Store View](https://webhook.thanhaloha.io.vn)

## Pages
- [Enable Cookies](https://webhook.thanhaloha.io.vn/enable-cookies)
- [Home page](https://webhook.thanhaloha.io.vn/home)
- [Privacy and Cookie Policy](https://webhook.thanhaloha.io.vn/privacy-policy-cookie-restriction-mode)
```

Notes (honest limitations, per task instruction not to fake multi-store evidence):
- Single store view exists in this environment; per-store-view title/currency overrides were
  NOT exercised at runtime, only via unit tests (`ConfigTest`).
- H1 proves the configured `site_title`; the `>` summary still falls back to the internal
  store view name because `brand_summary` is unconfigured — out of scope for LC-30.1.
- `Currency: EUR` reflects the store-scoped `currency/options/default` of this install.
- No CMS page / category on this install carries a `meta_description`, so the optional
  `: Description` suffix is covered by unit tests only at runtime granularity.
- `PHPSESSID` on responses is a pre-existing environment behavior (third-party session
  start at front-controller level), unchanged by this task.
