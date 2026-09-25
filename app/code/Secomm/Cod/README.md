# Secomm_Cod

**COD identification + collection decision — single owner** (TASK-DFGFZ9, DEC-TASKDFGFZ9-001/002/003).

Module nghiệp vụ nhỏ: sở hữu duy nhất câu trả lời "Magento payment method nào là COD" và
"khi tạo đơn giao hàng, provider có thu tiền cửa không, bao nhiêu, loại tiền nào — hay bị từ
chối". Không chứa allocation/deposit framework; không quyết định việc Magento đã nhận tiền.

## Identification (P1: Magento core COD)

```php
namespace Secomm\Cod\Api;

interface CodPaymentMethodResolverInterface
{
    public function isCod(string $paymentMethodCode): bool;
}
```

- P1 mặc định (`DefaultCodPaymentMethodResolver`): **`cashondelivery`** — payment method COD
  chuẩn Magento (`Magento_OfflinePayments`; method INACTIVE đến khi merchant bật trong admin).
  KHÔNG admin field, KHÔNG config path, KHÔNG payment method riêng.
- Match exact, case-sensitive trên code đã trim; code khác → `false`.
- Đổi policy (CODRisk tương lai): DI preference trên interface — không sửa carrier.

## COD collection decision

```php
namespace Secomm\Cod\Api;

interface CodCollectionResolverInterface
{
    public function resolve(Order $order, Shipment $shipment, ?CodCollectionAttemptInterface $attempt): CodCollectionDecisionInterface;
}
```

Trả MỘT quyết định (COLLECTIBLE | NOT_COD | REJECTED + amount + currency + reason). Carrier
gọi khi tạo đơn provider và **chỉ map kết quả** (GHTK `pick_money`, GHN `cod_amount`) — không
đọc `grand_total`/`base_total_due`, không tự nhận diện COD.

P1 policy (`Model\SingleCollectionCodResolver`, swap qua DI preference):

- thu **một lần** mỗi order bằng **`grand_total` theo `order_currency_code`** — decision trả
  ORDER currency; currency SUPPORT là carrier concern (GHTK/GHN gate VND trước
  recordPending/POST, không convert — DEC-TASKDFGFZ9-004);
- `grand_total = 0` → vẫn là COD (COLLECTIBLE amount 0 — CODRisk vẫn thấy payment method);
  `grand_total < 0` → REJECTED `invalid_order_amount`;
- partial shipment (qty-incomplete) → REJECTED;
- shipment COD thứ hai (cùng carrier HAY khác carrier) → REJECTED `COD_ALREADY_COLLECTED` —
  claim per-order được **engine enforce** (`active_order_claim` UNIQUE; attempt thua cuộc
  nhận `CodClaimConflictException`);
- non-COD shipment không bao giờ bị chặn; KHÔNG deposit support (đơn trả một phần vẫn thu cả
  grand_total — merchant không dùng COD method cho đơn trả một phần).

## Collection ledger (cross-carrier, bypass-proof)

Bảng `secomm_cod_collection` do module này sở hữu. Carrier REPORT attempt của mình
(`CodCollectionLedgerInterface::recordPending` khi amount > 0 — trước anchor insert + POST —
và mirror markSubmitted/markNotSubmitted). Resolver **tự đọc ledger** cho frozen replay
(retry replay amount ban đầu) và prior check → caller không thể bypass rule bằng cách bỏ sót
prior. Provider nhận đơn COD ≠ Magento đã nhận tiền — ledger không bao giờ đánh dấu order paid.

## Consumers

Carrier/consumer nào cần COD identification HOẶC collection decision thì khai báo dependency
`Secomm_Cod` (module.xml sequence) và inject contract:

- `Secomm_Ghtk` — `OrderSubmitService` (decision → `pick_money`; ledger report + mirror).
- `Secomm_Ghn` — `GhnShipmentCreationService` (decision → `cod_amount`; rejection → outcome
  `COD_REJECTED`: log + comment VISIBLE, không tạo đơn).
- `Secomm_ShippingCore` cố ý KHÔNG phụ thuộc module này.
- Forbidden edges: `Secomm_ShippingCore → Secomm_Cod`, `Secomm_Cod → carriers`.

## Run tests

```bash
php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter 'Secomm\\Cod'
```
