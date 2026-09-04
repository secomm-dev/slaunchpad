# TASK-XY9RZF — Final Batch 1 compatibility evidence

Date: 2026-08-28

## Scope

- Capability: `CMP-SECOMM-UI`
- Primary theme: `Secomm/launchpad` (store theme ID 5)
- Override theme used during QA: `Secomm/launchpad_fashion` (store theme ID 6); temporary fixture not shipped
- Approved phase: Batch 1, 21 unique component IDs

## Automated gates

- Full `Secomm_UiWidget` unit suite after removing the non-shipped theme fixture: 86 tests, 504 assertions.
- PHP syntax checks: passed for the fashion override and visual parity test.
- XML/DI validation: passed in the accumulated Batch 1 gate; the latest component slice includes successful DI compilation.
- Primary theme Tailwind v4 production build: passed.
- Fashion theme static content deployment (`vi_VN`): passed through the complete Hyvä inheritance chain.
- `git diff --check`: passed.
- Magento PHPCS error gate: passed; documentation/line-length warnings remain non-blocking review notes.
- Security regression is fail-closed for unknown component/template, malformed/oversized/deep payload, invalid schema/enums and unsafe URLs. Rich HTML remains restricted to declared `trusted-rich-text` fields.

## Storefront and theme results

| Theme | Viewports | Unique B1 IDs | Override marker | Page horizontal overflow |
|---|---|---:|---:|---|
| `Secomm/launchpad` | restored primary check | 21 | 0 | no |
| `Secomm/launchpad_fashion` | 390 / 768 / 1440 px | 21 | 1 temporary (`usp_c`) | no |

- The temporary fashion `usp_c` override retained the module HTML/Tailwind visual contract and rendered one non-visual marker. It was removed before commit because no product-theme-specific presentation is currently required.
- Lazy Gallery media loaded after entering the viewport; Category A images reported natural width 1024.
- The homepage contains 22 fixture instances but 21 unique IDs because an older `banner_c` sample is duplicated. This is fixture data, not a registry/rendering defect, and was not silently modified during this task.
- Store scope 1 was restored to theme ID 5 and Magento config/layout/block/full-page caches were cleaned after the fashion run.

## CMS, interaction, cache and accessibility coverage

- Native CMS Static Block Page Builder `HTML Code` → `Insert Widget` → `Secomm UI` was exercised throughout Batch 1. The block is composed into the homepage CMS Page and all 21 unique IDs render.
- Repeater round-trip/order and multi-instance isolation are covered by schema/codec tests plus Accordion, Modal, Slider and Admin AJAX browser evidence under `TASK-ZQ9ZE1`.
- Interactive controls preserve native semantic/ARIA behavior: Accordion details/summary, native Modal dialog and labelled slider controls.
- Magento `full_page` cache type is enabled and storefront output remained stable after cache clean and repeated browser reload. DDEV returned `no-store`, so FPC HIT/MISS and Varnish headers were not proven locally and remain a release-environment check.

## Documentation handoff

- Module README lists all 21 IDs, the theme override contract and the seven-step upstream component update workflow.
- Module CHANGELOG records the Batch 1 components and the locally verified, non-shipped override contract.
- Project context 04/09, component index and current state register `CMP-SECOMM-UI`.

## Residuals

- Batch 2 catalog-backed providers/components are intentionally deferred by scope decision.
- Luma/non-Hyvä themes are unsupported by design.
- No custom Page Builder content type is included; compatibility uses Magento's standard widget directive in the HTML content surface.
