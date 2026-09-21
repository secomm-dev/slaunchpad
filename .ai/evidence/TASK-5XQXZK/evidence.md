# Evidence — TASK-5XQXZK (Mageplaza TableRate fallback composition)

Ngày: 2026-09-15 · DEC: DEC-TASK5XQXZK-001 · Plan: `.ai/plans/TASK-5XQXZK-implementation-plan.md`

## 1. Unit tests (PHPUnit 10.5.64, dev/tests/unit)

| Suite | Result |
|---|---|
| `Secomm_ShippingCore` (TOÀN BỘ Test/Unit) | OK — **267 tests, 684 assertions** (gồm mới: CarrierRateOutcomeCollectorTest 9, SafeDegradationEligibilityPolicyTest 13) |
| `Launchpad_MageplazaTableRate` (TOÀN BỘ Test/Unit) | OK — **32 tests, 48 assertions** (mới: FallbackCoordinatorTest 10 = matrix §21 eligibility/suppress/non-participating/stale/fail-soft; MethodVisibilityFilterTest 6 = 4 tổ hợp caps + zero-dedup; CityRateScopeResolverTest 8 = precedence 3 tier + unresolved + SUM/MIN/MAX-in-tier + zero-regression; FallbackRateProviderTest 8 = bucket code + pipeline) |
| `Secomm_Ghn` | OK — **192 tests** (carrier record outcomes; collector inject; GhnRateCalculator AMBIGUOUS/UNMAPPED classification) |
| `Secomm_Ghtk` | OK — **237 tests** (1 PHPUnit deprecation pre-existing) |

## 2. Compile & schema

- `bin/magento setup:di:compile` → **"Generated code and dependency injection configuration successfully."** (2 constructor-signature lỗi trong lần đầu đã sửa; 1 lỗi XML pre-existing `Secomm/Ghn/etc/di.xml` — virtualType lồng sai trong `<arguments>`, GHN-E1 — được fix surgical, di chuyển node ra top-level, zero behavior change, flag trong report).
- `setup:db-declaration:generate-whitelist --module-name=Launchpad_MageplazaTableRate` → whitelist 3 bảng generated.
- `setup:upgrade` → 3 bảng tạo thành công, verified qua DESCRIBE:
  `launchpad_mptablerate_method_setting(method_id,show_to_customer,use_as_fallback)`,
  `launchpad_mptablerate_method_member(member_id,method_id,carrier_code,method_code,enabled)`,
  `launchpad_mptablerate_rate_city(rate_id,city_code)`; FK CASCADE + UNIQUE triple + index city_code.

## 3. Runtime seam verification (code evidence)

- `vendor/magento/module-shipping/etc/di.xml:9` — `RateCollectorInterface` preference = `Magento\Shipping\Model\Shipping` (duy nhất) → outer plugin cover storefront/REST/GraphQL/admin.
- `Shipping::collectRates()` trả `$this`; result = `getResult()` accumulator; chỉ set ORIGIN fields khi `!getOrig()` — `destCity` từ quote address nguyên vẹn (city dimension dùng được không cần đổi `FallbackRateRequestInterface` — directive §14 branch YES).

## 4. Validator

- `bin/project-ai-validate --check-specs --check-records` → 23 FAIL **TẤT CẢ pre-existing** (BUG-*/TASK-ZR2ZNS/SPEC naming/plan cũ — debt từ các work item khác, liệt kê trong report); **0 FAIL** cho TASK-5XQXZK / FEAT-GGTWXW / DEC-TASK5XQXZK-001.

## 5. Scope fence checks

- Grep dependency: `Launchpad\MageplazaTableRate` không có use nào của `Secomm\Ghn|Secomm\Ghtk|Secomm\Ahamove`; `Secomm\ShippingCore` không có use của Mageplaza/carriers (đúng directive §1).
- Mageplaza source (`app/code/Mageplaza/TableRateShipping/**`) không bị sửa — extension 100% qua plugin/preference/layout + extension tables.


## 6. TL/SA review round (2026-09-16) — fixes + QC L3

### Fixes áp dụng theo review directive
1. **§1A try/finally**: `CollectRatesPlugin` bọc proceed+logic trong `try/finally { endCollection() }` — throw không leak outcomes sang collection sau.
2. **§1B overwrite tests**: +3 collector tests (TECHNICAL→SUCCESS, UNAVAILABLE→SUCCESS, last-wins-by-contract với docblock: SUCCESS→diagnostic KHÔNG reachable trong carrier flows hiện tại). Last-wins GIỮ NGUYÊN.
3. **Duplicate presentation (Case 3, phát hiện khi soạn QC)**: fallback copy của method C (show=1+fallback=1) trùng native → `FallbackCoordinator::nativeMethodIdsIn()` skip fallback copy khi method đã có native rate. +1 unit test.
4. Pre-existing `Secomm/Ghn/etc/di.xml` virtualType lồng sai — fix surgical. `setup:di:compile` PASS; Launchpad 32 OK; collector 12 OK.

### §1C — global `carriers/mptablerate/active` (E2E VERIFIED)
Fallback engine KHÔNG đọc global active (grep sạch); E2E active=0: fallback A vẫn append khi eligible → fallback-only độc lập global active. `active=1` chỉ cần cho standalone B/C native.

### QC L3 (script `qc-run.php`; seed 4 methods A/B/C/D + members + city row; state toggles GHTK api_base_url→refused host (TECHNICAL thật), xóa GHN mapping + clean `secomm_ghn_mapping` cache, xóa edges Tan My; MỌI state REVERTED + verified)

| Scenario | Result |
|---|---|
| S1 GHN SUCCESS sandbox thật 82,500 VND | **PASS** — GHN hiển thị; A suppress; B=55000/C=65000 standalone; D vắng |
| S2 ineligible+TECHNICAL mix | **PARTIAL (deviation)** — GHN resolver còn fallback nội bộ từ unit master nên vẫn quote dù mapping rows xóa (behavior GHN module, ngoài scope bridge — flag team GHN); lane này COVER bởi coordinator unit matrix |
| S3 GHN CANONICAL_AMBIGUOUS thật (An Dong, 3 PRE candidates) + GHTK TECHNICAL | **PASS** — GHN không guess; fallback A=30000 |
| S4 CANONICAL_UNMAPPED thật (xóa edges Tan My) | **PASS** — NO fallback |
| S5 ghost member only | **PASS** — không synthetic failure, mọi scenario |
| S6 fallback-only visibility | **PASS** — A không bao giờ ở normal output; chỉ khi eligible |
| S7 city tiers | **PASS** exact/region/wildcard qua E2E lần 1 (state match) + unit tier tests; unresolved city: carriers không chạm API → no fallback (đúng §5) |
| S8 re-estimate same process | **PASS** — không stale outcomes/fallback |

### Channels + Result
Storefront/REST/GraphQL/admin cùng 1 seam (`RateCollectorInterface` preference duy nhất); E2E qua path Quote thật. **APPROVE WITH FIXES — fixes đã áp dụng, tests rerun PASS, S2-E2E deviation documented (GHN resolver fallback cần team GHN xác nhận riêng).**

## 7. S2 root-cause resolution (2026-09-16, TASK-78PVR0)
QC S2 "GHN vẫn quote" KHÔNG phải fallback masking: mapping bảng là **dual-scheme**
(DEC-FEATFQWEQ3-001) — RATE đọc row key `VN_ADMIN_PRE_2025` (VNAP25-…) riêng biệt với row key
2025 đã xóa. Behavior = intentional design, resolver fail-closed (KEEP). Deviation S2-E2E
được giải thích trọn vẹn; GHN-module audit chi tiết: `.ai/records/tasks/TASK-78PVR0-ghn-rate-classification-audit.md`.
