# Secomm_ThemeHelper

Reusable Magento-side configuration and typed providers for Secomm storefront
themes. Presentation remains owned by the active theme.

## Global Style Showcase

`/themehelper/styleguide/` renders the active theme's Global Style foundations
for visual regression and accessibility review. The route is intentionally
available only in Magento developer mode and returns 404 in other modes.

The showcase owns no presentation CSS. Its theme templates consume the same
semantic Tailwind utilities, component hooks and Hyvä SVG renderer as production
storefront templates.

## Header configuration

Stores → Configuration → Secomm Extensions → Theme → Header provides the
Store View scoped **Enable Sticky Header** setting. The default is enabled.
