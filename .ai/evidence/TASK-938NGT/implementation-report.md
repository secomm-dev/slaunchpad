# Evidence — TASK-938NGT (llms.txt single Brand Summary + /ai/store purpose alignment)

Branch: `task/llms-store-metadata-contract` — base: `3b26189e` (origin/dev/development/thanhle)
Ngày: 2026-09-10. Môi trường thực thi: docker `markoshust/magento-php:8.3-fpm`
(worktree mounted `/var/www/html`, vendor từ compose/src mount read-only).

## 1. Direct formatter output (EXPECTED OUTPUT verification)

```
$ php -r "require 'app/code/Secomm/AiDiscoverability/Service/LlmsTxtFormatter.php';
          $f = new Secomm\AiDiscoverability\Service\LlmsTxtFormatter();
          echo $f->format('Demo store1', 'vi_VN',
              ['Store Summary' => [['prose' => ['Hyva theme1']]]], 'VND');"

# Demo store1

Locale: vi_VN
Currency: VND

## Store Summary
Hyva theme1
```

- Không chứa `> Hyva theme1` ✓ (DoD-001)
- Khớp 100% EXPECTED OUTPUT của ticket.

## 2. PHP syntax (php -l, PHP 8.3.14 image) — 7/7 pass

```
No syntax errors detected in app/code/Secomm/AiDiscoverability/Service/LlmsTxtFormatter.php
No syntax errors detected in app/code/Secomm/AiDiscoverability/Service/LlmsTxtGenerator.php
No syntax errors detected in app/code/Secomm/AiDiscoverability/Service/Source/CommerceEndpointsSource.php
No syntax errors detected in app/code/Secomm/AiDiscoverability/Test/Unit/Service/LlmsTxtFormatterTest.php
No syntax errors detected in app/code/Secomm/AiDiscoverability/Test/Unit/Service/LlmsTxtGeneratorTest.php
No syntax errors detected in app/code/Secomm/AiDiscoverability/Test/Unit/Service/Source/CommerceEndpointsSourceTest.php
No syntax errors detected in app/code/Secomm/AiCommerce/Test/Unit/Service/Response/StoreDtoTest.php
```

## 3. Focused unit suites (vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml)

```
AiDiscoverability/Test/Unit → OK (79 tests, 226 assertions)
AiCommerce/Test/Unit        → OK (135 tests, 223 assertions)

--filter 'LlmsTxtFormatterTest|LlmsTxtGeneratorTest|CommerceEndpointsSourceTest|StoreDtoTest'
→ OK, but there were issues! Tests: 30, Assertions: 98, PHPUnit Deprecations: 5.
```

- 30 tests / 98 assertions — 0 failure, 0 error. 5 PHPUnit deprecations là notice
  framework-level (mock API), không phải failure, tồn tại từ baseline suite.
- Test mới `StoreDtoTest` pin: exact V1 allowlist (keys + values + thứ tự),
  internal fields bị loại (`website_id`, `store_group_id`, `name`,
  `extension_attributes`), base_url trailing-slash normalization.

## 4. PHPCS Magento2 — changed files scope

```
$ vendor/bin/phpcs -p --standard=Magento2 <7 changed files>
....... 7 / 7 (100%)   → 0 errors, 0 warnings
```
(Ghi chú: đã sửa kèm 1 warning baseline `@param $commerce` lệch tên trong
LlmsTxtGenerator constructor docblock — file thuộc scope thay đổi.)

## 5. Old-wording sweep (module scope)

```
$ grep -rn 'supported public catalog context' app/code/
→ chỉ còn app/code/Secomm/AiDiscoverability/CHANGELOG.md:28 (trích dẫn lịch sử
  "(was: ...)" trong entry 1.6.1 — có chủ đích).
$ grep 'Falls back to the Site / Brand Title' app/code/ → ABSENT
$ grep 'SUMMARY_MAX_LENGTH' app/code/Secomm/AiDiscoverability/ → ABSENT
```

## 6. Diff summary

| File | Thay đổi | Lý do |
|---|---|---|
| `Secomm/AiDiscoverability/Service/LlmsTxtFormatter.php` | bỏ `$summary` param + blockquote; const rename | Issue 1 |
| `Secomm/AiDiscoverability/Service/LlmsTxtGenerator.php` | bỏ summary fallback; format() call mới; docblock @param | Issue 1 |
| `Secomm/AiDiscoverability/Service/Source/CommerceEndpointsSource.php` | purpose `/ai/store` | Issue 2 |
| `Secomm/AiDiscoverability/etc/adminhtml/system.xml` | comment brand_summary | Docs |
| `Secomm/AiDiscoverability/i18n/{en_US,vi_VN}.csv` | source string + translation | Docs |
| `Secomm/AiDiscoverability/README.md` | cấu trúc output + bảng config | Docs |
| `Secomm/AiDiscoverability/CHANGELOG.md` | entry 1.6.1 | Docs |
| `Secomm/AiDiscoverability/Test/.../LlmsTxtFormatterTest.php` | signature mới + 2 test mới | Tests |
| `Secomm/AiDiscoverability/Test/.../LlmsTxtGeneratorTest.php` | exact-bytes + rename 3 test | Tests |
| `Secomm/AiDiscoverability/Test/.../CommerceEndpointsSourceTest.php` | purpose mới | Tests |
| `Secomm/AiCommerce/Test/Unit/Service/Response/StoreDtoTest.php` | MỚI — V1 allowlist pin | Tests |
| `.ai/records/tasks/TASK-938NGT.md` + evidence dir này | task record | .ai standard |

Không đụng: `StoreDto.php` runtime, vendor, compose/src working tree, Bitbucket.
