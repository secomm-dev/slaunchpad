# Kế hoạch triển khai: TASK-Y6SGHE — MoMo payment-attempt DI binding (MOMO-01-HF1)

| Field | Value |
|---|---|
| Specification | Full Spec — [SPEC-TASK-Y6SGHE-momo-payment-attempt-di-binding.md](../specs/SPEC-TASK-Y6SGHE-momo-payment-attempt-di-binding.md) (VALID) |
| Record | [TASK-Y6SGHE](../records/tasks/TASK-Y6SGHE.md) · Issue github:thanhle74/slaunchpad#13 |
| Workflow mode | A (payment/order path → high-risk) |
| Risk | high (generic placeOrder surface) — nhưng mutation tối tiểu: 1 preference + 1 test |
| Date | 2026-09-21 · RUN_ID RUN-20260921-TASKY6SGHE-e11bca · BASE `88050d2d` |

## 1. Hướng tiếp cận

Root cause đã verify tại BASE: plugin `momo_place_order_guard` đăng ký global trên
`Magento\Quote\Model\QuoteManagement` nên Magento instantiate plugin ở lần
interception `placeOrder` đầu tiên của **mọi** payment method; constructor của plugin
inject `PaymentAttemptRepositoryInterface` mà `etc/di.xml` không có preference →
runtime fatal cho mọi path, kể cả ZaloPay (non-MoMo). Các validation trước của
MOMO-01 không bắt được vì `setup:di:compile` không kiểm tra preference của
constructor injection (runtime-only), và CLI smoke không chạm placeOrder.

Các bước:

1. **di.xml** — thêm 1 preference `PaymentAttemptRepositoryInterface` →
   `Secomm\MoMo\Model\PaymentAttemptRepository` (đặt cạnh block preference MOMO-02,
   comment dẫn issue #13 + liệt kê 6 consumer). Không thêm binding khác — spec §3
   đã xác nhận `Api\Data\PaymentAttemptInterface` không có ObjectManager-created path.
2. **Regression test** `Test/Unit/Di/PaymentAttemptDiBindingTest.php`:
   - parse `etc/di.xml` (simplexml) → preference tồn tại, trỏ đúng class, class
     `implements` interface (bắt đúng regression "preference bị xoá/sai");
   - dựng `CartManagementPlaceOrderGuard` **thật** với `PaymentAttemptRepository`
     **thật** (full constructor graph, mocks chỉ ở I/O boundary: ObjectManagerInterface,
     ResourceConnection…) → `beforePlaceOrder` non-MoMo quote → null (no-op chứng minh
     AC3); hành vi MoMo đã do `CartManagementPlaceOrderGuardTest` phủ (giữ nguyên PASS — AC4).
3. **Validation** (throwaway env `/tmp/m2r` + container `m2r-php`): php -l → PHPCS
   Magento2 severity ≥ 6 → phpunit `phpunit-secomm.xml` full Secomm suite → xoá
   disposable generated + `setup:di:compile` → runtime DI smoke (resolve interface +
   instantiate guard từ ObjectManager global config) → `.ai/bin/project-ai-validate`.
4. **Evidence + handoff**: `.ai/evidence/TASK-Y6SGHE/`, CHANGELOG 2.2.1, record status
   `ready_for_review`, commit, non-force push task branch lên GitHub (origin), comment
   READY_FOR_REVIEW với BASE/TIP SHA + validation. Không self-ACCEPT/merge.

### Alternatives đã loại

- Preference cho `Api\Data\PaymentAttemptInterface` — speculative (scope #13.3:
  chỉ thêm nếu runtime cần; đã grep — không path nào resolve interface này).
- Proxy cho repository binding — không có yêu cầu nào từ source (repository nhẹ,
  constructor không I/O); thêm proxy là speculative optimization.
- Sửa plugin thành lazy-resolve (ObjectManager trong method) — vi phạm [BLOCK]
  "No direct ObjectManager usage" và redesign ngoài NON_SCOPE.
- Integration/DB-backed ZaloPay place-order test — env throwaway không có DB stack
  cho ZaloPay; issue cho phép "closest deterministic DI construction path" → unit
  DI-construction test + runtime OM smoke.
