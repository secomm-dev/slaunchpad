# Launchpad_MageplazaSocialLogin

Project-local fixes for `Mageplaza_SocialLogin` / `Mageplaza_SocialLoginPro`.
The vendor modules are never modified in place — every fix lives here
(plugin, layout, template override).

## Fix 1 — Google One Tap client id trimmed (BUG-KQ5A1D / SLP-222)

`Mageplaza\SocialLoginPro\Block\OneTap::getClientId()` reads the raw config
value `sociallogin/google/app_id` and renders it verbatim into
`data-client_id` (`quick_login/one_tap.phtml`). An admin paste with
leading/trailing whitespace (invisible in the `password`-type config field)
reaches the Google GIS client untrimmed and One Tap fails to initialize.

`Plugin/SocialLoginPro/OneTapPlugin::afterGetClientId()` trims the string
result; non-string values pass through unchanged. The main OAuth login flow is
unaffected — `Mageplaza_SocialLogin/Helper/Social.php` already trims the same
config path.

## Checkout popup style (TASK-8TXS2P / SLP-203, TASK-NWV2MQ / SLP-211)

The Mageplaza One Step Checkout page runs in the Magento/luma scope (LL-0011),
so the social-login popup opened from the checkout auth link is the legacy
luma popup — the Hyvä popup of the theme pages (styled by the theme overlay
`web/tailwind/theme/social-login.css`) never loads there.

`view/frontend/layout/onestepcheckout_index_index.xml` attaches
`view/frontend/web/css/social-login-checkout.css` on that page only. The file
restyles the luma markup to match the Hyvä popup: card, plain title, form
fields, buttons, links, social column and close button, in both columns and in
the stacked mobile layout. It is CSS-only and keeps every vendor JS/class
hook. Exceptions to "match Hyvä": the checkout keeps its own font family
(Open Sans), and the primary buttons keep the `sociallogin/general/style_management`
config color injected by the vendor `css.phtml`, as on the Hyvä popup.

When the Hyvä popup changes, re-measure both popups and update this file
(probe: `.ai/evidence/TASK-NWV2MQ/probe-home-vs-checkout.js`, regression:
`.ai/evidence/TASK-NWV2MQ/regression.js`).
