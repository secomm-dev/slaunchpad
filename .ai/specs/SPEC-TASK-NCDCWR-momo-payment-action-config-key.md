# SPEC-TASK-NCDCWR — MoMo payment-action config key alignment (MOMO-05)

Specification ID: SPEC-TASK-NCDCWR
Specification Level: FULL

- Issue: github:thanhle74/slaunchpad#17
- Lane: MoMo · Epic #1 · Depends on #16 (DONE — MOMO-04 integrated @ `ad6e2d7f`)
- Mode: A (payment capture configuration → high-risk, Tier 2 — Owner authorization on issue #17: `TASK_STATUS = READY_TO_START`, `NEXT_ACTOR = IMPLEMENTER`)
- Risk: high (Tier-2 payment capture configuration)
- Branch/BASE/RUN_ID: `thanhle74/momo-momo-05-align-payment-action-config` @ `ad6e2d7fe32bf71188deff49e666cc2643089b8b` · RUN-20260921-TASKNCDCWR-ad6e2d · host thanhle-aloha

## 1. Goal

Xoá key drift giữa admin-configured Payment Action và runtime value mà
payment/finalization path tiêu thụ: admin persistence và runtime read dùng
MỘT canonical key duy nhất — `payment/momo_payment/payment_action` (chuẩn
Magento), giữ nguyên default semantics (`authorize_capture`), không đụng
payment-first lifecycle, provider API contract, hay refund/recovery
classification.

## 2. Current state (verified at BASE `ad6e2d7f`)

Enumeration đầy đủ (exhaustive source search trên worktree @ BASE — mọi
`.php`/`.xml` trong `app/` + `vendor/`):

| # | Vị trí | Key | Vai trò |
|---|--------|-----|---------|
| 1 | `etc/adminhtml/system.xml:46` | `momo_payment_action` | Admin field — writer duy nhất của legacy key `payment/momo_payment/momo_payment_action`; ZERO readers. |
| 2 | `etc/config.xml:21` | `payment_action` | Default `authorize_capture` — canonical key, đã đúng. |
| 3 | `Service/OrderFinalizer.php:526` | `payment_action` (raw literal) | Runtime read duy nhất của module — gate local capture sau verify. |
| 4 | `Model/Config.php:46` | `momo_payment_action` | `KEY_PAYMENT_ACTION` — dead constant (no getter, no usage), mã hoá key sai. |
| 5 | `Model/Adminhtml/Source/PaymentAction.php` | — | Source model của select — chỉ có option `authorize_capture` → mọi UI-saved legacy value chỉ có thể là `authorize_capture`. |
| 6 | `OrderFinalizerTest.php:199` | `payment_action` | Test mock raw literal `'payment_action'` — trùng canonical key → assertions giữ nguyên sau fix. |
| 7 | Core consumer (không sửa): `Order\Payment::place()` (`Order/Payment.php:371–377`) | `payment_action` | Đọc `getConfigPaymentAction()` qua facade (`MoMoFacade` → `MoMoValueHandlerPool` → `MoMoConfig` virtualType `Magento\Payment\Gateway\Config\Config` methodCode `momo_payment` → path `payment/<methodCode>/<key>`); vì `isInitializeNeeded()` (`can_initialize=1`), core truyền `getConfigData('payment_action')` vào `InitializeCommand` (command bỏ qua giá trị truyền vào — luôn set `pending_payment`). Reader này unchanged bởi fix. |
| 8 | MOMO-03 spec `SPEC-TASK-4ZW0WG.md:168` | — | Đã ghi nhận drift là known issue → task này fix. |

## 2.1 Validated runtime read path

`Magento\Payment\Gateway\Config\Config::getValue($key)` → scope config
`payment/momo_payment/<key>`. di.xml: `MoMoConfig` virtualType methodCode
`momo_payment`; `OrderFinalizer` inject `Magento\Payment\Gateway\ConfigInterface`
(bound `MoMoConfig` virtualType trong di.xml
`<type name="Secomm\MoMo\Service\OrderFinalizer">`).

## 2.2 Compatibility proof (AC4)

- Legacy key có ZERO runtime readers — tham chiếu duy nhất là dead constant
  `Model\Config::KEY_PAYMENT_ACTION` (no getter, no usage). Repo-wide grep
  (app/ + .ai/, excluding evidence): 3 hits (system.xml, Model/Config.php,
  MOMO-03 spec mention).
- Source model single-option `authorize_capture` → mọi UI-saved legacy value
  chỉ có thể là `authorize_capture` — trùng hiệu ứng default đang chạy.
- Canonical key chưa từng writable qua UI → runtime của mọi install hiện có
  đã luôn chạy default `authorize_capture`; sau fix runtime vẫn đọc đúng key
  đó → **behavior provably unchanged cho mọi install hiện có**.
- Row legacy (nếu có) = inert data (no readers). Admin re-save qua field mới
  bắt đầu có hiệu lực thật. Ops optional check (spec §6).

## 3. Design decisions

1. **Canonical key = `payment_action`** dưới `payment/momo_payment/` — chuẩn
   Magento (`Order\Payment::place()` + `Adapter::getConfigPaymentAction()`
   đọc key này); config.xml default + OrderFinalizer read đã đứng đúng key →
   chỉ cần đổi PHẦN VIẾT (admin field) + dọn dead constant.
2. **system.xml field id `momo_payment_action` → `payment_action`** — admin
   save path ghi thẳng canonical key. Label/source model/sortOrder/showIn*
   giữ nguyên (không thêm `canRestore` — ngoài scope).
3. **`Model\Config::KEY_PAYMENT_ACTION = 'payment_action'`** — sửa giá trị
   constant + làm code-level single source: `OrderFinalizer` reference
   constant thay raw literal `'payment_action'`. Không thêm getter mới trên
   `Model\Config` — OrderFinalizer inject gateway `ConfigInterface`, giữ
   nguyên DI, không mở API surface.
4. **Compatibility (AC4) — KHÔNG fallback, KHÔNG migration** (chứng minh ở
   §2.2, không phải giả định). Row legacy inert; tài liệu hoá README +
   CHANGELOG.
5. **Source model `PaymentAction` giữ nguyên** — single option
   `authorize_capture` là production contract (MOMO-01); mở rộng option =
   scope mới.
6. **Không đụng**: payment-first lifecycle, IPN/Return classification
   (MOMO-03/04), refund (MOMO-02), schema, provider API contract, ZaloPay.

## 4. Components

| File | Thay đổi |
|---|---|
| `etc/adminhtml/system.xml` | field id `momo_payment_action` → `payment_action` (chỉ id; còn lại giữ nguyên) |
| `Model/Config.php` | `KEY_PAYMENT_ACTION = 'payment_action'` |
| `Service/OrderFinalizer.php` | `captureOrder()` dùng `Config::KEY_PAYMENT_ACTION` thay raw literal; import `Secomm\MoMo\Model\Config`; docblock ghi contract key |
| `Test/Unit/Service/OrderFinalizerTest.php` | +2 tests: non-capturing action → finalize WITHOUT capture; null payment_action → finalize WITHOUT capture. Existing mocks giữ nguyên (mock string trùng constant) |
| `README.md` | bảng Config: thêm dòng Payment Action + legacy-key note |
| `CHANGELOG.md` | entry `[2.3.2]` MOMO-05 |

## 5. Flow (sau thay đổi)

```
Admin save (Payment Action) → core_config_data `payment/momo_payment/payment_action`
                                        │
           ┌────────────────────────────┴────────────────┐
           ▼                                             ▼
OrderFinalizer::captureOrder                  Order\Payment::place() (core)
  Config::KEY_PAYMENT_ACTION                    Adapter::getConfigPaymentAction()
  === ACTION_AUTHORIZE_CAPTURE                  (cùng key, qua ValueHandlerPool)
    → capture() hoặc không                → truyền vào InitializeCommand (ignores)
```

## 6. Compatibility & migration

Không cần migration/fallback — chứng minh ở §2.2. Row legacy
`payment/momo_payment/momo_payment_action` (nếu có trong `core_config_data`)
là inert: không code path nào đọc nó sau fix và chưa từng có reader nào
TRƯỚC fix. Ops optional check sau deploy:

```sql
SELECT * FROM core_config_data WHERE path = 'payment/momo_payment/momo_payment_action';
```

Row nếu có → vô hại (có thể xoá thủ công).

## 7. Test plan

- **Existing regression**: `OrderFinalizerTest` giữ nguyên toàn bộ assertion
  (mock `'payment_action'` trùng constant mới).
  `testPlacesOrderFromVerifiedQuote` (`authorize_capture` → `capture()` once)
  = AC3 default behavior.
- **AC5 focused tests (mới)**:
  - non-capturing action (`not_authorize_capture`) → `capture()` never, order
    vẫn place/finalize/saved, attempt FINALIZED, commit.
  - null `payment_action` → `capture()` never, vẫn finalize + commit
    (strict-comparison: chỉ `authorize_capture` mới capture).
- **XML validity**: `xmllint --noout` trên 2 file XML đã sửa.
- **Full gate (AC6)**: PHPUnit full `Secomm\MoMo` + PHPCS Magento2 severity
  ≥6 (changed files) + `setup:di:compile` (container m2r-php, throwaway
  /tmp/m2r) — baseline MOMO-04: 253 tests / 746 assertions, 5 pre-existing
  PHPUnit deprecations.
- `bin/project-ai-validate --check-specs --check-identity --check-records` —
  0 finding cho TASK-NCDCWR; FAIL set = pre-existing baseline (không tăng).

## 8. Non-scope

MOMO-04 ReturnProcessor classification; MoMo provider API result-code
semantics; refund redesign; schema/data migration; source-model option
expansion; ZaloPay; Bitbucket sync.

## 9. Acceptance Criteria

- AC1: một canonical Payment Action config key được tài liệu hoá và dùng
  nhất quán (`payment/momo_payment/payment_action`; constant
  `Model\Config::KEY_PAYMENT_ACTION`).
- AC2: admin save path (system.xml field `payment_action`) và runtime read
  (`OrderFinalizer`, core placement) cùng key.
- AC3: default behavior (`authorize_capture` → capture) explicit + tested.
- AC4: installation có legacy saved value không đổi behavior — chứng minh
  zero-reader + single-option (§2.2); quyết định no-migration được tài liệu
  hoá.
- AC5: `OrderFinalizer`/capture behavior có focused tests (2 test mới).
- AC6: full `Secomm\MoMo` unit suite + PHPCS + DI compile pass.
