# Evidence — TASK-5XDG1P r1: TL-review cleanup `getReceiverText()` (PII hygiene)

Ngày: 2026-09-08 · Cùng work item TASK-5XDG1P (chưa đóng — cleanup theo TL/SA review trước
contract freeze). Không TASK mới, không DEC mới (API hygiene, không phải architecture decision).

## Audit `getReceiverText` / `receiverText` (grep toàn repo, trước cleanup)

| Vị trí | Phân loại | Xử lý |
|---|---|---|
| `Api/Address/ShippingAddressResolutionContextInterface.php:39-40` | interface declaration | XÓA |
| `Model/Address/ShippingAddressResolutionContext.php:35,74-77` | concrete impl (property + promoted param + getter) | XÓA |
| `Test/Unit/Model/Address/ShippingAddressResolutionContextTest.php:29,38,55` | unit tests | UPDATE + thêm regression guard |
| README.md (E-A bullet + E-B cache bullet), CHANGELOG.md (0.4.0/0.5.0) | documentation | UPDATE |
| SPEC-TASK-5XDG1P (§3.1, §6, AC-4, Status r1) | spec | UPDATE |
| TASK-5XDG1P.md (mini-spec §4/§5 + Review rounds r1) | task record | UPDATE |
| SPEC-TASK-AQT7V3 §3.3 + TASK-AQT7V3.md mini-spec | spec/record E-A (cùng batch chưa commit) | UPDATE có chú thích r1 |
| plans/TASK-5XDG1P (risk row) | plan | UPDATE wording |
| SPIKE-W273TB §3.3 (proposed external-resolver signature) | historical research report — proposal THỜI ĐIỂM đó, KHÔNG phải contract shipped | GIỮ NGUYÊN (không sửa lịch sử audit; shape này đã bị thay thế bởi approved E-A shapes) |
| "webhook receivers" (Tracking interface/README) | word usage không liên quan | GIỮ |

**Production consumers: 0** — manager không consume (đúng như TASK-5XDG1P report);
**carrier references: 0** (GiaoHangNhanh/Ghn/GhnAddressMapper/Ghtk/Ahamove grep sạch).
0 instantiation positional bị dịch tham số (chỉ 3 test file instantiate, đều named args).

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress"
OK — 209 tests, 656 assertions (0 failure/error; 5 PHPUnit deprecations pre-existing)

Test mới: ShippingAddressResolutionContextTest::testReceiverIdentityIsNotPartOfTheContextContract
  — method_exists('getReceiverText') = FALSE trên interface + concrete, 'getCustomerName' = FALSE,
  getStreetText vẫn TRUE (không reflection-heavy; khớp convention project).
Manager tests (19) + integration (5) pass KHÔNG đổi — orchestration/cache/4-state behavior giữ nguyên.
```

## Command output (build + validator)

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 15 FAIL, 0 WARN — GIỐNG HỆT baseline pre-existing (stale H1/plan/spec cũ);
0 finding mới liên quan TASK-5XDG1P / cleanup này (grep 5XDG1P = 0 hits trong FAIL/WARN).
```

## Files changed (r1)

- `app/code/Secomm/ShippingCore/Api/Address/ShippingAddressResolutionContextInterface.php` — bỏ getter + docblock boundary sentence
- `app/code/Secomm/ShippingCore/Model/Address/ShippingAddressResolutionContext.php` — bỏ property/param/getter
- `app/code/Secomm/ShippingCore/Test/Unit/Model/Address/ShippingAddressResolutionContextTest.php` — bỏ receiver cases + regression guard
- `app/code/Secomm/ShippingCore/{README.md,CHANGELOG.md}` — wording + mục Removed 0.5.0
- `.ai/specs/SPEC-TASK-5XDG1P-…md`, `.ai/records/tasks/TASK-5XDG1P.md`, `.ai/records/tasks/TASK-AQT7V3.md`,
  `.ai/specs/SPEC-TASK-AQT7V3-…md`, `.ai/plans/TASK-5XDG1P-…md`, `.ai/project/CURRENT_STATE.md` — governance sync

0 file manager/DI/carrier/external-provider thay đổi.
