# Evidence — TASK-DFGFZ9 Phase 2 (COD amount ownership → Secomm_Cod)

Date: 2026-09-23 · DEC-TASKDFGFZ9-002 · Plan approved (user acting SA/TL: anchor table GHTK + reject-partial chốt qua AskUserQuestion)

## 1. Contract + P1 policy (Secomm_Cod)

- `Api\CodCollectionDecisionInterface` — 3 trạng thái COLLECTIBLE|NOT_COD|REJECTED + 3 reason
  (CURRENCY_UNSUPPORTED|PARTIAL_SHIPMENT|COD_ALREADY_COLLECTED); VO `Model\CodCollectionDecision`
  private-ctor + static factories + LogicException guards (mirror CarrierRateOutcome).
- `Api\CodCollectionPriorInterface` + `Model\CodCollectionPrior` — caller-supplied prior
  (amount/currency/`getProviderShipmentReference()` opaque); CALLER owns same/different
  shipment identity, RESOLVER owns policy → contract không hardcode single-shipment.
- `Api\CodCollectionResolverInterface::resolve(Order, Shipment, ?Prior)` + P1 impl
  `Model\SingleCollectionCodResolver` — evaluation order: prior>0 (shipment khác) → REJECT;
  identification (CodPaymentMethodResolverInterface nội bộ) → notCod; order currency ≠ VND →
  REJECT (no conversion); grand_total ≤ 0 → notCod; partial (logic move verbatim từ
  Ghtk DefaultCodAmountResolver cũ) → REJECT; else COLLECTIBLE grand_total VND.
  `SUPPORTED_CURRENCY = 'VND'` là private const của P1 impl (VN-scope fact).
- di.xml: preference `CodCollectionResolverInterface → SingleCollectionCodResolver`.

## 2. Consumer: Secomm_Ghn (sequence += Secomm_Cod — đảo ngược forbidden edge, arch Rev v12)

- `GhnShipmentCreationService`: resolve decision SAU SUBMITTED short-circuit, TRƯỚC anchor + POST:
  - existing row `cod_amount > 0` → **frozen replay** (resolver không được gọi — persisted-wins);
  - else fresh resolve với prior từ `findCollectedPrior(orderId, excludeShipmentId)`
    (reference `shipment:{id}`); REJECTED → outcome `COD_REJECTED` — **không insertPending,
    không POST**; > MAX_COD_AMOUNT (50,000,000) → UNAVAILABLE `COD_AMOUNT_EXCEEDS_PROVIDER_LIMIT`
    trước mọi write;
  - amount `(int) round()` → payload `'cod_amount'` (LUÔN emit, 0 = non-COD — sandbox-verified
    shape) + anchor row; existing row amount lệch → `updateCodAmount` (guard `<> SUBMITTED`).
- `GhnCreateRequestBuilder::build()` + param `int $codAmount`; guard [0, 50M] fail-closed.
- Observer: nhánh `STATUS_COD_REJECTED` → log error + shipment comment VISIBLE
  "GHN COD collection rejected (reason): message" + save, KHÔNG attach track (in-flight guard
  giữ re-fire an toàn). Retry CLI in thêm dòng "COD policy: …".

## 3. Consumer: Secomm_Ghtk (anchor table mới + xoá seam cũ)

- **`secomm_ghtk_shipment`** mới (db_schema + whitelist + đã apply lên local DB — DDL:
  `ghtk-shipment-ddl.txt`): `partner_order_code` UNIQUE (`ghtk-{inc}-{seq}`),
  `magento_order_id` indexed, `magento_shipment_id` nullable (always NULL — submit trước
  shipment save), `provider_status` PENDING|SUBMITTED|RECOVERED|FAILED|UNKNOWN,
  `cod_amount` decimal(12,4) default 0, `weight_gram`, `label_id`, `tracking_number`, timestamps.
- `Model\OrderSubmit\ShipmentAnchorRepository`: findByPartnerCode / findFrozenAmount
  (non-FAILED, amount>0) / findCollectedPrior (partner <> current, amount>0, status
  PENDING|SUBMITTED|RECOVERED|UNKNOWN — FAILED excluded) / insertPending (force PENDING) /
  markSubmitted (guard `<> SUBMITTED`; RECOVERED khi duplicate) / markNotSubmitted (FAILED|UNKNOWN only).
- `OrderSubmitService`: constructor thay `CodAmountResolverInterface` →
  `CodCollectionResolverInterface` + `ShipmentAnchorRepository`. Flow: frozen amount →
  resolveCod (prior feed → decision; REJECTED → LocalizedException "COD collection rejected
  (reason): message" — native label flow abort, không row) → payload map → insertPending
  ngay trước POST (freeze amount) → POST single-attempt → mark mapping:
  technical/malformed/identity-conflict/incomplete-identity → UNKNOWN;
  business/ORDER rejection → FAILED; success → SUBMITTED/RECOVERED.
- **Xoá**: `DefaultCodAmountResolver.php`, `CodAmountResolverInterface.php`, preference di.xml
  (+ `DefaultCodAmountResolverTest.php`) — GHTK không còn giữ phép tính COD amount; seam duy
  nhất là `Secomm\Cod\Api\CodCollectionResolverInterface` (override tại đó).

## 4. Migration proof — REAL DB (local `launchpad`, sau khi patch đã chạy từ Phase 1)

> **REVISED by phase 3 (2026-09-23, DEC-TASKDFGFZ9-003):** the config surface + this migration
> patch were REMOVED for the pre-release product state (no client data to preserve; `isCod()`
> defaults to hardcoded `cashondelivery`). This section is HISTORY — the DataPatch
> `MigrateLegacyCodPaymentMethodConfig` no longer exists in the codebase.

| Case | Hành động | Kết quả |
|---|---|---|
| Copy case | INSERT legacy row `secomm_shippingcore/cod/payment_methods` = `cashondelivery` (default scope) → DELETE patch_list entry → `setup:upgrade` | Row mới `secomm_cod/payment_identification/payment_methods` = `cashondelivery` được tạo; legacy row GIỮ NGUYÊN (copy-only); patch entry restored |
| Dest-wins | Đổi dest thành `custom_cod_merchant` → DELETE patch entry → `setup:upgrade` | Dest giữ nguyên `custom_cod_merchant` — KHÔNG bị legacy ghi đè |
| Cleanup | DELETE cả 2 test rows | DB sạch (0 row cả 2 path — trạng thái local như trước test) |

> **REVISED by phase 3:** same — per-carrier prior queries replaced by the Secomm_Cod ledger.

## 5. Prior-query proof — REAL DB (seeded, rồi cleanup)

GHTK (`secomm_ghtk_shipment`): order 900001 có 2 anchor — `ghtk-PROOF1-1` SUBMITTED
cod=1,250,000 + `ghtk-PROOF1-2` FAILED cod=900,000:
- `findCollectedPrior(partner=ghtk-PROOF1-2)` → tìm thấy `ghtk-PROOF1-1` 1,250,000
  (SUBMITTED sibling chặn; FAILED sibling KHÔNG làm nguồn prior).
- `findFrozenAmount(ghtk-PROOF1-1)` → 1,250,000 (retry cùng code replay).

GHN (`secomm_ghn_shipment`): order 900002 có shipment 41 SUBMITTED cod=500,000:
- `findCollectedPrior(order, exclude=42)` → shipment:41 500,000 (shipment khác chặn).
- `findCollectedPrior(order, exclude=41)` → 0 row (retry cùng shipment proceeds).

Proof rows đã DELETE sạch sau verify.

## 6. Test results

- Scoped `Secomm_Cod`: **40 tests / 103 assertions** — 20 cũ (identification/ACL/patch) + 20 mới
  (7 decision VO + 13 P1 resolver: COD grand_total VND; non-COD; unconfigured; USD →
  CURRENCY_UNSUPPORTED; grand_total ≤ 0; partial → REJECT; qty-complete → collectible;
  bundle-parent skip; prior khác shipment → REJECT; prior 0 → bỏ qua; deposit-paid vẫn thu full —
  documents P1 limitation).
- Scoped `Secomm_Ghn`: **397 tests** — builder 9 (cod_amount always-emitted 0 + 125,000; cap
  50M+1 và negative fail-closed; forbidden-list flip giữ cod_failed_amount/insurance_value/
  order_value); service 16 (+7: COD flows payload+anchor; non-COD 0; rejection dừng trước anchor
  + POST; frozen replay resolver `never()`; existing-0 re-resolve; prior reference
  `shipment:{id}`; >50M UNAVAILABLE); observer 8 (+COD_REJECTED: log + comment + save, không track).
- Scoped `Secomm_Ghtk`: **235 tests** — service 17 (+7: rejection aborts trước API + anchor;
  prior từ partner khác aborts; frozen replay không re-decide (pick_money 750000); anchor
  insertPending→markSubmitted order; FAILED vs UNKNOWN mapping; RECOVERED mark; flip test
  double-collection cũ → rejection). `DefaultCodAmountResolverTest` XÓA theo file.
- **Scoped tổng Cod|Ghtk|Ghn: 672 tests / 211,731 assertions — 0F/0E** (9 PHPUnit deprecations =
  framework bootstrap baseline, constant mọi subset).
- `setup:di:compile` GREEN; `setup:upgrade` GREEN (bảng mới + whitelist regenerated).

## 7. Full suite

Baseline trước task: 52E+2F (44 ShippingCore Coverage/Zone = stream WY6WP5/G3K9V2 thiếu/khác
production files, 7 Tracking, 3 FulfillmentCore) — không thuộc task. Kết quả full-suite phase 2:
xem phần chạy cuối (báo cáo trong chat); số lỗi pre-existing KHÔNG tăng.

## 8. QC notes (pending runtime)

- GHN create với `cod_amount` > 0 CHƯA sandbox-verify (docs: Int VND max 50M; `cod_amount=0`
  shape đã verify L8TL6B). Cần 1 sandbox create thật khi QC L3.
- GHTK ORDER_ID_EXIST exact payload vẫn pending TASK-44F7V7 (không đổi ở phase này).
- Admin smoke: shipment COD lần 2 → comment "GHN COD collection rejected (...)" trên shipment +
  GHTK native error trong UI; cần browser verification khi có môi trường đăng nhập.
