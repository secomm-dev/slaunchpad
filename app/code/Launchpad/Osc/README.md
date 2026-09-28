# Launchpad_Osc

Mageplaza One Step Checkout (OSC) address integration for the **Launchpad** package — owns every OSC coupling of the VN address cascade so that `Secomm_AddressDropdown` stays generic (default Magento checkout only).

**Boundary:** DEC-TASKFMAN1B-001 (implements the "project integration module" option of TASK-FMAN1B AC-O1 / DEC-8). Never edit `app/code/Mageplaza/*` in-place.

## How it works

- `etc/db_schema.xml` re-declares `quote_address.osc_gift_wrap_amount` as `decimal` (SLP-232): Mageplaza_Osc ships it as `boolean` → TINYINT(1) (max 127), which clamps every real VND gift wrap amount to 127 on quote save, so the Order Summary displays "127" while grand total math stays correct. The declaration merges after the vendor's (module sequence), so one `setup:db-schema:upgrade` applies the ALTER; see the file header for the one-time data repair SQL and the intentionally-out-of-scope sibling columns. With OSC absent the declaration only keeps the (orphaned) column alive — declarative schema, no runtime gate needed.
- `view/frontend/layout/onestepcheckout_index_index.xml` re-points the checkout `jsLayout` cascade components (`address_dropdown`, `address_dropdown_billing`) to this module's copies. The OSC page inherits `checkout_index_index` (via `<update handle="checkout_index_index"/>`), where `Secomm_AddressDropdown` registers its default-checkout components; because this module loads after it, the same jsLayout keys are overwritten with the OSC-tuned copies — a single component instance renders, never two.
- The JS copies inject the ward cascade (schema-driven labels via GraphQL `addressSchema`, data via `GetListCity`) into the OSC shipping/billing forms and sync the selection into the KO-bound inputs, keeping the standard submit contract intact. The legacy sub-city tier was removed from the copies (TASK-6MKF0V / DEC-TASK6MKF0V-001) — every administrative level renders through the profile engine's city depth, and no `sub_city` payload is submitted.

## Soft coupling (works with OSC disabled)

Magento Open Source 2.4.8 has no `module.xml` `soft="true"`, so the contract is:

1. `etc/module.xml` sequences `Mageplaza_Osc` — orders loading only when present, not a hard requirement.
2. All frontend behaviour is scoped to the `onestepcheckout_index_index` handle — OSC disabled ⇒ handle never renders ⇒ module contributes nothing.
3. `composer.json` only `suggest`s Mageplaza OSC.
4. Any future PHP integration (plugins, Option A layout processor) MUST runtime-gate on `Magento\Framework\Module\Manager::isEnabled('Mageplaza_Osc')` / `isOutputEnabled()`.

## Temporary by design

The two JS copies are the QC'd quick-fix path of the legacy cascade. They are scheduled to be replaced by the Alpine schema cascade (Option A, TASK-FMAN1B) and removed together with the legacy mixins (TASK-K09G8Y).
