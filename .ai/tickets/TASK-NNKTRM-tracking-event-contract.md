# TASK-NNKTRM — Event contract: DTO + Normalizer + UserDataHasher + unit tests

**Type:** Task (slice của FEAT-31X6N2 — core server-side pure logic)
**Priority:** High
**Estimate:** ~10h
**Mode:** B (pure PHP logic + unit tests; PII hashing là nhánh risk của feature — được cover bởi spec VALID + DEC, code review Tier-2)
**Placement:** `app/code/Secomm/Tracking/Model/Event/`, `app/code/Secomm/Tracking/Model/Hash/`, `app/code/Secomm/Tracking/Test/Unit/`
**Risk tier:** Tier 2 (PII hashing path — code review chặt)
**Author:** AI draft · **Date:** 2026-08-24 · **Status:** Dev complete — chờ phpunit + TL review (Tier-2 PII path)
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-31X6N2](../specs/SPEC-FEAT-31X6N2-commerce-tracking.md) §4, §6.4, §7 · Plan: [TASK-NNKTRM plan](../plans/TASK-NNKTRM-implementation-plan.md)

## Description

Pure server-side logic của event contract:

- `Model/Event/TrackingEvent.php` — immutable DTO theo contract spec §4 (event, event_id, event_time, currency, value, order_id, items[], user, consent, source) + `Item` DTO.
- `Model/Event/EventNormalizer.php` — build `TrackingEvent` từ Magento entities: Order → purchase/refund; deterministic event_id generator (`purchase-{increment_id}`, `refund-{cm_increment_id}`, `{event}-{entity_id}-{Ymd}` cho browser-context) theo spec §7.
- `Model/Hash/UserDataHasher.php` — SHA-256 hashing + normalization: email (trim/lowercase); phone VN-aware E.164 (spec §6.4: strip separators, `0`-leading 10 số → `+84…`); external_id (customer_id).
- Unit tests: normalizer mapping (7 events), hasher VN phone cases (`0901234567`→`+84901234567`, `+84901234567`, `(090) 123-4567`, uppercase email), event_id determinism.

**[BLOCK] rule:** hasher input chỉ từ server entities (Order billing address / Customer); raw identifier không được persist ngoài scope hàm — normalizer hash trước khi tạo payload JSON.

## Mini Spec

### Goal

Launchpad Tracking Event contract thành code: mọi pipeline (browser TASK-E0NG8Z, server TASK-VRKJKQ) consume cùng 1 DTO + 1 normalizer.

### Expected Behavior

- `EventNormalizer::fromOrder(Order $order): TrackingEvent` — event=purchase, value=grand_total display, currency=order currency, items từ visible items (SKU/qty/price display), user hashed từ billing address (+ customer_id khi có).
- `EventNormalizer::fromCreditmemo(Creditmemo $cm): TrackingEvent` — event=refund, value=CM grand_total display.
- Hasher deterministic: cùng input → cùng hash; VN phone normalize đúng mọi format phổ biến.
- event_id deterministic: 2 lần normalize cùng order → cùng event_id (unit test assert).

### Constraints / Rules

- Immutable DTO (readonly properties PHP 8.2).
- Không gọi HTTP, không DB, không dependency ngoài Magento framework + Sales API.
- Payload JSON không bao giờ chứa plain email/phone — unit test assert chuỗi plain không xuất hiện trong `json_encode()`.

### Out of Scope

Vendor mapping (Meta/TikTok adapter — TASK-VRKJKQ) · observers/outbox insert (TASK-8FZ8YX) · browser dataLayer (TASK-E0NG8Z).

### Acceptance Criteria

- [ ] **AC-1:** Unit tests pass: 7 event mappings đúng field contract spec §4 (value/currency/items/user).
- [ ] **AC-2:** Hasher: ≥8 phone case (VN formats + quốc tế + garbage-input → null an toàn) + email cases pass; output luôn 64-char hex.
- [ ] **AC-3:** event_id determinism assert (màu order → 2 lần → cùng ID; order khác → ID khác).
- [ ] **AC-4:** PII leakage test: `json_encode(event)` không chứa plain email/phone substring.
- [ ] **AC-5:** `vendor/bin/phpunit` toàn module green, không warning.

## Approach

> Retro-canonical 2026-08-27 — distilled từ work đã dev-complete; status các bước là thực tế.

1. **DTO layer trước, không vendor knowledge:** `TrackingEvent` + `Item` + `UserData` là immutable readonly PHP 8.2 DTO theo contract §4. `UserData` chỉ nhận SHA-256 hex từ hasher — impossible-việc truyền plain qua constructor typing ([BLOCK] PII rule enforcement bằng cấu trúc, không phải kỷ luật).
2. **EventNormalizer thuần:** `fromOrder()` / `fromCreditmemo()` nhận concrete `Magento\Sales\Model\Order|Creditmemo` (không phải interface — observer truyền model thật; đồng thời lộ đúng `getRemoteIp()`). Items qua `getAllVisibleItems()` để children configurable/bundle tự nested. event_id deterministic: `purchase-{increment_id}` / `refund-{cm_id}` / `{event}-{entity}-{Ymd}` — server single source, browser render cùng ID.
3. **UserDataHasher tách pure:** không dependency Magento nào → unit test thẳng không bootstrap. Logic VN E.164: strip separators → nhận diện `84` prefix / trunk-zero local / VN-country context → `+84…`; garbage → `null` (field absent, không hash rác). Cross-validate logic bằng Python reference implementation trước khi commit (9 VN cases + garbage + intl khớp 100%).
4. **Unit tests 2 suite:** hasher (mapping table + hex-format assert + determinism) + normalizer (mock bằng real data objects Order/Order\Item — khớp thực tế observer truyền vào; PII leakage test assert chuỗi plain không xuất hiện trong `toJson()`).

Bước đã làm thêm ngoài plan gốc (phát hiện khi runtime): `TrackingEvent::withConsent()` + `withEventTime()` + `fromArray()` rehydrate cho flush-side; `browserEventId()` cho TASK-E0NG8Z; PSR-4 audit tooling cho cả module.

**Parent:** [FEAT-31X6N2](../records/features/FEAT-31X6N2.md) · **Spec:** SPEC-FEAT-31X6N2 §4/§6.4/§7