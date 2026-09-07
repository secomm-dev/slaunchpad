# LC-30 — Test & Static Analysis Result

Task: TASK-0X552E (SPEC-TASK-0X552E, LC-30 AI Discoverability baseline). 2026-08-24.

## Unit tests

Command:

```
docker exec slaunchpad-phpfpm-1 bash -c \
  "cd /var/www/html && vendor/bin/phpunit -c dev/tests/unit/phpunit.xml app/code/Secomm/AiDiscoverability/Test/Unit"
```

Result: **OK — Tests: 29, Assertions: 66** (1 PHPUnit warning from the repo-wide Allure
extension bootstrap, unrelated to the module).

Suites: LlmsTxtFormatterTest, EligibilityCheckerTest, UrlCollectorTest, SeoPolicyTest
(JSON + legacy-serialize noindex rules, wildcard patterns, trailing slash modes),
CanonicalPolicyTest (oldest non-redirect rewrite wins, query strip, double-slash collapse,
null without rewrites, home URL), and — added in the final-correction pass —
Controller/Index/IndexTest (HTTP cache semantics: lifetime 86400/custom → matching
`Cache-Control: max-age`, lifetime 0 → no-store + no ETag, conditional 304, disabled 404)
and Controller/RouterTest (GET/HEAD matched; POST/PUT/DELETE/PATCH/OPTIONS and non-root
paths not matched → deterministic 404).

## Static analysis

- `bin/magento setup:di:compile` — OK after every DI change (final run clean).
- PHPCS Magento2 on `app/code/Secomm/AiDiscoverability`: 0 errors / 0 warnings after the
  final-correction pass (HTTP cache semantics from store config, GET/HEAD-only routing,
  bilingual i18n CSVs).

## Validation labels

- Proven by automated test: formatter determinism/sanitization, eligibility, collector
  bound/dedupe/sort, SEO policy reuse, bounded canonical policy.
- Proven by runtime execution: endpoint contract (GET/HEAD/304/disabled 404, unsupported
  methods 404), HTTP TTL aligned with configured cache lifetime incl. lifetime 0 no-store,
  cache hit at provider seam, describedby link, storefront renders (see runtime-smoke.md).
- Not executable locally: integration test suite (`dev/tests/integration` not wired in this
  docker stack), two-store isolation (single store view), admin preview ACL browser check
  (ACL declared; preview shares the same generator — code-inspection verified).
