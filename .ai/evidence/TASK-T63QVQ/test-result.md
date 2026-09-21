# TASK-T63QVQ — Verification evidence

Date: 2026-09-16
Environment: local docker (`launchpad-docker-phpfpm-1`, Magento 2.4.8-p5, developer mode)

## Automated checks

- `php -l` on `Model/InstructionsConfigProvider.php` and `Model/Config.php` — no syntax errors.
- `bin/magento setup:di:compile` — "Generated code and dependency injection configuration successfully."
- `bin/magento cache:flush config full_page` — flushed.
- `.ai/bin/project-ai-validate --check-records --check-identity` — record `TASK-T63QVQ` has no findings (17 pre-existing FAILs in other records, unchanged by this task).

## Runtime check — AC-003 (default/fallback branch)

Booted `Secomm\VietQr\Model\InstructionsConfigProvider` via `Magento\Framework\App\Bootstrap` in frontend area, no logo configured:

```
logoSrc: https://ngrok.victorpham.io.vn/static/version1789542145/frontend/_view/vi_VN/Secomm_VietQr/images/logo.svg
instructions key: present
```

- No logo uploaded → falls back to the default module asset (expected).
- Existing `payment.instructions.secomm_vietqr` entry still emitted (no regression).

## Runtime check — AC-002 (uploaded branch, round-trip)

Config row `payment/secomm_vietqr/logo` = `default/test-logo.png` (scope default) + file placed at `pub/media/vietqr/default/test-logo.png`, config cache flushed:

```
uploaded logoSrc: https://ngrok.victorpham.io.vn/media/vietqr/default/test-logo.png
URL MATCHES media/vietqr/default/
```

- Stored value format (`default/test-logo.png`, scope-prepended per `File::_prependScopeInfo`) resolves to exactly the physical upload dir `media/vietqr/default/` — contract between `upload_dir` config and provider concat holds.
- `curl -sI` on the resolved URL through the live tunnel: **HTTP 200** — file served by nginx.
- Cleanup after test: config row deleted, test file removed, config cache flushed; provider re-checked → default asset again.

## Revision 2026-09-16 (review feedback)

Logo made optional: bundled default asset fallback removed (dead asset `view/frontend/web/images/logo.png` deleted). No upload configured → `logoSrc` = '' and the template's `ko if` renders no image. `assetRepository` dependency dropped from the provider constructor (`setup:di:compile` re-run, passes). Runtime re-check: upload branch still resolves the live `VietQR_Logo.png` media URL; `instructions` intact.

## Gotcha found (see LL-0027)

`bin/magento config:set payment/secomm_vietqr/logo <value>` writes **NULL**: CLI-set goes through the field backend model `Magento\Config\Model\Config\Backend\Image`, whose `beforeSave()` sees no upload data and calls `unsValue()`. Image-type config fields can only be set via the admin form (upload flow) or direct DB row + `cache:flush config`. Admin form flows (upload / keep-existing / delete checkboxes) are unaffected.

## Manual — pending QA (browser)

- AC-001: admin form — Logo field renders with preview + delete after a real upload. **De facto proven 2026-09-16 16:04**: a real admin upload landed at `media/vietqr/default/VietQR_Logo.png` with config row `default/VietQR_Logo.png`; provider returns its media URL and `curl` gets HTTP 200 `image/png`.
- AC-004: checkout DOM — logo `<img class="payment-icon" width="40">` renders next to the VietQR title when a logo is configured (ko-if guard; no logo configured → title only). Uploaded logo is 696×206: width 40 renders it ~40×12px — bump `width` in `vietqr.html` if the wordmark reads too small. Static `/static/version.../...` URLs 404 via curl for BOTH Secomm_ZaloPay and Secomm_VietQr assets in this env (env-wide serving quirk, not module-specific).
