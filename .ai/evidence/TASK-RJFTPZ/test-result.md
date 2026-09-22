# Evidence — TASK-RJFTPZ (Phase GHN-A skeleton Secomm_Ghn)

Date: 2026-09-10 · Implementer: Claude (AI) · Environment: WSL2, PHP 8.3.31, PHPUnit 10.5.64

## 1. Scoped unit tests (AC-A3..A5)

Command: `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter 'Secomm\\Ghn'`

```text
D..........................................................       59 / 59 (100%)
OK, but there were issues!
Tests: 59, Assertions: 141, PHPUnit Deprecations: 6.
```

- 0 failures, 0 errors. 6 PHPUnit deprecations (PHPUnit 10.5 mock-generator notices, non-blocking —
  không ảnh hưởng kết quả; các suite khác trong repo cũng emit cùng loại).
- Test files: `ConfigTest` (7), `GhnSchemesTest` (3), `GhnAddressCapabilityTest` (3),
  `GhnErrorTranslatorTest` (13), `GhnApiClientTest` (16), `TokenScrubTest` (4).

## 2. Full scoped suite (regression)

Command: `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml`

```text
Tests: 1127, Assertions: 3813, Errors: 7, Failures: 3.
```

Phân loại:

| Nhóm | Số lượng | Trạng thái |
|---|---|---|
| `Secomm\Tracking\Test\Unit\Event\EventNormalizerTest` | 5 errors + 2 failures | **pre-existing baseline** (CURRENT_STATE: "7 errors full-suite = Secomm_Tracking pre-existing") |
| `Secomm\Ghtk\Test\Unit\Model\Address\GhtkDestinationResolverTest` | 2 errors + 1 failure | **stream song song** (TASK-7AJ3K8 GHTK alignment đang in-flight trên working tree — không phải scope FEAT này) |
| `Secomm\Ghn` | **0** | sạch |

## 3. Class-load verification (thay thế `setup:di:compile`)

`setup:di:compile` + `setup:upgrade` hiện **không chạy được trên môi trường** (MySQL container down —
`SQLSTATE[HY000] 2002 Connection refused`; Docker daemon off trên Windows host). Verification thay thế:

- `php -l` toàn bộ 26 file PHP của module: pass.
- Load toàn bộ 20 class/interface của module dưới unit bootstrap: `loaded: 20 / 20 — all classes
  load OK` (bao gồm wiring preference/virtualType qua class_exists).
- `bin/magento module:enable Secomm_Ghn`: `Secomm_Ghn => 1` đã ghi vào `app/etc/config.php:453`;
  `bin/magento module:status` liệt kê Secomm_Ghn ở nhóm enabled.

**Pending environment (cần DB up):** chạy `setup:di:compile` đầy đủ; mở admin confirm section
`Stores → Configuration → Secomm → GHN Shipping`; smoke call sandbox `master-data/provinces` qua
client (cần api_token/shop_id sandbox cấu hình trước) — để lại cho QC/TL khi DB khả dụng.

## 4. Token hygiene (AC-A4)

- `TokenScrubTest::testSensitiveKeysAreScrubbed` — mọi key chứa `token`/`shopid`/`authorization`
  bị thay `[scrubbed]`, đệ quy 1 cấp; `shop_id` operation context giữ nguyên (SPEC §48 yêu cầu log
  shop_id).
- `GhnApiClientTest::testTokenNeverAppearsInAnyLogRecordOnFailurePath` — token không xuất hiện trong
  record log lẫn exception message trên failure path.
- `GhnApiClientTest::testSuccessfulCallWritesSingleAuditLineWithoutToken` — đúng 1 dòng context
  `operation, shop_id, http_status, provider_code, duration_ms` mỗi call.

## 5. Validators

Baseline trước thay đổi: check-specs 12 FAIL / check-records 2 FAIL / check-identity 12 FAIL
(tất cả pre-existing, không liên quan). Sau khi thêm SPEC + FEAT + DEC + 6 TASK records + plan:

- check-records: 2 FAIL (đúng baseline — 0 mới).
- check-specs / check-identity: 0 FAIL trỏ tới `FEAT-FQWEQ3`, `SPEC-FEAT-FQWEQ3`,
  `TASK-RJFTPZ/MZ2TCB/FMBBSD/9Q5ZAK/RR1ZFN/8019VC`, `DEC-FEATFQWEQ3-001`.
- (Các FAIL tăng thêm giữa baseline và lần chạy sau thuộc working file của stream song song:
  `SPEC-TASK-7AJ3K8-*`, `TASK-7AJ3K8`, BUG-* — không phải artifacts của FEAT này.)

## 6. Bugs tự phát hiện + đã sửa trong quá trình code

1. `Config::getEnvironment()` — biểu thức precedence sai (đã viết lại `isset()` guard) — bắt được
   khi review lại chính code trước khi test.
2. `GhnApiClient` thiếu `use Secomm\Ghn\Api\Client\GhnApiClientInterface` — PHP resolve interface
   theo namespace hiện tại (`Model\Client`) → "interface not found" lúc compile; bắt được qua
   class-load check, sửa bằng thêm import.
3. `GhnLogger` needle `shop_id_header` không match key `ShopId_header` (needle dài hơn haystack) —
   đổi SENSITIVE_KEYS thành fragments `['token','shopid','authorization']` + test fix.

## 7. Spec/record artifacts

- `.ai/specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md` (FULL)
- `.ai/records/features/FEAT-FQWEQ3.md` + 6 task records (RJFTPZ in_progress; còn lại proposed)
- `.ai/records/decisions/DEC-FEATFQWEQ3-001.md` + DECISIONS.md index
- `.ai/plans/TASK-RJFTPZ-implementation-plan.md` (dòng `| Specification |` đầy đủ)
- SPIKE-9Z231Q §16 update note; CURRENT_STATE.md entry mới
