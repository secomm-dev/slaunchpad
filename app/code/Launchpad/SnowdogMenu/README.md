# Launchpad_SnowdogMenu

## Purpose

Storefront navigation toggle for the header (`SLP-129` / `TASK-SXW5RB`). Selects between the **native Hyvä category navigation** and **Snowdog Menu** navigation without disabling the `Snowdog_Menu` module — the Snowdog admin menu builder (Marketing > Snowdog Menu Manager) stays available either way.

## Features

- **Stores > Configuration > Hyva Theme > Snowdog Navigation > General > Enable Snowdog Menu Navigation** (`snowdog_navigation/general/enabled`, default **No**, `canRestore`; tab `hyva_themes` khai báo bởi `Hyva_ThemeModule`).
- Scope: default / website / store view.
- **No** (default): the native Hyvä top menu renders (`topmenu_mobile` + `topmenu_desktop`, restored via theme layout `remove="false"` in `Secomm/launchpad`); every `Snowdog\Menu\Block\Menu` output is gated empty by the frontend plugin; the Snowdog Alpine collapse plugin is skipped (theme template guard).
- **Yes**: Snowdog Menu renders the navigation from the admin-built menus (identifiers `hyva-topmenu-desktop`, `hyva-topmenu-mobile`) — menus must exist for the store or the navigation renders empty (module behavior).

## How it works

1. `ViewModel\Config` (`Launchpad\SnowdogMenu\ViewModel\Config`) reads `snowdog_navigation/general/enabled` via `ScopeConfigInterface::isSetFlag()` at store scope.
2. `Plugin\Snowdog\Menu\Block\Menu` (frontend-scope `afterToHtml` on `Snowdog\Menu\Block\Menu`) returns `''` while disabled — one plugin covers the desktop, mobile and footer menu blocks. When `Snowdog_Menu` is disabled the target class does not exist and the plugin is never invoked.
3. Theme `Magento_Theme::html/header/topmenu.phtml` (override in `Secomm/launchpad`) branches on the view model: native children vs the Snowdog mobile child; the Snowdog desktop block renders separately in `header.container`.

## Notes

- After changing the value in Admin, flush the cache (`bin/magento cache:flush`) — the top menu is cached in `block_html` (ttl 3600).
- No database schema; no cron; frontend only (admin system config + view model + plugin). Third-party `Snowdog_Menu` is never modified — extension is via plugin + theme overrides only.
