# Shipping Cluster — Architecture Audit (Phiên 1: Structure, Ownership, Dependency)

- Repository: `secomm-vn/slaunchpad` · Branch: `development` @ `935b81e1` (working tree có thay đổi chưa commit)
- Ngày: 2026-09-22
- Mode: **READ-ONLY**. Không sửa một dòng code nào trong lúc audit.
- Phạm vi yêu cầu: `Secomm_AddressDropdown`, `Secomm_VietNamAddress`, `Secomm_ShippingCore`, `Secomm_Ghn`, `Launchpad_MageplazaTableRate`, `Mageplaza_TableRateShipping` (3rd-party — chỉ audit bề mặt tiếp xúc).
- Mục tiêu (do TL đặt): (1) code structure có đủ tốt, (2) flow hoạt động, (3) việc sở hữu feature trên từng module, (4) độ phụ thuộc có thỏa OOP.
- Phương pháp: trích xuất cấu trúc bằng máy (709 class/interface, PHP tokenizer-style extractor) → dependency graph mức class → đọc toàn bộ `etc/*.xml` của cụm → đọc các class chốt trên flow. Không đọc 8.3 MB dataset CSV (sampling/tham chiếu gián tiếp).
- Phiên 2 (chưa chạy): flow address-resolution đầu-cuối, GHN create/tracking, chấm điểm chi tiết Mageplaza vendor surface, bảng refactor xếp ưu tiên.

> **Ranh giới audit này:** đây là *baseline audit*, không phải đề xuất refactor đã được duyệt. Theo quy tắc audit-trước-refactor, không thực hiện thay đổi nào cho tới khi TL gate kết quả này.

---

## 0. Kết luận ngắn

Cụm shipping **về tổng thể là kiến trúc tốt trên mức trung bình của một dự án Magento thương mại** — có tầng contract thật (`ShippingCore/Api`, 62 file interface), dependency graph **acyclic**, hướng phụ thuộc đúng chiều (adapter → core, không có reverse dependency), và DI được viết có chủ đích kèm lý do kiến trúc ghi ngay trong `di.xml`.

Ba vấn đề thực sự đáng xử lý, theo thứ tự nghiêm trọng:

| # | Vấn đề | Mức | Bản chất |
|---|---|---|---|
| F-01 | Fallback có **hai đường kích hoạt song song**; đường mà ShippingCore thiết kế ra **không có consumer nào trong production** | **S1** | Ownership + dead contract |
| F-02 | Vòng đời `beginCollection()/endCollection()` của contract ShippingCore **do module glue Launchpad sở hữu duy nhất** | **S1** | Ownership inversion |
| F-03 | `Secomm\Ghn\Model\Carrier\Ghn` — 26 constructor dependency / 564 dòng | **S2** | SRP |

Còn lại là các vấn đề S2/S3 về DIP leak, override bằng `preference`, và trùng lặp code — liệt kê đầy đủ ở §4–§6.

---

## 1. Inventory (đã loại trừ `Test/`)

| Module | Tổng | class | interface | abstract | final | plugin | preference |
|---|---|---|---|---|---|---|---|
| Secomm_ShippingCore | 160 | 109 | **51** | 0 | 57 | 0 | 29 |
| Secomm_AddressDropdown | 129 | 116 | 13 | 0 | 0 | 9 | 13 |
| Secomm_Ghn | 100 | 98 | 2 | 1 | 25 | 0 | 1 |
| Secomm_VietNamAddress | 58 | 46 | 12 | 0 | 3 | 6 | 5 |
| Mageplaza_TableRateShipping (vendor) | 64 | 64 | 0 | 1 | 2 | 0 |
| Launchpad_MageplazaTableRate | 30 | 30 | **0** | 1 | 1 | 5 | 5 |

Khối lượng source (không tính test/dataset): 2.33 MB / 707 file.

**Đọc bảng này cho đúng:** số interface thấp ở `Secomm_Ghn` (2) và `Launchpad_MageplazaTableRate` (0) **không tự nó là lỗi**. Cả hai là module lá (leaf adapter / composition), chúng *tiêu thụ* abstraction chứ không cần *xuất bản* abstraction. Xem §4 để biết chúng tiêu thụ có đúng abstraction hay không — đó mới là phép thử DIP thật.

---

## 2. Dependency graph — mức module

```
Mageplaza_TableRateShipping  (vendor, 0 interface)
            ▲ 10 CONCRETE + 4 preference override
            │
Launchpad_MageplazaTableRate ──4 abstract──▶ Secomm_ShippingCore
            └──1 abstract──────────────────▶ Secomm_VietNamAddress
                                                    ▲
Secomm_Ghn ──16 abstract / 9 CONCRETE───▶ Secomm_ShippingCore ──7 abstract──┘
           ──7 abstract─────────────────▶ Secomm_VietNamAddress
                                                    │
                                     1 abstract / 6 CONCRETE
                                                    ▼
                                          Secomm_AddressDropdown
```

**Không có cycle** ở mức module, và cũng không có cycle ở mức class trong cụm. Hướng phụ thuộc luôn trỏ vào trong (adapter → core → data). `module.xml` `<sequence>` khớp với phụ thuộc thực tế ở cả 6 module — không có phụ thuộc ẩn nào không khai báo.

`Secomm_ShippingCore` **không hề phụ thuộc ngược** vào bất kỳ carrier hay module Launchpad nào. Điều này được bảo vệ có chủ đích bằng các quy tắc "no default implementation" ghi rõ trong `app/code/Secomm/ShippingCore/etc/di.xml` — ví dụ `CarrierPhysicalLimitInterface`, `RealtimeCarrierRateContributorInterface` và carrier API profile contract cố ý **không** có `<preference>` để một preference ở core không biến thành reverse dependency. Đây là điểm kiến trúc mạnh nhất của cụm và cần được giữ.

**Ghi chú phạm vi:** trong cùng cụm còn có `Secomm_Ghtk` (104 file, 83 class) đang tiêu thụ đúng bộ contract này (`CarrierAddressHandoffServiceInterface`, `RuntimeAddressContextBuilderInterface`, `CarrierHttpClientInterface`), cùng `Secomm_Ahamove`, `Secomm_GiaoHangNhanh`, `Secomm_GhnAddressMapper`, `Secomm_FulfillmentCore`, `Secomm_Tracking`. Chúng nằm ngoài scope TL giao nhưng **thuộc cùng một biên kiến trúc** — mọi kết luận về contract ở đây tác động sang chúng.

---

## 3. Flow — Rate collection & Fallback (đã trace)

### 3.1 Đường chạy thực tế trong production

```
Quote\Address::requestShippingRates()
   └─▶ Magento\Shipping\Model\Shipping::collectRates()          ← seam duy nhất của RateCollectorInterface
         └─▶ [around] Launchpad\...\Plugin\Shipping\CollectRatesPlugin   sortOrder=100
               1. outcomeCollector->beginCollection()                    ← MỞ bracket của ShippingCore
               2. proceed()  → Magento chạy toàn bộ carrier
                     └─▶ Secomm\Ghn\Model\Carrier\Ghn::collectRates()
                           └─▶ CarrierRateExecutionServiceInterface      ← ShippingCore gate
                                 eligibility → mode → origin → address policy → contributor
                           └─▶ outcomeCollector->record(...)             ← carrier REPORT, không quyết định
                     └─▶ Mageplaza\...\Carrier\TableRate::collectRates() (gate `carriers/mptablerate/active`)
               3. MethodVisibilityFilter->filter($result)                ← lọc hiển thị theo customer group
               4. FallbackCoordinator->appendFallbackRates($request, $result)
                     ├─ đọc getDecisionRecords() + getOutcomes() của bracket này
                     ├─ per-method gate: no member → bỏ; any SUCCESS → suppress;
                     │  ≥1 eligible → tính giá nội bộ và append
                     └─ FallbackEligibilityPolicyInterface (ShippingCore sở hữu POLICY)
               5. finally → outcomeCollector->endCollection()            ← ĐÓNG bracket
```

Thiết kế của seam này là **đúng và được lập luận tốt**: `Magento\Shipping\Model\Shipping` là preference duy nhất của `RateCollectorInterface`, nên mọi kênh (cart/checkout storefront, REST, GraphQL estimate, admin order create) đều đi qua đúng một điểm. Bracket đóng trong `finally` nên một carrier ném exception không làm rò outcome sang lần collect kế tiếp trong cùng HTTP request. Phân vai "carrier **báo cáo** status + structured reason, ShippingCore **quyết định** eligibility, không parse message" là ranh giới trách nhiệm sạch.

### 3.2 F-01 — Đường thứ hai đã chết (S1)

`Secomm_ShippingCore` thiết kế sẵn một đường fallback theo hợp đồng:

```
ServiceLevelRateOrchestratorInterface
   └─▶ ServiceLevelRateAggregator     (gom outcome theo service level)
   └─▶ FallbackPolicyInterface        (ConfigurableFallbackPolicy)
   └─▶ FallbackRateProviderPool       (Launchpad đăng ký `launchpad_mptablerate`)
         └─▶ Launchpad\...\Model\FallbackRateProvider
```

`Launchpad_MageplazaTableRate/etc/di.xml` vẫn đăng ký provider vào pool này, và chính comment trong file thừa nhận: *"The internal provider stays registered in the ShippingCore fallback provider pool … the production trigger is the outer plugin."*

**Kiểm chứng bằng grep toàn `app/code` (loại trừ `Test/`):**

| Contract | Consumer ngoài chính nó |
|---|---|
| `ServiceLevelRateOrchestratorInterface` | **0** |
| `ServiceLevelRateAggregator` | **0** |
| `FallbackRateProviderPool` | chỉ `ServiceLevelRateOrchestrator` (bản thân đã không có consumer) |

→ Toàn bộ nhánh `ServiceLevel` + `FallbackRateProviderPool` + `FallbackRateProvider` (231 dòng) **không được gọi ở bất kỳ đường chạy production nào**. Nó chỉ sống qua unit test.

**Vì sao đây là S1 chứ không phải dead code vô hại:**
1. **Ownership nhập nhằng.** Có hai câu trả lời khác nhau cho câu hỏi "ai quyết định fallback": `ConfigurableFallbackPolicy` (theo service level, ShippingCore) và `FallbackCoordinator` (theo per-method group, Launchpad). Chúng dùng hai mô hình dữ liệu khác nhau. Dev tiếp theo sửa đúng file, sai đường.
2. **Nguy cơ phân kỳ âm thầm.** Test xanh trên đường chết tạo cảm giác an toàn giả cho đường sống.
3. **Đường sống lại nằm ở module glue**, không ở core (xem F-02).

**Khuyến nghị (chờ TL gate):** chọn một trong hai — (a) nối `ServiceLevelRateOrchestrator` vào seam thật và rút `FallbackCoordinator` về vai trò provider, hoặc (b) chính thức khai tử nhánh ServiceLevel, xoá pool/provider/orchestrator và ghi một DEC nói rõ per-method group là mô hình fallback duy nhất. **Không được để nguyên trạng hai đường.** Phương án (a) đúng về kiến trúc, (b) rẻ và trung thực với thực tế đang chạy.

### 3.3 F-02 — Ownership inversion trên bracket (S1)

`CarrierRateOutcomeCollectorInterface` là contract của **ShippingCore**, có ngữ nghĩa vòng đời rõ ràng (`beginCollection()` … `endCollection()`, bracket per-execution). Nhưng grep toàn repo cho thấy **nơi duy nhất mở và đóng bracket là `Launchpad\MageplazaTableRate\Plugin\Shipping\CollectRatesPlugin`**.

Hệ quả cụ thể cần verify ở Phiên 2:

- Nếu `Launchpad_MageplazaTableRate` bị disable (nó là module bridge tuỳ chọn cho một vendor 3rd-party — hoàn toàn có thể bị tắt), bracket **không bao giờ mở**. Khi đó `Secomm_Ghn` vẫn gọi `outcomeCollector->record(...)` trong `collectRates()`. Cần xác định `CarrierRateOutcomeCollector` xử lý `record()` ngoài bracket ra sao: buffer vô hạn theo process, hay no-op an toàn.
- Ngữ nghĩa "per execution" của một contract core đang phụ thuộc vào sự tồn tại của một module bridge — đây là quan hệ sở hữu ngược chiều so với toàn bộ phần còn lại của kiến trúc (vốn rất kỷ luật về chiều phụ thuộc).

**Khuyến nghị:** ShippingCore tự sở hữu seam vòng đời (một plugin `around collectRates` riêng ở ShippingCore, sortOrder thấp hơn, chỉ làm nhiệm vụ mở/đóng bracket). Launchpad giữ nguyên plugin của nó nhưng chỉ còn lo filter + append. Việc này cũng mở đường cho phương án (a) ở F-01.

---

## 4. Độ phụ thuộc có thỏa OOP không

### 4.1 Tổng quan — phần lớn là ĐẠT

`Secomm_Ghn` → `Secomm_ShippingCore`: **16 phụ thuộc qua interface / 9 qua concrete**. Tỷ lệ này tốt cho một adapter. Các đường chính (`CarrierRateExecutionServiceInterface`, `CarrierAddressHandoffServiceInterface`, `RuntimeAddressContextBuilderInterface`, `CarrierRateOutcomeCollectorInterface`) đều đi qua `Api\`. `Launchpad_MageplazaTableRate` → `ShippingCore`: **4/5 qua interface**.

Đây là điểm tôi phải đính chính so với nhận định sơ bộ trước khi đo: tôi đã nghi `Secomm_Ghn` yếu về DIP vì chỉ khai báo 2 interface. Số liệu cho thấy ngược lại — Ghn *tiêu thụ* abstraction đúng cách; việc nó khai báo ít interface là hợp lý với vai trò leaf adapter.

### 4.2 F-04 — ShippingCore có bề mặt API không hoàn chỉnh (S2)

9 phụ thuộc concrete của Ghn vào ShippingCore rơi vào hai loại khác nhau:

| Class ShippingCore bị dùng trực tiếp | Có interface? | Đánh giá |
|---|---|---|
| `Model\Tracking\ShipmentTrackingProcessor` (2×) | **CÓ** — `Api\Tracking\CarrierTrackingProcessorInterface` | **Vi phạm DIP thật.** Interface tồn tại, có `<preference>`, nhưng Ghn inject concrete. Sửa được ngay, chi phí thấp. |
| `Model\Physical\StoreWeightConverter` (2×) | không | Bề mặt API thiếu |
| `Model\Tracking\TrackingReconciliationService` | không | Bị Ghn dùng làm `virtualType` base (idiom Magento hợp lệ, nhưng biến class thành API de-facto) |
| `Model\Physical\ShipmentPhysicalPersister` | không | Bề mặt API thiếu |
| `Model\Physical\ConfiguredDefaultPackageDimensions` | không | Bề mặt API thiếu |
| `Model\ShippingContextFactory` | không | Factory — chấp nhận được |
| `Model\ResourceModel\CarrierTrackingState\CollectionFactory` | không | **Carrier đọc thẳng persistence layer của core** — rò tầng |

Vấn đề gốc: ShippingCore có 51 interface nhưng **bề mặt mà carrier thực sự cần lại chưa được phủ hết**. Mỗi chỗ thiếu buộc carrier phải bám vào `Model\`, biến class nội bộ thành API ngầm — khoá cứng khả năng thay đổi của core mà không có hợp đồng nào cảnh báo.

### 4.3 F-05 — `VietNamAddress` đọc thẳng persistence của `AddressDropdown` (S2)

Cả 6 phụ thuộc concrete `VietNamAddress` → `AddressDropdown` đều là **cùng một class**: `Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory`, dùng trong 6 plugin `ValidateVietNamWard` (Customer/Address, Inventory, Sales, Adminhtml/Config, Cart, Quote).

Đây là vi phạm layering rõ: module A với vào **resource-model collection** của module B, bỏ qua hoàn toàn `AddressDropdown\Api\` (13 interface, trong đó có `CityRepositoryInterface` và `LocationHierarchyProviderInterface`). Hệ quả: mọi thay đổi schema/collection bên `AddressDropdown` có thể làm vỡ validation ward ở 6 điểm trong `VietNamAddress` mà không có compile error nào.

### 4.4 F-06 — 6 bản sao song song của `ValidateVietNamWard` (S2)

Sáu class cùng tên, cùng mục đích, **không có base class / trait / service chung**:

| Plugin context | LOC |
|---|---|
| `Plugin/Adminhtml/Config/ValidateVietNamWard.php` | 129 |
| `Plugin/Quote/ValidateVietNamWard.php` | 105 |
| `Plugin/Customer/Address/ValidateVietNamWard.php` | 94 |
| `Plugin/Sales/ValidateVietNamWard.php` | 91 |
| `Plugin/Inventory/ValidateVietNamWard.php` | 89 |
| `Plugin/Cart/ValidateVietNamWard.php` | 88 |

~600 dòng logic validation gần-song-song. Sáu bản thân đã phân kỳ (LOC lệch 88→129), nghĩa là chúng **đã** trôi khỏi nhau. Một luật validation ward mới phải sửa đúng 6 chỗ, và không có cơ chế nào bắt lỗi nếu quên một chỗ.

Nghiệp vụ hợp lệ ở đây là 6 *điểm gắn* (6 seam khác nhau của Magento), không phải 6 *bản cài đặt*. Đúng ra phải là một `WardValidator` service + 6 plugin mỏng chỉ lo trích địa chỉ ra khỏi subject.

### 4.5 F-07 — Override class core Magento bằng `preference` (S2)

`Secomm_AddressDropdown` khai báo:

```
<preference for="Magento\Directory\Model\ResourceModel\Country\CollectionFactory" type="..."/>
```

`preference` trên class core là global và **độc quyền** — bất kỳ module nào khác (bao gồm module tương lai, hoặc extension bên thứ ba) cũng override class này thì một trong hai bị nuốt im lặng, không có cảnh báo. 12 preference còn lại của module là binding `Api\*Interface` → impl của chính nó, hoàn toàn hợp lệ; chỉ mục này lệch chuẩn.

29 preference của `ShippingCore` đã kiểm tra mẫu: **toàn bộ là `Secomm\ShippingCore\Api\*Interface` → impl của chính nó**. Đây là DI đúng chuẩn, không phải override core. Không có phát hiện nào ở đây.

### 4.6 F-08 — Bề mặt tiếp xúc với vendor Mageplaza (S2)

`Launchpad_MageplazaTableRate` mở rộng vendor bằng **4 `<preference>` thay thế class**:

| Class vendor bị thay | Bằng |
|---|---|
| `Block\Adminhtml\Method\Edit\Tabs` | `Block\Adminhtml\Method\Edit\MethodTabs` |
| `Block\...\Tab\Rate\Form` | `...\Tab\Rate\CityForm` |
| `Block\...\Tab\Rate\Grid` | `...\Tab\Rate\CityGrid` |
| `Model\Import` | `Model\MptablerateImport` |
| `Model\Method` | `Model\LaunchpadMethod` |

Cộng 4 plugin trên `ResourceModel\Rate`, `ResourceModel\Method`, `ResourceModel\Rate\Collection`, `Controller\Adminhtml\Method\Save`.

Đánh giá cân bằng: mục tiêu "không sửa source vendor" là **đúng** và comment trong `di.xml` nói rõ chủ đích đó. Nhưng `preference` lên class vendor cụ thể có hai chi phí: (1) độc quyền — không module nào khác extend được cùng class; (2) mỗi bản nâng cấp Mageplaza đổi constructor signature của 5 class đó sẽ vỡ ở runtime, không có test nào của Launchpad bắt được vì vendor không có interface để pin. Vendor `Mageplaza_TableRateShipping` có **0 interface**, nên không có cách nào tốt hơn hẳn — đây là chi phí cố hữu của việc mở rộng một vendor không thiết kế cho mở rộng.

**Khuyến nghị:** giữ pattern, nhưng bổ sung một contract test pin constructor signature của 5 class vendor, chạy trong CI. Chi phí thấp, biến một lỗi runtime im lặng thành một lỗi CI ồn ào.

---

## 5. SRP — các điểm nóng

| File | ctor deps | LOC | Nhận định |
|---|---|---|---|
| `Secomm/Ghn/Model/Carrier/Ghn.php` | **26** | 564 | **F-03, S2.** 26 collaborator trong một class là vi phạm SRP rõ ràng. Class này vừa là Magento carrier adapter, vừa điều phối rate, vừa nói chuyện với execution service, vừa lo context/capability. Đây là điểm refactor có lợi nhất của cả cụm. |
| `Secomm/VietNamAddress/Model/Import/VnAddressSchemeImporter.php` | 11 | **854** | Class lớn nhất cụm. Import/migration nên là orchestrator + các step nhỏ. |
| `Secomm/AddressDropdown/Model/Import/Hierarchy/HierarchyImportService.php` | 1 | 596 | 596 dòng với 1 dependency → logic nhét inline, khó test từng nhánh. |
| `Secomm/Ghn/Model/Shipment/GhnShipmentCreationService.php` | 12 | 338 | Cần theo dõi. |
| `Mageplaza/.../Controller/Adminhtml/Method/RateMassDelete.php` | 14 | 117 | Vendor — không sửa, chỉ ghi nhận. |

Phần còn lại của cụm có phân bố dependency lành mạnh (đa số ≤ 8).

Điểm cộng đáng ghi nhận: `ShippingCore` có **57/109 class `final`** và dùng `readonly` promoted properties xuyên suốt — immutability và đóng kín kế thừa được thực thi có kỷ luật, đúng hướng "compose, don't inherit".

---

## 6. Sở hữu feature — ma trận

| Feature | Sở hữu (de jure) | Chạy thật (de facto) | Trạng thái |
|---|---|---|---|
| Canonical VN address data (dataset, scheme 2025/pre-2025) | Secomm_VietNamAddress | ✔ khớp | OK |
| Address dropdown UI + hierarchy + import/export | Secomm_AddressDropdown | ✔ khớp | OK |
| Ward validation | Secomm_VietNamAddress | ✔ nhưng ×6 bản sao, và đọc thẳng ResourceModel của AddressDropdown | **F-05, F-06** |
| Address resolution → canonical handoff cho carrier | Secomm_ShippingCore | ✔ khớp (Ghn, Ghtk đều qua `Api\Address\*`) | OK |
| Carrier eligibility / zone / destination scope | Secomm_ShippingCore | ✔ khớp | OK |
| Rate execution gating (eligibility→mode→origin→policy) | Secomm_ShippingCore | ✔ khớp (`CarrierRateExecutionServiceInterface`, Ghn consume) | OK |
| Outcome collection bracket (vòng đời) | Secomm_ShippingCore (contract) | ✘ **Launchpad_MageplazaTableRate mở/đóng, độc nhất** | **F-02** |
| Fallback **policy** (ai đủ điều kiện) | Secomm_ShippingCore (`FallbackEligibilityPolicyInterface`) | ✔ Launchpad consume đúng | OK |
| Fallback **orchestration** (khi nào chạy) | Secomm_ShippingCore (`ServiceLevelRateOrchestrator`) | ✘ **0 consumer — chết**; thực tế `Launchpad\FallbackCoordinator` quyết định | **F-01** |
| Fallback **pricing source** | Mageplaza_TableRateShipping (bảng giá) | ✔ qua Launchpad bridge | OK |
| TableRate như một shipping method thường | Mageplaza_TableRateShipping | ✔ gate `carriers/mptablerate/active` giữ nguyên chỗ vendor đặt | OK |
| Mở rộng TableRate (city scope, customer group, import) | Launchpad_MageplazaTableRate | ✔ qua preference + plugin | **F-08** (chấp nhận được, cần contract test) |
| GHN rate / create / cancel / return / tracking | Secomm_Ghn | ✔ khớp | OK |
| GHN ↔ canonical address mapping dataset | Secomm_Ghn (`data/`, CanonicalCsvProvider) | ✔ nhưng SSOT là CSV của VietNamAddress vì DB provider có defect `parent_code = NULL` | **F-09, S3** — nợ kỹ thuật đã ghi nhận trong `di.xml`, chưa có ticket |
| COD payment identification | Secomm_ShippingCore | ✔ khớp | OK |
| Shared carrier HTTP transport | Secomm_ShippingCore (`CarrierHttpClientInterface`) | ✔ Ghtk dùng; **Ghn có client riêng** (`GhnApiClientInterface`) | **F-10, S3** — hai đường HTTP song song giữa hai carrier cùng cụm |

**Nhận định về ownership:** ranh giới sở hữu được *thiết kế* rất rõ và phần lớn *được tôn trọng*. Toàn bộ sai lệch tập trung vào đúng một chỗ: **biên giới giữa `ShippingCore` và `Launchpad_MageplazaTableRate` trong chuyện fallback** (F-01 + F-02). Đây không phải nhiều lỗi rải rác mà là một đường nứt duy nhất, xử lý gọn được trong một đợt.

---

## 7. Chấm điểm structure theo module

| Module | Điểm | Lý do |
|---|---|---|
| **Secomm_ShippingCore** | **A−** | Tầng contract thật (51 interface), `final`+`readonly` nhất quán, DI có lập luận kiến trúc ghi trong file, chủ động chặn reverse dependency. Trừ điểm: bề mặt API chưa phủ hết nhu cầu carrier (F-04); một nhánh feature lớn chết (F-01); không tự sở hữu vòng đời contract của mình (F-02). |
| **Secomm_Ghn** | **B** | Tiêu thụ abstraction đúng (16/25), phân tách Rate/Create/Tracking/Mapping/Capability rõ ràng, logger có scrub token. Trừ điểm: `Carrier\Ghn` 26 deps (F-03); 9 concrete leak (F-04); HTTP client riêng (F-10). |
| **Launchpad_MageplazaTableRate** | **B−** | Là composition module nên 0 interface là hợp lý; seam chọn đúng và lập luận tốt; `finally` đúng chỗ. Trừ điểm: đang giữ quyền sở hữu vượt vai trò (F-02); duy trì một đường đăng ký đã chết (F-01); 5 preference lên vendor không có contract test (F-08). |
| **Secomm_AddressDropdown** | **B−** | Có `Api\` (13 interface), tách Command/Query/Mapper/Ui rành mạch. Trừ điểm: override `CollectionFactory` của core (F-07); 116 class / 13 interface với ResourceModel bị module khác dùng trực tiếp (F-05); `HierarchyImportService` 596 dòng. |
| **Secomm_VietNamAddress** | **C+** | Có `Api\` nhưng không dùng nó khi nói chuyện với `AddressDropdown` (F-05); 6 bản sao validation (F-06); `VnAddressSchemeImporter` 854 dòng. Đây là module yếu nhất trong nhóm Secomm. |
| **Mageplaza_TableRateShipping** | *không chấm* | Vendor 3rd-party. Ghi nhận: 0 interface, 64 class, controller admin fan-in cao (10–14 deps). Không thiết kế cho mở rộng — đây là ràng buộc mà Launchpad phải chịu, không phải lỗi của Launchpad. |

---

## 8. Việc cần làm (đề xuất — CHỜ TL GATE, chưa thực hiện)

| # | Việc | Mức | Effort | Ghi chú |
|---|---|---|---|---|
| 1 | Quyết định F-01: nối orchestrator vào seam **hoặc** khai tử nhánh ServiceLevel. Ghi DEC. | S1 | M | Cần quyết định kiến trúc của TL trước khi code. |
| 2 | Chuyển quyền mở/đóng bracket về ShippingCore (F-02) | S1 | S | Verify trước: `record()` ngoài bracket hiện xử lý thế nào. |
| 3 | Tách `Secomm\Ghn\Model\Carrier\Ghn` (26 deps → ≤8) | S2 | L | Ghn có 312 test — có lưới an toàn để refactor. |
| 4 | Bổ sung interface cho bề mặt ShippingCore mà carrier đang dùng concrete (F-04); đổi `ShipmentTrackingProcessor` → `CarrierTrackingProcessorInterface` ngay | S2 | S→M | Riêng phần tracking processor là fix 2 dòng. |
| 5 | Gom 6 `ValidateVietNamWard` về 1 service + 6 plugin mỏng (F-06) | S2 | M | Cần test trước vì 6 bản đã phân kỳ — không được giả định chúng tương đương. |
| 6 | `VietNamAddress` dùng `AddressDropdown\Api\` thay ResourceModel (F-05) | S2 | M | Làm cùng #5. |
| 7 | Contract test pin constructor signature 5 class vendor Mageplaza (F-08) | S2 | S | Đổi lỗi runtime im lặng thành lỗi CI. |
| 8 | Bỏ preference lên `Magento\Directory\...\CollectionFactory` (F-07) | S2 | S | Thay bằng plugin hoặc DI argument. |
| 9 | Mở ticket cho defect `parent_code = NULL` (F-09) | S3 | S | Đã ghi trong `Ghn/etc/di.xml` nhưng chưa có ticket. |
| 10 | Quyết định Ghn có chuyển sang `CarrierHttpClientInterface` không (F-10) | S3 | M | Có thể là quyết định có chủ đích — cần xác nhận, không mặc định là lỗi. |

**Không mục nào được thực hiện trong audit này.** Thứ tự đề xuất: #2 → #4 (rẻ, giảm rủi ro ngay) → #1 (cần quyết định) → #7, #8 → #5, #6 → #3.

---

## 9. Bằng chứng & tái lập

- Extractor cấu trúc: `scratchpad/extract.php` → `structure.txt` (709 bản ghi: kind|modifier|FQCN|extends|implements|ctor-deps|path|LOC).
- Phân tích: `scratchpad/analyze.py` (inventory, cross-module abstract-vs-concrete, DIP candidates, ctor fan-in, LOC).
- Các con số coupling trong §2/§4 là **đếm bằng máy trên toàn bộ `use` + `extends` + `implements` + constructor type hint**, đã loại `Test/`.
- Kết luận "0 consumer" ở §3.2 dựa trên `grep -rln` toàn `app/code`, loại `Test/` và loại chính file định nghĩa.
- Chưa verify bằng runtime: hành vi của `CarrierRateOutcomeCollector::record()` khi gọi ngoài bracket (F-02) — **cần chạy thử ở Phiên 2 trước khi kết luận mức độ ảnh hưởng.**

## 10. Phiên 2 — phạm vi còn lại

1. Trace đầy đủ flow address resolution: `AddressDropdown` → `VietNamAddress` → `ShippingAddressResolutionManager` → `CarrierAddressHandoffService` → adapter carrier.
2. Trace GHN create / cancel / return / tracking-reconciliation.
3. Verify runtime F-02 (bracket ngoài phạm vi).
4. Đánh giá `Secomm_Ghtk` + `Secomm_Ahamove` trên cùng bộ contract (xác nhận contract có thật sự carrier-neutral hay chỉ vừa đủ cho GHN).
5. Kiểm tra chồng lấn `Secomm_Ghn` vs `Secomm_GiaoHangNhanh` vs `Secomm_GhnAddressMapper` (ba module cùng miền GHN — nghi ngờ legacy chưa gỡ).
