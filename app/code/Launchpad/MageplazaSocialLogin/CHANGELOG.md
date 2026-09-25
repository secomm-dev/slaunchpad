# Changelog — Launchpad_MageplazaSocialLogin

All notable changes to this project module are documented here.
The module exists to keep `Mageplaza_SocialLogin` / `Mageplaza_SocialLoginPro`
vendor code untouched.

## [Unreleased]

### Added (2026-09-25) — TASK-YHCJ79

- `view/frontend/templates/password-toggle.phtml` + blocks in
  `view/frontend/layout/hyva_default.xml` (roots `#social-login-popup`,
  `#authentication-popup`) and `view/frontend/layout/onestepcheckout_index_index.xml`
  (root `body`): eye-icon show/hide toggle on every password input of the
  Mageplaza popups (sign-in, create, request-info, checkout authentication) and
  of the OSC checkout page (create account, email step, Sign In popup, luma
  social-login modal). Plain JS, vendor templates untouched; late inputs (KO,
  Alpine `x-if`) via MutationObserver; the toggle is a `span[role=button]`
  (keyboard Enter/Space, `aria-pressed`, `aria-label`) because both pages style
  every `<button>` by tag. Heroicons solid `eye`/`eye-off`, colors
  `--color-ink-muted`/`--color-ink` with the checkout fallbacks.
- `view/frontend/web/css/social-login-checkout.css`: `padding-right: 44px` on
  the password inputs of the luma social-login modal (the modal's
  `#social-login-popup .social-login .input-text` padding outranks the toggle's).

### Changed (2026-09-25) — TASK-NWV2MQ (SLP-211)

- `view/frontend/web/css/social-login-checkout.css`: re-synced the OSC checkout
  social-login popup (luma fallback) with the Hyvä popup as it ships today
  (SLP-259 plain title + SLP-246 global style), measured element by element on
  the home page: title banner `#3399cc` removed (plain ink heading), block-title
  rule removed, label/input/primary button/link/close button/social buttons and
  the two-column layout match the Hyvä sizes, spacing and colors (≤7px position
  delta at 1280/375). The checkout keeps its own font family (Open Sans) until
  a checkout design exists; primary buttons keep the `style_management` config
  color. Beats the page-wide OSC `border-radius: 4px !important`, the vendor
  `.modal-content .secondary a.action { margin: -20px 0 25px !important }` (the
  old 6px/2px link nudge is gone — the link is centered on the button row) and
  luma's modal-slide offset under 768px. CSS-only, every vendor JS/class hook
  preserved.

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
