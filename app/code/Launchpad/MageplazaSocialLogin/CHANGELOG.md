# Changelog — Launchpad_MageplazaSocialLogin

All notable changes to this project module are documented here.
The module exists to keep `Mageplaza_SocialLogin` / `Mageplaza_SocialLoginPro`
vendor code untouched.

## [Unreleased]

### Added (2026-09-16) — TASK-8TXS2P (SLP-203)

- `view/frontend/layout/onestepcheckout_index_index.xml` +
  `view/frontend/web/css/social-login-checkout.css`: restyle the OSC checkout
  social-login modal (jQuery/luma markup — the vendor `hyva_default` swap and the
  SLP-160 child-theme overrides never load in the luma scope, LL-0011) to the
  SLP-160 Hyvä UI target: white 760px card, duplicate title hidden, blue #3399cc
  bars removed (specificity beats the vendor `css.phtml` inline re-injection),
  white bordered social buttons with SVG data-URI brand logos replacing
  FontAwesome, primary #14532d, one-column stack ≤639px, close button restored
  (vendor styles the X white for the removed blue bar; header min-height fixes
  content painting over it). CSS-only — every vendor JS/class hook preserved 1:1.

### Added (2026-09-15) — BUG-KQ5A1D (SLP-222)

- `Plugin/SocialLoginPro/OneTapPlugin.php` + `etc/di.xml`: trim the Google
  One Tap client id (`sociallogin/google/app_id`) so an admin paste with
  leading/trailing whitespace no longer breaks One Tap rendering.
