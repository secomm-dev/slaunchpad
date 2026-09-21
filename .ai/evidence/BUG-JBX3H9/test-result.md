# Evidence — BUG-JBX3H9 (GHN hardcoded destination fallback removed)

Ngày: 2026-09-08 · Mode C · pre-review evidence cho TL review.

## Audit trước fix (occurrences tìm thấy — đầy đủ hơn prompt)

| # | Vị trí | Giá trị fake | Side |
|---|--------|--------------|------|
| 1 | `AbstractDataBuilder::resolveGhnLocation` catch + missing-input branch | `1456` / `'21511'` | destination |
| 2 | `ServicesDataBuilder::build` develop branch | `from=1457` + `to=1456` | from + destination |
| 3 | `ShippingDetailsDataBuilder::build` develop branch | `from=1457` / `fromWard='21715'` (override cả origin map ĐƯỢC) | from |
| 4 | `SynchronizeOrderDataBuilder::build` develop branch | `'Phường 17'`/`'Quận Phú Nhuận'`/`'Hồ Chí Minh'` | from |
| 5 | `etc/config.xml:26` | `is_develop_mode` default **1** (bật fallback ngầm) | flag |

LocationResolver (`GhnAddressMapper`) KHÔNG chứa fallback — nó throw `NoSuchEntityException` đúng
chức năng; behavior mapper giữ nguyên (E-C mới migrate canonical).

## Grep sau fix

```text
$ grep -rn "1456|21511|Phường 17|1457|21715|is_develop_mode" app/code/Secomm/GiaoHangNhanh/ app/code/Secomm/GhnAddressMapper/
→ production code (*.php Model/, Api/, etc/): 0 hit
→ Test/Unit (2 hits, legitimate): string literal 'is_develop_mode' => 1 trong test là CỐ Ý —
   chứng minh stale config value không thể re-activate fallback (AC-2/AC-5)
→ README.md (1 hit): Known-Issues #2 ghi nhận fix (stale doc đã cập nhật)
```

## Command output

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "GiaoHangNhanh"
OK — 9 tests (AbstractDataBuilderTest 7 + ServicesDataBuilderTest 2)

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml
Tests: 846, Assertions: 3163, Errors: 7, Failures: 0
→ cả 7 errors pre-existing ở Secomm_Tracking\EventNormalizerTest (ArgumentCountError
  Magento\Sales\Model\Order\Item::__construct — test debt cũ); verified tồn tại ở clean HEAD:
  git stash -u → 818 tests / 7 errors (giống hệt). 0 failure mới.
  (Lưu ý: 2 lần đọc "15 errors" trước đó trong session là generated/code stale — sau
  setup:di:compile baseline clean HEAD = 7 errors.)

$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.
(fatal "Cannot redeclare __construct" ở lần compile giữa đã fix — duplicate constructor sót lại)

$ bash .ai/bin/project-ai-validate --check-records --check-specs --check-identity
→ 0 finding trên BUG-JBX3H9 (15 FAIL còn lại = backlog record cũ, xem evidence TASK-AQT7V3)
```

## Scope confirmation

Files changed: `GiaoHangNhanh/Model/Service/Request/{AbstractDataBuilder,ServicesDataBuilder,
ShippingDetailsDataBuilder,SynchronizeOrderDataBuilder}.php`, `Model/Exception/
GhnLocationMappingException.php` (mới), `etc/config.xml`, `etc/adminhtml/system.xml`,
`Test/Unit/Model/Service/Request/*` (mới ×2), `README.md`, `CHANGELOG.md` (1.2.0).
KHÔNG đụng: GhnAddressMapper code, Secomm_ShippingCore, Ghtk, Ahamove, VietNamAddress, LocationResolver.
Ghi chú scope: vì `is_develop_mode` sau khi xóa branches không còn reader nào (grep toàn app/),
field bị XÓA SẠCH thay vì chỉ default về 0 — đúng hướng DEC-004 (stronger form); flag không thể
re-activate fake data dưới mọi giá trị (test chứng minh). Stale `core_config_data` rows cho path cũ
= orphan harmless (Out of Scope dọn DB).

Khuyến nghị QC (ngoài phạm vi unit): sau khi bật GHN trên store thật, verify 1 địa chỉ KHÔNG có
mapping → GHN method biến mất khỏi checkout + log có `[GHN Location Mapping]` warning; 1 order
test → sync fail rõ ràng (direct mode: error message admin; async: MQ retry + log).
