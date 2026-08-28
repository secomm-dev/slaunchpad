# TASK-NNKTRM Implementation Plan — Event contract: DTO + Normalizer + UserDataHasher + unit tests

| Field | Value |
|---|---|
| Specification | tickets/TASK-NNKTRM-tracking-event-contract.md (embedded Mini Spec) · canonical parent specs/SPEC-FEAT-31X6N2-commerce-tracking.md §4, §6.4, §7 |
| Mode | B · Tier 2 (PII hashing path) |

> **Status:** Retro-canonical — distilled 2026-08-27 từ work đã dev-complete. **phpunit chưa chạy lần nào** (runtime cần user).

---

## PART 1 — ANALYSIS

| Quyết định | Kết luận | Nguồn |
|---|---|---|
| DTO immutable readonly PHP 8.2 | `TrackingEvent` + `Item` + `UserData`; `UserData` chỉ nhận SHA-256 hex — PII rule enforce bằng typing, không kỷ luật | spec §4, §6.4 [BLOCK] |
| Normalizer nhận concrete models | `Magento\Sales\Model\Order|Creditmemo` (không interface) — observer truyền model thật; `getRemoteIp()` không có trên interface | implementation finding |
| Items: `getAllVisibleItems()` | Children configurable/bundle tự nested; creditmemo item lọc theo `getOrderItem()->getParentItem()` | spec §4 |
| event_id deterministic | `purchase-{increment_id}` / `refund-{cm_id}` / `{event}-{entity}-{Ymd}` — server single source, browser render cùng ID (dedup D3) | spec §7 |
| Hasher pure, 0 Magento dependency | Unit test không cần bootstrap; VN E.164 normalize (strip separators → `84` prefix / trunk-zero / VN-context) | spec §6.4 |
| Cross-validate trước commit | Python reference implementation chạy cùng bảng case (9 VN + garbage + intl) khớp 100% | QC |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | DTO layer | `Model/Event/TrackingEvent.php`, `Item.php`, `UserData.php` | ✅ |
| 2 | Normalizer + deterministic ids | `Model/Event/EventNormalizer.php` | ✅ |
| 3 | Hasher VN E.164 + SHA-256 | `Model/Hash/UserDataHasher.php` | ✅ |
| 4 | Unit tests 2 suite | `Test/Unit/Hash/UserDataHasherTest.php`, `Test/Unit/Event/EventNormalizerTest.php` | ✅ code / ⏳ chưa chạy |

## Phát sinh khi runtime (ngoài plan gốc)

- `TrackingEvent::withConsent()` / `withEventTime()` (backfill) / `fromArray()` (flush rehydrate) + `browserEventId()`.
- `Config::decryptConfigValue()` handle cả cipher envelope lẫn plain token (set qua `config:set` không mã hóa) — đỡ crash cron.

## QC / còn chờ

`vendor/bin/phpunit app/code/Secomm/Tracking/Test/Unit` — AC-1..AC-5 của Mini Spec.
