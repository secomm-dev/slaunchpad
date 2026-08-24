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

## Second pass (acceptance fix) — public-safe summary fallback (2026-08-24, same branch)

Acceptance review rejected `> Default Store View` (internal store-view name) as summary.
Change: summary fallback is now `brand_summary` → effective public Site / Brand Title →
**blockquote omitted**; `$store->getName()` is never emitted as the summary (H1 last resort
unchanged). Admin comment, i18n (en/vi), README and spec §12.2 updated accordingly.

| Check | Result |
|---|---|
| Unit tests | 40 tests, 80 assertions, 0 failures |
| PHPCS (php) | 0 errors / 0 warnings |
| `php -l` changed files | clean |
| `setup:di:compile` | success |
| `git diff --check` | clean |

Runtime after fix (`site_title` = `Secomm Launchpad`, `brand_summary` empty):

```
# Secomm Launchpad
> Secomm Launchpad

Locale: vi_VN
Currency: EUR
...
```

- `> Default Store View` absent (grep count 0) ✓
- H1 / Markdown links / Currency unchanged ✓
- HEAD 200, POST 404, If-None-Match 304 ✓

New tests (`Test/Unit/Service/LlmsTxtGeneratorTest.php`): configured summary wins; empty
summary → public title; no public identity → blockquote omitted and internal name never
appears as summary; Currency/format assertions unchanged in FormatterTest.

---

Notes (honest limitations, per task instruction not to fake multi-store evidence):
- Single store view exists in this environment; per-store-view title/currency overrides were
  NOT exercised at runtime, only via unit tests (`ConfigTest`).
- H1 and summary both prove the configured `site_title` (summary fallback = public title;
  fixed in the second pass above).
- `Currency: EUR` reflects the store-scoped `currency/options/default` of this install.
- No CMS page / category on this install carries a `meta_description`, so the optional
  `: Description` suffix is covered by unit tests only at runtime granularity.
- `PHPSESSID` on responses is a pre-existing environment behavior (third-party session
  start at front-controller level), unchanged by this task.
