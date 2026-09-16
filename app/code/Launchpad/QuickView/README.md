# Launchpad_QuickView

## Purpose

Storefront feature switch for the **Quick View** modal (`SLP-157` / `TASK-Z3DAH5`). The modal itself lives in the theme `Secomm/launchpad` (`Magento_Theme::html/quickview/modal.phtml` + `Magento_Catalog::product/list/item.phtml`); this module owns the Admin configuration that turns the feature on/off per store view.

## Features

- **Stores > Configuration > Hyvä Themes > Quick View > General > Enable Quick View** (`hyva_theme_quickview/general/enabled`, default **Yes**, `canRestore`).
- Scope: default / website / store view — `vi_VN` and `en_US` storefronts can be toggled independently.
- When disabled: the Quick View trigger button is removed from product cards (`Launchpad\QuickView\ViewModel\Config::isEnabled()`, consumed via `ViewModelRegistry`) and the modal block is skipped in layout (`ifconfig="hyva_theme_quickview/general/enabled"` on `quickview.modal`).

## How it works

1. `ViewModel\Config` (`Launchpad\QuickView\ViewModel\Config`) reads `hyva_theme_quickview/general/enabled` via `ScopeConfigInterface::isSetFlag()` at store scope.
2. Theme `item.phtml` requires the view model through `ViewModelRegistry` and renders the trigger only when enabled.
3. Theme layout files (`catalog_category_view.xml`, `catalogsearch_result_index.xml`) declare the `quickview.modal` block with `ifconfig` so the whole modal container is dropped from the page when disabled.

## Notes

- After changing the value in Admin, flush the block cache (`bin/magento cache:flush`) so the product-card markup is re-rendered.
- No database schema; no cron; frontend only (admin system config + view model).
