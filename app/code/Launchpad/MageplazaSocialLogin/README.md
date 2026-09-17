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
