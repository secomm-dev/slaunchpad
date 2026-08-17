# QC Test Cases: SL-012 — Admin VN 2-level address dropdown trên order form

> Scope: admin order create (`sales_order_create_index`) + admin order address edit (`sales_order_address`).
> Focus: VN `country -> region -> city(ward)` cascade, same-as-billing sync, persist parity, non-VN fallback, and legacy `sub_city` no-op when empty.

| TC ID | Description | Precondition | Steps | Expected Result | Actual Result | Status | Notes |
|-------|-------------|--------------|-------|-----------------|---------------|--------|-------|
| TC-001 | Admin order create - billing cascade VN | Admin login; order create page open; VN data available; locale vi_VN or en_US | 1. Set country = Vietnam 2. Select region/province 3. Select ward from city dropdown 4. Save order | Billing address renders VN cascade; `city` stores ward name; save succeeds without free-typed ward | | Not Tested | Core AC-1 |
| TC-002 | Admin order create - shipping same-as-billing keeps ward | TC-001 precondition plus shipping section visible | 1. Fill billing VN address 2. Enable "Same as billing" 3. Inspect shipping fields before save 4. Save order | Shipping copies `country_id`, `region`, `region_id`, `city`/ward; no ward value lost in shipping | | Not Tested | Core AC-2 |
| TC-003 | Admin order edit - preselect region + ward | Existing order with saved VN billing/shipping address | 1. Open order address edit 2. Inspect region and ward fields 3. Change nothing 4. Save | Cascade hydrates correctly; saved ward is pre-selected; no reset on initial render or save | | Not Tested | Core AC-3 |
| TC-004 | Non-VN fallback stays Magento default | Admin order create/edit page open | 1. Set country != Vietnam 2. Observe address fields 3. Save | No VN-specific ward dropdown is forced; Magento default behavior remains intact | | Not Tested | Core AC-4 |
| TC-005 | Persist parity on save path | VN order create or edit page ready | 1. Enter VN billing/shipping address with ward 2. Save order 3. Inspect saved order address data | `sales_order_address.city` persists ward name; quote/order parity remains intact for order-create flow | | Not Tested | Core AC-5 |
| TC-006 | Validation blocks incomplete VN address | Admin order create/edit page open; VN selected | 1. Set country = Vietnam 2. Leave region or ward empty 3. Attempt save | Required validation prevents invalid VN address submission; order is not saved with missing ward/region | | Not Tested | Core AC-6 |
| TC-007 | Legacy `sub_city` path remains no-op when empty | Admin order create/edit page open; VN flow does not submit `sub_city` | 1. Use VN order form with only ward selected 2. Save order 3. Re-open saved order | Save succeeds even when `sub_city` is absent; observer `SaveSubCity.php` does not affect ward-only flow | | Not Tested | Regression / legacy no-op |
| TC-008 | Display regression on order view / email / PDF | Existing saved VN order with ward | 1. Open order view 2. Check related email/PDF if available 3. Compare displayed address | Region + ward display remains intact; no truncation or loss after save | | Not Tested | Core AC-7 |

## Summary

| Total | Passed | Failed | Blocked | Not Tested |
|-------|--------|--------|---------|------------|
| 8 | 0 | 0 | 0 | 8 |

## Coverage Notes

- AC-1 covered by TC-001.
- AC-2 covered by TC-002.
- AC-3 covered by TC-003.
- AC-4 covered by TC-004.
- AC-5 covered by TC-005.
- AC-6 covered by TC-006.
- AC-7 covered by TC-008.
- Legacy `sub_city` confirmation covered by TC-007.

