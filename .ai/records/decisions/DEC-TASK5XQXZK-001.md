---
id: DEC-TASK5XQXZK-001
title: 'Mageplaza TableRate fallback: Magento làm carrier orchestrator (CarrierRateOutcomeCollector) + Mageplaza Method = fallback grouping identity (per-method show_to_customer/use_as_fallback/membership) + safe-degradation eligibility + city dimension — supersede service_level_code mapping + global FALLBACK_ONLY mode'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-15
created: 2026-09-15
last_verified: 2026-09-15
verified_against_commit:
supersedes: []   # KHÔNG supersede nguyên vẹn DEC nào; supersede MỘT PHẦN: DEC-TASKNQT782 scope (mapping DI service_level→method + global mode) + phần §15.1 note ownership + §17/§18 architecture doc (xem §Amendments)
superseded_by:
work_items: [FEAT-GGTWXW, TASK-5XQXZK]
---

# Decision Record: Mageplaza TableRate fallback — Magento orchestrator + per-method fallback groups + safe degradation + city dimension

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-09-15 (user acting as SA/TL — 22-point architecture
     directive sau audit FEAT-GGTWXW). Index pointer: .ai/project-context/memory/DECISIONS.md -->

## Status

Accepted (2026-09-15 — user acting as SA/TL)

## Context

Audit 2026-09-15 (FEAT-GGTWXW) chứng minh: (1) mô hình hiện tại `service_level_code → method_id` (DI) + global mode FALLBACK_ONLY/STANDALONE không đáp ứng requirement per-method; (2) ShippingCore decision path chưa có production caller — không có fan-out; (3) Mageplaza TableRate v4.0.8 không có city dimension, không có events/Api, matching không precedence. Decision mới định lại runtime model.

## Decision

1. **KHÔNG build carrier fan-out registry trong ShippingCore** (REJECT `CarrierRateProviderInterface` registry). Magento Shipping Framework own carrier discovery + rate collection. Carrier không cài/disabled → không có outcome, không phải failure. Dependency giữ: `Secomm_{Ghn,Ghtk,Ahamove} → Secomm_ShippingCore`; forbidden `ShippingCore → carrier`, `Launchpad_MageplazaTableRate → carrier`.
2. **ShippingCore thêm `CarrierRateOutcomeCollectorInterface`** — carrier-neutral, không persist, không Mageplaza concepts: `beginCollection()/record(carrierCode, methodCode, CarrierRateOutcomeInterface)/getOutcomes()/endCollection()`. Outcome identity = **pair `(carrier_code, method_code)`** (không dùng composite underscore string). Execution isolation: bracket quanh MỖI lần `RateCollectorInterface::collectRates()` (không giả định 1 HTTP request = 1 collection).
3. **Carrier chỉ report, không tự fallback**: Magento gọi carrier → carrier realtime rate → record normalized outcome (SUCCESS/UNAVAILABLE/TECHNICAL_FAILURE + structured `ShippingFailureReason`) → trả result Magento như thường. Thêm reasons `CANONICAL_AMBIGUOUS`, `CANONICAL_UNMAPPED`, `INVALID_CONFIGURATION` vào `ShippingFailureReason`. Carrier không call Mageplaza/Launchpad, không tự quyết fallback.
4. **Safe-degradation eligibility — ShippingCore owns policy**: carrier report FACT (status + reason); ShippingCore quyết POLICY qua `FallbackEligibilityPolicyInterface` (default map: `TECHNICAL_ERROR`+TECHNICAL_FAILURE → eligible; `CANONICAL_AMBIGUOUS` → eligible; `CANONICAL_UNMAPPED`/`UNSUPPORTED_DESTINATION`/`PROVIDER_MAPPING_MISSING`/`INVALID_CONFIGURATION`/`SERVICE_UNAVAILABLE`/`INVALID_PARCEL` → NOT eligible; DI-array override được; không parse message; không convert CANONICAL_AMBIGUOUS → TECHNICAL_FAILURE).
5. **Mageplaza Method = fallback grouping identity** (KHÔNG EXPRESS/SAME_DAY/STANDARD, không ShippingService/BusinessService). Per method: `show_to_customer`, `use_as_fallback`, membership N realtime methods `UNIQUE(method_id, carrier_code, method_code)`. Extension tables `launchpad_mptablerate_method_setting` / `launchpad_mptablerate_method_member` / `launchpad_mptablerate_rate_city`, FK ON DELETE CASCADE. Global `launchpad_mptablerate/general/mode` + `methodMapping`/`labels` DI **deprecated** (chưa production data); `carriers/mptablerate/active` chỉ là carrier activation Mageplaza, không mô phỏng fallback-only.
6. **Fallback trigger = outer lifecycle seam**: plugin `around Magento\Shipping\Model\Shipping::collectRates()` (preference duy nhất của `RateCollectorInterface` — storefront/REST/GraphQL/admin đều đi qua): begin collector → `proceed()` (normal carriers) → đọc outcomes → evaluate per method (any participating member SUCCESS → suppressed; ≥1 approved-eligible outcome → eligible; member không tham gia collection không tự trigger) → append fallback rate vào Result + filter `show_to_customer=0`. Không dùng 3-seam duplicate. KHÔNG auto-hide realtime members (dedup = chủ đích admin).
7. **City dimension**: extension table `launchpad_mptablerate_rate_city(rate_id, city_code)`; `city_code` = stable `directory_region_city.code` (= Secomm unit_code); KHÔNG dùng auto-increment city_id làm identity import; không hardcode district_id/ward_id; dest resolve qua `Secomm_VietNamAddress` resolvers, không resolve được (AMBIGUOUS/UNMAPPED/non-VN) → không guess, city-rows không match, legacy rows vẫn match. Precedence CHỈ narrow location scope (exact-city → exact-region+wildcard-city → broader), không phá SUM/MIN/MAX multi-row aggregation; không có city mapping → behavior Mageplaza nguyên vẹn.
8. **`FallbackRateRequestInterface` KHÔNG mở vội** — outer plugin có Magento RateRequest (destCity + region): bridge dùng runtime context nội bộ. Chỉ mở contract ShippingCore khi một provider-neutral consumer thật chứng minh gap.
9. **CSV**: thêm optional `city_code` column (stable code), unknown code import → fail validation rõ ràng; fix round-trip gap (postcode/shipping_group) nếu làm được an toàn trong extension Launchpad; document đúng cột supported.
10. **Architecture doc update sau implementation** (§15–§18, §9 semantics, §12 deprecate `CarrierServiceLevelInterface` nếu vẫn unused, runtime architecture diagram, checklist §29) — không để dead abstraction "just in case".

## Consequences

- Bridge hoạt động với bất kỳ subset carriers nào cài đặt; Launchpad không depend carrier module.
- Fallback semantics mở rộng từ "chỉ TECHNICAL_FAILURE" sang approved safe-degradation (CANONICAL_AMBIGUOUS bổ sung) — status giữ semantic riêng, policy central hóa ShippingCore.
- Mageplaza upgrade-safe (không alter vendor schema/source; extension qua plugin/preference + extension tables).
- Hiệu lực: SCOPE-CREEP FENCE — không thêm routing/ranking/dedup tự động; reopen chỉ khi consumer thật chứng minh.
