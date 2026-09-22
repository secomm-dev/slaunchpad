# Task Spec: ShippingCore — COD payment identification (architecture v4 §4.1 delta)

Specification ID: SPEC-TASK-STC3NB

> Filename: `SPEC-TASK-STC3NB-shippingcore-cod-payment-identification.md`. Delta task từ audit
> ShippingCore vs architecture doc v4 — implementation của đúng MỘT gap được directive yêu cầu
> implement ngay; các gap PARTIAL khác (per-operation capability, resolution snapshot) REPORT +
> defer theo governance (material contract change / DB Tier-2, chạm carrier modules).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-STC3NB |
| Feature ID | FEAT-YA2C0W (parent — chuỗi ShippingCore) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ audit directive; architecture basis = address-shipping.md **Revision v4 §4.1** (đã TL/SA amend) |
| Status | **VALID** — contract shape scalar-in/bool-out theo architecture v4 §4.1 nguyên văn |
| Date | 2026-09-11 |
| Related Ticket(s) | TASK-STC3NB · TASK-M3ME32 (foundation) · TASK-NQT782 (bridge consumer pattern) |
| Workflow Mode | A (shipping shared-contract) |

## 1. Objective

Implement COD payment identification trong `Secomm_ShippingCore` (architecture v4 §4.1):

```text
Secomm\ShippingCore\Api\Cod\CodPaymentMethodResolverInterface

isCod(string $paymentMethodCode): bool
```

ShippingCore own: configuration khai báo Magento payment method codes được xem là COD +
provider-neutral resolver. Carrier (GHN/GHTK) sau này consume: `order.getPayment().getMethod()`
→ `isCod(...)` → map provider COD fields. **Identification thuần** — không COD framework.

## 2. Audit result (basis — full matrix ở final report)

MATCH: rate outcome 3-state + status/reason separation · service-level dynamic registry (taxonomy
chỉ ở docblock examples) · fallback policy/provider/pool · handoff boundary (0 provider-field
leak) · external resolver seam (0 invocation) · dependency graph sạch.
PARTIAL (DEFER, không code trong task): capability per-operation + representations (interface
đang per-carrier; implementers ngoài ShippingCore: GhnAddressCapability, GhtkAddressCapability —
đổi signature sẽ phá carrier modules) · CanonicalResolutionSnapshot persistence (DB Tier-2).
MISSING: COD identification → implement task này.

## 3. Scope

### 3.1 `Api\Cod\CodPaymentMethodResolverInterface`

```php
public function isCod(string $paymentMethodCode): bool;
```

### 3.2 `Model\Cod\ConfiguredCodPaymentMethodResolver`

* Config: system path `secomm_shippingcore/cod/payment_methods` — comma-separated codes
  (một hoặc nhiều), đọc default scope (Launchpad single store-group; store-scoping là upgrade
  path có thể thêm sau mà không đổi contract).
* Normalization: config split `,` → trim từng code → filter rỗng; query code trim; compare
  **exact** (case-sensitive `in_array` strict) — prefix/similar → false.
* Semantics: empty/malformed/null config → `false`; unconfigured code → `false`; không default
  COD method; không hardcode Magento COD implementation.
* `final` + `readonly` theo convention module.

### 3.3 Admin config

`etc/adminhtml/system.xml`: section `secomm_shippingcore` → group `cod` → field `payment_methods`
( text, comma-separated; comment hướng dẫn). `etc/config.xml` default rỗng (safe false).
ACL: `Magento_Backend::stores` (mirror Launchpad bridge convention).

### 3.4 DI

Preference `CodPaymentMethodResolverInterface` → `ConfiguredCodPaymentMethodResolver`.

## 4. Out of scope (cứng — directive §"Không implement")

`Secomm_Cod` · eligibility engine · COD amount resolver / `getCollectAmount` abstraction ·
surcharge · min/max · risk scoring · OTP · reconciliation · settlement · payment visibility
orchestration · service-level COD eligibility · partial payment/deposit · capability per-operation
refactor (deferred — report) · snapshot persistence (deferred — report) · carrier module changes.

## 5. Acceptance Criteria

* **AC-1**: Contract `isCod(string): bool` đúng namespace `Api\Cod`; DI preference.
* **AC-2**: Config semantics: configured (một/nhiều) → true tương ứng; unconfigured → false;
  empty/null/malformed → false; prefix/similar → false; whitespace normalize deterministic.
* **AC-3**: 0 hardcode COD implementation; không OrderInterface trong COD contract.
* **AC-4**: system.xml + config.xml default; compile + validator 0 new finding.
* **AC-5**: Tests §6 pass; regression ShippingCore suite pass (0 new fail).
* **AC-6**: README/CHANGELOG ShippingCore update (COD identification) + working memory sync.

## 6. Test plan (unit, AAA — mock ScopeConfig)

configured single → true · configured multi → từng code match đúng · unconfigured → false ·
empty config → false · null config → false · prefix/similar (`cashondel`, `CASHONDELIVERY`)
→ false · whitespace quanh dấu phẩy (`" a , b "`) → deterministic true cho `a`/`b` ·
query code whitespace → trimmed match.
