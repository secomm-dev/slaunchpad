# Ke hoach trien khai: SL-014 - Apply VN City dropdown tren admin MSI Source form

> Mode B | Tier 1, reassess Tier 2 neu MSI Source cap du lieu cho shipping/pickup origin.
> **Status: Draft - cho TL approval. Khong duoc xem implementation la complete truoc QC.**
> Ticket: [SL-014](../tickets/SL-014-apply-address-dropdown-admin-msi-source.md) | Spec: [admin-vn-address-msi-source-form](../specs/admin-vn-address-msi-source-form.md)

## Metadata

| Field | Value |
|---|---|
| Feature | FEAT-007 |
| Workflow mode | B |
| Architecture | DEC-025, DEC-019, DEC-020 |
| Reviewer | TL/user - pending Level-2 approval |
| Validation level | L2; elevate L3 neu Source dong vai tro shipping/pickup origin |
| Date | 2026-08-07 |

## 1. Hướng tiếp cận

Dung UI-component adapter rieng trong `Secomm_VietNamAddress` de extend field
`address.city` cua `inventory_source_form`. Adapter chi thay doi presentation:
ngoai VN render native input, voi VN render select options theo `region_id`.
Ca hai cung lien ket voi `data.general.city`, vi vay Magento core Source data
provider va repository persist vao `inventory_source.city` ma khong can mapper,
schema hay save observer moi.

`Secomm_AddressDropdown` chi duoc reuse nhu data mechanism qua
`GetListCity(area=adminhtml)`. Country gate va VN 2-level behavior nam trong
`Secomm_VietNamAddress`, tuan DEC-019/025. Khong render `sub_city` cho VN.

### Alternatives da loai

- Extend `Magento_Ui/js/form/provider` global: co the tac dong customer/admin surface khac; khong can thiet cho Source rieng.
- Them field `city_select`: tao hai data field va rui ro source save khong persist native `city`.
- Static source model: khong the filter options theo province runtime.
- Schema `ward_id`/column moi: ngoai scope DEC-020.

## 2. Files affected

| File | Thay doi | AC |
|---|---|---|
| `Secomm/VietNamAddress/view/adminhtml/ui_component/inventory_source_form.xml` | Merge field `address.city` voi VN component | AC-1, AC-2, AC-3 |
| `Secomm/VietNamAddress/view/adminhtml/web/js/form/element/source-city.js` | Observe country/region, fetch options, stale request guard, persist native observable | AC-1, AC-2, AC-3, AC-5 |
| `Secomm/VietNamAddress/view/adminhtml/web/template/form/element/source-city.html` | Render input non-VN / select VN dung cung `inputName` va `value` | AC-1, AC-2, AC-3 |
| `Secomm/AddressDropdown/Model/Resolver/GetListCityGraphql.php` | Khong sua, reuse read-only | AC-1 |
| Source repository/schema core | Khong sua, verify persistence | AC-2, AC-4 |

## 3. Steps

1. **Baseline audit**
   - Verify core form `inventory_source_form`: `country_id`, `region_id`, `city` deu co scope `data.general`.
   - Verify Source data provider tra `general.city` va Source repository persist native `city`.
   - Verify MSI Source dang duoc su dung va danh gia Source co cap du lieu rate/pickup hay khong.

2. **UI component merge**
   - Merge duy nhat field `address.city`, giu label va data scope core.
   - Dung component `source-city`; khong override `country_id`, `region_id`, template form hay provider core.

3. **Country/region cascade**
   - Observe `country_id` va `region_id` qua UI imports.
   - Country `VN`: load City options theo region. Doi country/province clear city do cu khong hop le.
   - Hydrate edit: giu city da luu, sau khi options load thi preselect; neu city khong thuoc province thi clear.
   - Dung request ID de bo response GraphQL stale; error thi de select trong, khong throw client error.
   - Non-VN: city options clear va native input render lai.

4. **Persist and validation verification**
   - Confirm select bind truc tiep `data.general.city`; save Source va reload de verify `inventory_source.city` ward string.
   - Chi them server-side SourceRepository validation neu QC/API flow cho thay native save co the nhan ward sai; khi do can TL review vi Source co the la fulfillment origin.

5. **Pre-review and QC**
   - Static checks: XML parse, JS syntax, diff check.
   - Manual QC: create/edit VN, non-VN, country switch, rapid province switch, source listing/assignment.
   - Neu Source feed shipping/pickup: test checkout/rate origin va elevate L3.

## 4. Regression risks

| Risk | Severity | Mitigation |
|---|---|---|
| City value khong serialize sau khi doi sang select | High | Cung observable/dataScope native, QC DB/reload |
| Async imports clear city edit | Medium | Chi clear khi value country/region thay doi sau hydration |
| GraphQL response cu ghi de list moi | Medium | Request ID va region/country check truoc set options |
| Break non-VN input | Medium | Country gate + manual US QC |
| Source origin anh huong fulfillment | High | Verify downstream va TL reassessment |

## 5. Test approach

- **TC-1**: Create Source VN, chon Province va City; save/reload preselect dung.
- **TC-2**: Edit Source VN co city hop le; city khong bi reset trong hydration.
- **TC-3**: Doi Province VN; city cu clear va danh sach moi dung province.
- **TC-4**: Doi VN <-> US; US render text City va khong giu dropdown VN.
- **TC-5**: Doi Province nhanh; list cuoi cung khop province hien tai.
- **TC-6**: Source listing, stock-source assignment va checkout/MSI regression.

## 6. Out of scope

- SL-011, SL-012, SL-013 va storefront address surfaces.
- `sub_city` generic, database schema, GraphQL API contract va carrier algorithm.

## 7. Escalation / approval

- **Level-2 pending**: TL approve approach component-local + native city persistence.
- **Reassess Tier-2**: neu Source city duoc dung lam carrier rate, pickup origin hoac fulfillment-distance input.
- **Process correction**: Code SL-014 da duoc them truoc plan trong turn truoc. Can TL review spec/plan va pre-review code theo plan truoc khi merge/release.
