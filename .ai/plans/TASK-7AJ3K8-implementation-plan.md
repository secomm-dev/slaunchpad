# Implementation Plan: TASK-7AJ3K8 — GHTK alignment lên target architecture ShippingCore

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-7AJ3K8 (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract + carrier runtime — Tier-2) |
| Specification | [specs/SPEC-TASK-7AJ3K8-ghtk-shippingcore-alignment.md](../specs/SPEC-TASK-7AJ3K8-ghtk-shippingcore-alignment.md) — FULL, VALID (audit basis §2; user directive 2026-09-10; TL review chạy cùng code pre-review) |
| Decision | [DEC-TASK7AJ3K8-001](../records/decisions/DEC-TASK7AJ3K8-001.md) (5 amendments) trên nền [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md) |
| Contract basis | TASK-AQT7V3 (E-A contracts) · TASK-5XDG1P (E-B manager) · TASK-T78YH6 (E-C0 handoff) · TASK-Q4B98P (bridge) |
| Risk | High — carrier runtime + shared contracts; backward-compat pickup config là ràng buộc cứng |

## Approach

Ba hướng song song gặp nhau ở `GhtkDestinationResolver` (Stage-2 adapter): (1) VietNamAddress bổ
sung name-based bridge (sibling interface, match vi/en trên reference layer); (2) ShippingCore bổ
sung runtime context builder + manager candidates passthrough + handoffContext entry + HTTP
primitive + tracking reconciliation; (3) GHTK thay 3 class address cũ bằng capability + profile +
Stage-2 resolver + fetcher, client chuyển lên shared transport. Không đổi schema DB, không đổi
system.xml, không đổi webhook contract, không đổi legacy pickup chain.

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | record + SPEC + DEC + plan + DECISIONS.md index | spec-first TRƯỚC code; idgen mint |
| 2 | VietNamAddress bridge | `Api/VnOperationalNameResolverInterface.php`, `Api/Data/VnOperationalNameResolutionInterface.php`, `Model/VnOperationalNameResolver.php`, `etc/di.xml` | DEC §Decision 3; compose resolveFromCanonical |
| 3 | ShippingCore context | `Api/Address/RuntimeAddressContextBuilderInterface.php`, `Model/Address/RuntimeAddressContextBuilder.php`, manager candidates, `DestinationContextBuilder` delegate, `CarrierAddressHandoffServiceInterface::handoffContext` | fix F8; AMBIGUOUS passthrough |
| 4 | ShippingCore HTTP | `Api/Http/{CarrierHttpErrorCategory, CarrierHttpException, CarrierHttpClientInterface}.php`, `Model/Http/{CarrierHttpRequest, CarrierHttpResponse, CurlCarrierHttpClient, RetryPolicy, RetryExecutor}.php`, di.xml | DEC §Decision 1 |
| 5 | ShippingCore tracking | `Api/Tracking/CarrierTrackingFetcherInterface.php`, `Model/Tracking/TrackingReconciliationService.php` | logic = F6, carrier-agnostic |
| 6 | Ghtk address | `Model/Address/{GhtkAddressCapability, GhtkDestinationResolver}.php`, `Model/GhtkApiProfile.php`; xóa `WardIdBridge`, `BestEffortViVnResolver`, `DestinationAddressResolver` | matrix §6 SPEC |
| 7 | Ghtk client + tracking | `GhtkApiClient` trên shared transport + `GhtkTrackingFetcher` + thin `TrackingRefreshService` + di.xml virtual type | submitOrder single attempt giữ nguyên |
| 8 | Consumers rewiring | `Carrier/Ghtk.php`, `OrderSubmitService.php`, `PickupAddressResolver.php` | chỉ đổi call target |
| 9 | Tests | mới + update theo §Test plan | phpunit-secomm.xml |
| 10 | Docs + evidence | README/CHANGELOG 3 module, `.ai/evidence/TASK-7AJ3K8/`, working memory | [WARN] rule |

## Test plan

- **VietNamAddress**: name bridge — vi exact / en exact / ambiguous 2 units (candidates, không pick) /
  unmapped / non-VN region / scheme-not-active / whitespace trim / compose identity đúng (mock
  operational resolver).
- **ShippingCore context**: id-based ưu tiên; name fallback khi cityId=0; AMBIGUOUS candidates vào
  context; manager: identity+candidates → AMBIGUOUS passthrough, identity null + no candidates →
  UNMAPPED; quote builder delegate + street + PII hygiene.
- **Handoff**: `handoffContext` matrix (non-VN/EXACT/AMBIGUOUS+fallback/UNMAPPED±fallback) — assertion
  theo test cũ + entry mới.
- **HTTP**: classify NETWORK/RATE_LIMIT/SERVER_ERROR/CLIENT_ERROR/INVALID_RESPONSE; sendJson decode
  fail; RetryExecutor retry-on-network + stop-on-client-error + maxAttempts; 429 no-retry.
- **Ghtk client**: fee retry theo retry_max (mock http), 4xx no retry, submitOrder 1 attempt, JSON
  encode fail; profile paths đúng.
- **Ghtk address**: alias hit exact / alias miss → name_vi / AMBIGUOUS → null / UNMAPPED fallback →
  province name_vi + submitted ward / non-VN → null / cache theo unitCode.
- **Origin**: test legacy pickup chain giữ nguyên pass (`GhtkOriginProviderTest`,
  `PickupAddressResolverTest` update call shape).
- **Tracking**: shared service — terminal exclude, stale filter, batch, per-item exception continue,
  fetcher null không count; GhtkTrackingFetcher — status text/id, missing status → null, UNKNOWN map.
- **Carrier/OrderSubmit**: update mocks sang resolver mới; rate hide semantics giữ nguyên.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Ambiguous ward trước đây có rate → giờ mất | intentional (directive); log warning; rollback = revert commit (behavior cũ là first-match sai) |
| name match rộng hơn (vi+en) làm đổi kết quả resolve | exact match sau trim; test chốt; reference layer là dataset chính thức |
| Shared client đổi error surface | `GhtkApiException` giữ nguyên làm public surface; mapping category→isRetryable có test |
| Tracking service đổi làm cron hỏng | thin shell giữ config gate; test shared service đầy đủ; cron không đổi shape |
| Legacy pickup config bị ảnh hưởng | `GhtkOriginProvider` không đụng; AC-5 test chạy lại |

## Validation gates

phpunit secomm · `bin/project-ai-validate --check-specs --check-records` · `php -l` toàn bộ file mới ·
grep AC-1 · grep "không carrier import vào ShippingCore" (reverse dependency check).

---

# r1 Amendment (2026-09-10) — TEXT_NATIVE correction (DEC-TASK7AJ3K8-002)

Directive r1 đổi premise: canonical `name_vi` là representation MẶC ĐỊNH cho GHTK (TEXT_NATIVE);
bảng `secomm_ghtk_address_map` hạ cấp exception/override. Steps bổ sung:

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| r1-1 | Governance r1 | DEC-TASK7AJ3K8-002 + SPEC r1 section + plan này + task record | supersede MỘT PHẦN DEC-001 D2/D4 |
| r1-2 | Schema re-key | `etc/db_schema.xml` + whitelist: `(scheme_code, province_code, ward_code)` UNIQUE + nullable overrides + note; drop runtime cols + old unique/index | ⚠️ setup:upgrade required; whitelist giữ entry drop |
| r1-3 | Entity rename + rewrite | `Api/Data/GhtkAddressOverrideInterface`, `Model/GhtkAddressOverride`, `ResourceModel/GhtkAddressOverride(+/Collection)`, `Model/GhtkAddressOverrideRepository` | git mv (history) |
| r1-4 | Import stack | namespace `GhtkAddressOverrideImport`; CsvReader header mới; Validator 6 canonical checks (unit provider); Importer replace-all canonical keys | SampleCsv + Upload controllers cập nhật |
| r1-5 | Adapter | `GhtkAddressAdapter` (rename): canonical name_vi default + optional override + no-guess; cache theo unitCode | reverse bridge removed |
| r1-6 | Capability/profile | `supportsTextualFallback()=false`; `getAddressMode()=TEXT_NATIVE` | behavior change: UNMAPPED mất rate |
| r1-7 | Guard removal | xoá `Model/Import/DirectoryReferenceGuard` + test + DI registration | table không còn runtime refs |
| r1-8 | Tests r1 | adapter (15), validator (9), csv (6), importer (4), profile/capability | §16 matrix |
| r1-9 | Docs/evidence | README + CHANGELOG r1; evidence update | |

Risks r1: schema migration bắt buộc setup:upgrade (dev-stage table, re-import); UNMAPPED mất
rate là intentional fail-closed; sandbox E2E PENDING (dev sandbox unreachable + DB down tại
thời điểm làm — runbook trong evidence).
