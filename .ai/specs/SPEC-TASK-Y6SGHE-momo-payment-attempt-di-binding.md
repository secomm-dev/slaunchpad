# SPEC-TASK-Y6SGHE — MoMo payment-attempt DI binding (MOMO-01-HF1)

Specification ID: SPEC-TASK-Y6SGHE

- Issue: github:thanhle74/slaunchpad#13
- Lane: MoMo · Epic #1 · Depends on #3 (DONE, merged in base `88050d2d`)
- Mode: A (spec → record → dev → pre-review → TL review)
- Risk: high (payment/order path — generic placeOrder surface)
- Branch: `thanhle74/13-momo-momo-01-hf1-fix-missing-payment-attempt`
- RUN_ID: RUN-20260921-TASKY6SGHE-e11bca

## 1. Goal

Khôi phục DI hợp lệ cho payment-attempt path của `Secomm_MoMo` sao cho **mọi order
path — MoMo lẫn non-MoMo (ZaloPay, check/mo, …) — không còn fatal
`Cannot instantiate interface Secomm\MoMo\Api\PaymentAttemptRepositoryInterface`**
khi Magento khởi tạo plugin `Secomm\MoMo\Plugin\Quote\CartManagementPlaceOrderGuard`
(được đăng ký global trên `Magento\Quote\Model\QuoteManagement`), đồng thời giữ
nguyên hành vi guard: non-MoMo no-op, MoMo quote bị chặn trừ khi có grant hợp lệ.

## 2. Failure analysis (verified at BASE `88050d2d`)

- Guard plugin đăng ký global trong `etc/di.xml` trên `Magento\Quote\Model\QuoteManagement`
  (`momo_place_order_guard`, sortOrder 10) → ObjectManager khởi tạo plugin ở **lần
  interception đầu tiên của `placeOrder` trong mọi area** (frontend, webapi_rest,
  graphql, webapi_soap) — bất kể quote là method nào.
- Constructor của guard inject 5 dependency: `CartRepositoryInterface` (core pref ✓),
  `PaymentAttemptRepositoryInterface` (**thiếu preference — lỗi**), `MethodInterface`
  (bound qua argument virtualType `MoMoFacade` ✓), `OrderPlacementAuthorization`
  (concrete ✓), `LoggerInterface` (core pref ✓).
- `Secomm\MoMo\Model\PaymentAttemptRepository` implements interface và là concrete
  duy nhất — nhưng `etc/di.xml` **không có `<preference>`** cho interface này.
- Impact surface lớn hơn report: ngoài guard, **5 service nữa** constructor-inject
  cùng interface — `PaymentAttemptManagement`, `IpnProcessor`, `OrderFinalizer`,
  `PaymentAttemptLifecycle`, `ReturnProcessor` — tất cả đều unresolvable từ
  ObjectManager kể từ merge MOMO-01 (không lộ ra vì các validation trước chỉ chạy
  CLI/di:compile, không chạm runtime instantiation của các class này).
- Dependency graph của `PaymentAttemptRepository` (construct eagerly khi resolve):
  `PaymentAttemptFactory` (inject `ObjectManagerInterface` ✓), `PaymentAttemptCollectionFactory`
  (inject `ObjectManagerInterface` ✓), `ResourceConnection` (core ✓), `DateTime` (core ✓),
  `PaymentAttemptResource` (extends core `AbstractDb`, không custom constructor ✓).

## 3. Design decisions

1. **Một preference duy nhất** `PaymentAttemptRepositoryInterface` →
   `Secomm\MoMo\Model\PaymentAttemptRepository` trong `etc/di.xml` (global), mirror
   đúng pattern của cặp preference MOMO-02 (`RefundRequest*`). Không thêm binding
   spec nào khác (không proxy, không virtualType — không có yêu cầu từ source/runtime).
2. **`Api\Data\PaymentAttemptInterface` KHÔNG cần preference** (scope item #13.3):
   grep toàn module — không class nào được ObjectManager tạo qua tên interface này
   (không constructor injection, không `ObjectManager->create(PaymentAttemptInterface::class)`,
   không `PaymentAttemptInterfaceFactory`). Entity được tạo qua `PaymentAttemptFactory`
   (hand-written, `objectManager->create(PaymentAttempt::class)` — concrete) và trả về
   qua return type của repository. Preference cho interface entity sẽ là binding
   speculative — không thêm.
3. **Regression coverage ở mức DI construction** (AC5): unit test mới
   `Test/Unit/Di/PaymentAttemptDiBindingTest.php` — (a) parse `etc/di.xml` khẳng định
   preference tồn tại và trỏ đúng class implements interface (bắt đúng failure mode
   "ai đó xoá preference"); (b) dựng instance guard **thật** với repository **thật**
   (đúng graph DI) và chứng minh non-MoMo quote là no-op; hành vi guard MoMo đã có
   `CartManagementPlaceOrderGuardTest` phủ (blocked/grant paths) — giữ nguyên chạy lại.
4. **Không đổi code nào khác** — không sửa plugin, không sửa service, không đụng
   ZaloPay (NON_SCOPE của #13), không DI cleanup ngoài scope.

## 4. Components

- `app/code/Secomm/MoMo/etc/di.xml` — thêm preference (kèm comment dẫn issue #13).
- `app/code/Secomm/MoMo/Test/Unit/Di/PaymentAttemptDiBindingTest.php` — test mới.
- `app/code/Secomm/MoMo/CHANGELOG.md` — entry HF1.
- `.ai/` artifacts: record `TASK-Y6SGHE`, spec này, plan, evidence.

## 5. Flows

```
placeOrder (bất kỳ method) → QuoteManagement interceptor
  → ObjectManager instantiate CartManagementPlaceOrderGuard
    → constructor resolve:
       CartRepositoryInterface        → core preference
       PaymentAttemptRepositoryInterface → NEW preference → PaymentAttemptRepository
                                           → factories/resource: core + concrete ✓
       MethodInterface                → MoMoFacade virtualType argument
       OrderPlacementAuthorization    → concrete (no-arg deps)
       LoggerInterface                → core preference
  → beforePlaceOrder:
       non-MoMo quote → return null (Magento behaviour giữ nguyên)
       MoMo quote     → grant peek → persist-backed triple check → consume / block
```

## 6. Assumption log (for TL review)

- Không có integration/DB-backed test cho ZaloPay place-order trong env này;
  AC3 được chứng minh bằng: OM/DI runtime smoke (container m2r-php, instantiate
  guard + resolve interface từ clean DI) + unit no-op test với repository thật.
  Đây là "closest deterministic DI construction path" theo wording của issue.
- `setup:di:compile` KHÔNG phát hiện missing preference (runtime-only failure) —
  vì vậy compile clean là necessary-but-not-sufficient; smoke + unit test là
  bằng chứng chính.

## 7. Validation plan

- `php -l` changed PHP files (trong container m2r-php, PHP 8.3).
- PHPCS Magento2 (severity ≥ 6) trên changed files.
- `phpunit -c dev/tests/unit/phpunit-secomm.xml` — full Secomm suite (regression)
  + focused filter Secomm\MoMo.
- Clean DI state: xoá disposable generated artifacts trong validation copy
  (`/tmp/m2r` — throwaway env), `bin/magento setup:di:compile` EXIT=0.
- Runtime DI smoke: script bootstrap Magento CLI, `$om->get(PaymentAttemptRepositoryInterface)`
  → instanceof `PaymentAttemptRepository`; `$om->create(CartManagementPlaceOrderGuard)`
  → thành công từ config global (chứng minh AC1 + AC3 instantiation).
- Validator framework: `.ai/bin/project-ai-validate` (specs/records/identity).
