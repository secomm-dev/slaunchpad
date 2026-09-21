# Evidence — TASK-STC3NB (COD payment identification + ShippingCore audit vs architecture v4)

Ngày: 2026-09-11 · Mode A · pre-review evidence cho TL review.

## Audit vs address-shipping.md Revision v4 (compliance matrix)

| # | Contract (architecture) | Trạng thái code | Verdict |
|---|---|---|---|
| 1 | Capability per-operation (`requiredScheme(op)`, `supportedRepresentations(op)`, `supportsTextualFallback(op)`; RATE+CREATE; UNIT_ID/TEXT_NAME) | `CarrierAddressCapabilityInterface` đang **per-carrier** (`getRequiredScheme()`, `supportsTextualFallback()`, không op/representations). Implementers NGOÀI ShippingCore: `Secomm_Ghn\Model\Capability\GhnAddressCapability`, `Secomm_Ghtk\Model\Address\GhtkAddressCapability` (+test) | **PARTIAL — DEFER**: đổi signature phá carrier modules (cấm modify trong task) → material contract change, cần spec-first task riêng (Tier-2, phối hợp GHN-B/GHTK adapter) |
| 2 | CanonicalResolutionSnapshot (canonical_2025/resolved_pre2025/status/failure_class/source/provenance; chỉ canonical codes) | Chưa có persistence nào (grep 0 hit); §5.1 để DB decision cho implementation;涉及 quote/order storage = DB migration Tier-2 | **MISSING — DEFER**: cần spec-first task riêng (schema + storage decision) |
| 3 | Failure semantics (RESOLVED/AMBIGUOUS→UNAVAILABLE/UNMAPPED→UNAVAILABLE/TECHNICAL→TECHNICAL_FAILURE) | Manager map 1-1 4-status; E-C1 outcome 3-state; **0 lần đọc reason** trong E-SL1/E-SL2 (grep) | **MATCH** (ShippingCore layer; mapping failure_class→outcome là việc carrier adapter lúc translate) |
| 4 | External resolver boundary (AMBIGUOUS only, selector, không mint/unmapped/fan-out) | Pool seam tồn tại, **0 invocation** trong orchestration, không resolver provider, không UNMAPPED path | **MATCH** (seam đúng, chưa có consumer — đúng hướng "không cần implement nếu chưa có") |
| 5 | Handoff boundary (không build GHN district_id/ward_code/is_new_to_address/GHTK payload) | grep provider fields trong ShippingCore = **0 hit**; TASK-7AJ3K8 đã thêm `handoffContext()` + AMBIGUOUS candidates passthrough | **MATCH** |
| 6 | Rate semantics (SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE; status vs reason) | E-C1 contracts + VO invariants + `ShippingFailureReason` owner (r1) | **MATCH** |
| 7 | Service-level/fallback (registry dynamic, policy, pool, decision; không hardcode taxonomy; không Mageplaza/Launchpad; không ranking; fallback gating theo STATUS) | Đủ contracts; taxonomy chỉ docblock examples ("e.g."); 0 reason inspection; 0 Mageplaza/Launchpad dep (hit duy nhất = docblock example trong `CarrierApiProfileInterface:19` — không phải use statement); fallback gating theo hasSuccessfulRate/hasTechnicalFailure | **MATCH** |
| 8 | COD payment identification (§4.1) | Trước: **MISSING** (0 hit). Sau task: implemented (§ Code) | **MISSING → IMPLEMENTED** |

## COD implementation

```text
Api\Cod\CodPaymentMethodResolverInterface        isCod(string $paymentMethodCode): bool
Model\Cod\ConfiguredCodPaymentMethodResolver     config `secomm_shippingcore/cod/payment_methods`
                                                 (comma-separated) → trim từng code → filter rỗng →
                                                 exact strict in_array; query code trim;
                                                 empty/null/malformed → false; 0 default COD method
etc/adminhtml/system.xml                         section secomm_shippingcore → cod → payment_methods
etc/config.xml                                   default rỗng (safe false)
etc/di.xml                                       preference interface → resolver
```

Scope: identification thuần — grep `CollectAmount|getCollectAmount|surcharge|Secomm_Cod|OrderInterface`
trong Api/Cod + Model/Cod = **1 hit** (docblock dòng 21: "never expresses COD eligibility, amounts,
surcharges" — negative statement, không phải code). Không OrderInterface trong contract.

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ConfiguredCodPaymentMethodResolver"
OK — 8 tests, 14 assertions, 0 failure/error

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "ShippingCore|VietNamAddress|MageplazaTableRate"
OK — 388 tests, 1078 assertions, 0 failure/error (regression 0 new fail)

$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.
```

Cases: single configured → true · multi → từng code · unconfigured → false · empty → false ·
null → false · prefix (`cashondel`) / suffix (`cashondelivery_extra`) / case (`CASHONDELIVERY`)
→ false · config whitespace normalize deterministic · queried code trimmed.
Tự-fix trong review: assertion `isCod('custom_cod ')` sai kỳ vọng (contract trim queried code —
đúng thiết kế) → đổi sang `custom_codx` (junk suffix không match).

## Validator + dependency audit

```text
$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 29 FAIL, 0 WARN — 0 finding TASK-STC3NB. 3 FAIL mới (TASK-7AJ3K8 stale H1,
TASK-TBM30R stale H1, SPEC-TASK-7AJ3K8 naming) + BUG records = các stream song song
(GHTK alignment / GHN-B2), ngoài scope task này.

$ grep forbidden deps trong ShippingCore (trừ docblock example CarrierApiProfileInterface:19)
  → 0 hit. $ grep Mageplaza vendor/ → 0 edit.
```

## Files changed

- `Api/Cod/CodPaymentMethodResolverInterface.php`, `Model/Cod/ConfiguredCodPaymentMethodResolver.php` (mới)
- `etc/adminhtml/system.xml`, `etc/config.xml` (mới), `etc/di.xml` (1 preference)
- `Test/Unit/Model/Cod/ConfiguredCodPaymentMethodResolverTest.php` (mới, 8 tests)
- `README.md` + `CHANGELOG.md` (0.13.0)
- Governance: SPEC + plan + TASK record + FEAT-YA2C0W ticket_ref + CURRENT_STATE

0 carrier / 0 Mageplaza / 0 vendor code thay đổi.
