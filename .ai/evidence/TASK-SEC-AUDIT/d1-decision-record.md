# D1 Decision Record — ShippingCore/GHN orchestration (Step 3)

Ngày: 2026-09-21 · TASK-SEC-AUDIT · **HARD STOP: chưa implement thêm bất kỳ orchestration change nào trong lượt này**

## 1. Current production call graph (verified bằng code + DI, ngày 2026-09-21)

```text
Magento\Shipping\Model\Shipping::collectRates()
└─ [outer seam] Launchpad\MageplazaTableRate\Plugin\Shipping\CollectRatesPlugin::aroundCollectRates()
   ├─ outcomeCollector->beginCollection()
   ├─ proceed()  →  Magento invokes carriers natively:
   │   ├─ Secomm\Ghn\Model\Carrier\Ghn::collectRates()          ← ĐÃ WIRE (stream FEAT-QA23PZ, commit 6a804351-era)
   │   │   ├─ entry gates còn lại ở GHN: active, VN destination, VND currency
   │   │   ├─ Ghn\Config: getDestinationScope / getAllowedZoneCodes / getRateSourceMode (thin read)
   │   │   ├─ Ghn::buildExecutionRequest() → RealtimeRateContributorFactory::create(request)
   │   │   ├─ CarrierRateExecutionService::execute(request)      ← PRODUCTION, không còn test-only
   │   │   │    eligibility → mode → origin → policy(handoff) → contributor
   │   │   │    └─ RealtimeRateContributor::contribute()
   │   │   │         → GhnRateRequestMapper::map → GhnRateCalculator::quoteWithHandoff
   │   │   │           → GHN mapping → Fee API → CarrierRateOutcome
   │   │   ├─ decision mapping: OUT_OF_SCOPE → record UNAVAILABLE+DESTINATION_NOT_IN_SCOPE → hide;
   │   │   │    FALLBACK_ONLY skip → record skip fact → hide; success → rateAdjuster → buildResult
   │   │   └─ recordOutcome(outcome) → CarrierRateOutcomeCollector
   │   └─ (Mageplaza native carrier; GHTK carrier — un-wired, giữ path riêng)
   ├─ MethodVisibilityFilter::filter(result)          ← visibility TRƯỚC append
   ├─ FallbackCoordinator::appendFallbackRates()      ← FINAL fallback dispatcher (read collector)
   │    isMemberEligible(): DESTINATION_NOT_IN_SCOPE guard → mode gate → policy gate → shared policy
   └─ outcomeCollector->endCollection()
```

### Ownership table

| Concern | Current owner | Intended owner (SSOT v10 §35) | Duplicate/shadow? |
|---|---|---|---|
| Eligibility | CarrierRateExecutionService (qua GHN wire) | ShippingCore execution service | Không |
| Destination scope | GHN Config thin read → execution request | ShippingCore (config convention `carriers/<code>/…`) | Không |
| Rate source mode | Execution service (gate) + coordinator (per-member gate cho members chưa wire) | Execution service | **Tránh được khi wire xong toàn bộ members** |
| Origin readiness | Execution service | Execution service | Không |
| Address policy | Execution service → handoff service | Execution service + handoff | Không |
| Canonical resolution | Handoff service → VnAdminAddressResolver | Handoff service | Không |
| GHN provider mapping | GhnRateCalculator (quoteWithHandoff) | Carrier (GHN) | Không |
| GHN API call | GhnRateCalculator | Carrier | Không |
| Rate adjustment | GHN rateAdjuster (AFTER success, trong carrier) | Carrier | Không |
| Outcome recording | Ghn::recordOutcome → collector | Collector | Không |
| Fallback eligibility | Execution decision (GHN side) NHƯNG collector chỉ mang outcome → coordinator RE-JUDGE từ outcome + đọc lại mode/policy | Execution service decide; coordinator chỉ consume | **CÓ — policy được quyết 2 nơi (mảnh còn thiếu)** |
| Fallback price | FallbackRateProvider (Launchpad) | Composition | Không |
| Fallback dispatch | FallbackCoordinator (outer seam) | Composition (SSOT §35.9 cho phép composition owns dispatch) | Không |
| Realtime suppression | FallbackCoordinator (any member success) | Orchestrator semantics equivalently implemented ở coordinator | Tương đương, không song song |

### Consumer classification

- Production consumer: execution service (GHN wire), collector, FallbackCoordinator, visibility filter.
- Test-only consumer: `ServiceLevelRateAggregator` + `ServiceLevelRateOrchestrator` (0 production caller — Launchpad composition chưa đi qua chúng).
- DI registration không caller: (đã dọn) — `RealtimeCarrierRateContributorInterface` không default implementation (by design).
- Comment claims khớp code sau commit 6a804351 (GHN wire là thật, có tests kèm).

## 2. Decision gate

### Option 1 — Wire GHN qua CarrierRateExecutionService — **ĐÃ ĐƯỢC IMPLEMENT (stream FEAT-QA23PZ)**
Đánh giá hiện trạng: entry gates GHN còn lại tối thiểu (VN/VND/active); eligibility/mode/origin/policy chạy trong shared service theo §35.6; realtime tail qua `quoteWithHandoff` — giá không tính 2 lần (calculator chỉ chạy 1 lần qua contributor); outcome 1 lần qua collector; coordinator vẫn là final dispatcher (không dispatch thứ hai).

**Gap P1 duy nhất còn lại**: fallback ELIGIBILITY transport. `decision.getFallbackEligibility()` (đã gồm mode/policy gating của service) KHÔNG được ghi vào collector — coordinator tự re-judge bằng cách đọc lại mode/policy (`MemberRatePolicy`) + shared policy trên outcome. Hiệu lực hiện tại: đúng kết quả cho GHN (2 bên dùng cùng constants/policy), nhưng đây vẫn là quyết định policy lần hai — vi phạm tinh thần Option 1 và sẽ sai lệch khi members khác được wire.

### Option 2 — Giữ runtime cũ và sửa contracts — KHÔNG CÒN phù hợp
Execution service giờ là production contract (GHN entry dùng nó) — Option 2 đã bị thực tế vượt qua. Chọn Option 2 đồng nghĩa revert wire hoặc chấp nhận hai orchestration path song song → tệ hơn.

## 3. Chosen option + điều kiện hoàn tất

**Option 1** (theo SSOT §35.6/§35.9, đã implement phần lớn). Việc còn lại (cần approval rồi mới làm — HARD STOP hôm nay):

1. Collector contract: thêm optional `FallbackEligibilityInterface` per (carrier, method) record — hoặc một record riêng cùng bracket.
2. `Ghn::collect()`: sau execute, record decision's fallbackEligibility cùng outcome.
3. `FallbackCoordinator::isMemberEligible`: member CÓ eligibility recorded → consume trực tiếp; member CHƯA (GHTK/legacy) → giữ legacy re-judge path (BC transition).
4. Test: assert số lần resolver/contributor/API/fallback-provider — không double-execution; D3 matrix qua outer seam.
5. GHTK: không ép wire — giữ path riêng cho tới khi có directive riêng.

## 4. Migration/BC

Không đổi schema; collector change là additive (optional record) → BC với mọi consumer hiện tại; coordinator transition path giữ behavior cho members chưa wire; rollback = revert collector record call (coordinator tự fallback legacy path).


---

# r2 (2026-09-21) — re-verified on the classified patch stack

Call graph re-confirmed: GHN wire (Option 1) là uncommitted FEAT-QA23PZ stream changes trên
HEAD 6a804351 — production-reachable chỉ khi stack đó được review/merge. Không có consumer
production mới của Aggregator/Orchestrator trên stack này.

## Eligibility transport TARGET DESIGN (proposed — KHÔNG implement, chờ approval)

1. **Collector API (additive):** `CarrierRateOutcomeCollectorInterface` thêm
   `recordEligibility(string $carrierCode, string $methodCode, FallbackEligibilityInterface $eligibility)`
   + `getEligibility(): array` (same bracket lifetime, request-scoped, same endCollection reset
   semantics). Outcome và eligibility là HAI record riêng biệt trên cùng (carrier, method) key
   — giữ raw-outcome consumer (Launchpad fallback gate hiện tại, GHTK) BC hoàn toàn.
2. **Carrier adapter:** sau `execute()`, GHN record CẢ outcome lẫn `decision.getFallbackEligibility()`
   (typed `FallbackEligibility` — KHÔNG rebuild từ reason string).
3. **FallbackCoordinator:** `isMemberEligible()` đổi thành — nếu collector có eligibility
   recorded cho member → consume TRỰC TIẾP (không re-judge); nếu không có → compatibility path
   (legacy re-judge như hiện tại) cho members chưa wire (GHTK).
4. **Compatibility path:** explicit (`$eligibility === null` branch), telemetry: một
   `logger->debug('legacy eligibility re-judge', {carrier})` — có thể metrics sau; KHÔNG chạy
   cho member đã có record; removal plan = xóa khi toàn bộ members wire (GHTK adaptation task).
5. **Fallback price:** vẫn FallbackRateProvider (Launchpad). **Dispatch:** vẫn coordinator —
   một final owner. **Suppression:** any-member-success vẫn chặn trước eligibility consume.
6. **Không parse message/reason** ở coordinator để tái tạo policy — chỉ consume typed record.
7. **FALLBACK_ONLY:** eligibility được service emit mà không chạm resolver/API (đã đúng) —
   transport chỉ mang nó về coordinator.
8. **Fail-closed giữ nguyên:** INVALID_CONFIGURATION / UNSUPPORTED_DESTINATION / UNMAPPED /
   business rejection không tạo eligibility (service đã None) — coordinator consume None = none.
9. **Duplicate records:** record cuối cùng per (carrier, method) wins — nhất quán với outcome
   collector hiện tại.
10. **Tests cần:** collector eligibility round-trip; GHN record-after-execute; coordinator
    consume-vs-legacy 2 path; suppression-với-eligibility; D3 matrix asserts (resolver/
    contributor/API/fallback-provider call counts).
11. **Migration path GHTK:** wire qua execution service khi có directive riêng; cho tới đó
    compatibility path giữ behavior — không ép semantics GHN.

**Vẫn HARD STOP — chỉ implement sau approval.**
