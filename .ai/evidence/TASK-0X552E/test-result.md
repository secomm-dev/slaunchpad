# LC-30 — Test & Static Analysis Result

Task: TASK-0X552E (SPEC-TASK-0X552E, LC-30 AI Discoverability baseline). 2026-08-24.

## Unit tests

Command:

```
docker exec slaunchpad-phpfpm-1 bash -c \
  "cd /var/www/html && vendor/bin/phpunit -c dev/tests/unit/phpunit.xml app/code/Secomm/AiDiscoverability/Test/Unit"
```

Result: **OK — Tests: 21, Assertions: 48** (1 PHPUnit warning from the repo-wide Allure
extension bootstrap, unrelated to the module).

Suites: LlmsTxtFormatterTest, EligibilityCheckerTest, UrlCollectorTest, SeoPolicyTest
(JSON + legacy-serialize noindex rules, wildcard patterns, trailing slash modes),
CanonicalPolicyTest (oldest non-redirect rewrite wins, query strip, double-slash collapse,
null without rewrites, home URL).

## Static analysis

- `bin/magento setup:di:compile` — OK after every DI change (final run clean).
- PHPCS Magento2 on `app/code/Secomm/AiDiscoverability`: 0 errors; docblock warnings fixed
  (see command-output.txt for the final PHPCS summary line).

## Validation labels

- Proven by automated test: formatter determinism/sanitization, eligibility, collector
  bound/dedupe/sort, SEO policy reuse, bounded canonical policy.
- Proven by runtime execution: endpoint contract (GET/HEAD/304/404/POST), cache hit at
  provider seam, describedby link (see runtime-smoke.md).
- Not executable locally: integration test suite (`dev/tests/integration` not wired in this
  docker stack), two-store isolation (single store view), admin preview ACL browser check
  (ACL declared; preview shares the same generator — code-inspection verified).
