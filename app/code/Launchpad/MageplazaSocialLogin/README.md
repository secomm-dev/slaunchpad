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

## Show/hide password toggle (TASK-YHCJ79)

`view/frontend/templates/password-toggle.phtml` adds an eye-icon toggle to every
password input under the root selectors passed by layout:

| Layout | Roots | Inputs covered |
|---|---|---|
| `hyva_default.xml` (Hyvä pages) | `#social-login-popup`, `#authentication-popup` | popup sign-in, create (2), request-info (2, when `sociallogin/general/information_require` has *Password*), checkout authentication popup |
| `onestepcheckout_index_index.xml` (OSC, luma scope) | `body` | create account (2), email step, OSC Sign In popup, luma social-login modal (login + create) |

How it works: one plain-JS copy for both stacks (no Alpine / KO / jQuery). Each
input gets a `span.lp-password-toggle[role=button]` inserted right after it; the
input's parent becomes `.lp-password-toggle-host` (relative, isolated stacking)
and the input gets `padding-right: 44px`. The toggle is pinned to the input box
with layout offsets through a ResizeObserver, so Hyvä validation wrapping the
input in a new `div.field-reserved`, OSC inputs narrower than their `.control`
and the popups' scale transition do not move it. Inputs rendered later (KO
templates, Alpine `x-if`) are picked up by a MutationObserver. Labels come from
`__('Show Password')` / `__('Hide Password')` — theme CSV on Hyvä pages,
`Launchpad_MageplazaTranslate` on the luma checkout.

Notes: a `<button>` is not used because both pages style buttons by tag
(`#popup_test button` in the theme CSS, the OSC design color
`.checkout-container button:not(...) !important`). `$hyvaCsp` only exists on
Hyvä themes, hence the `isset()` guard. On OSC the email-step password
(`#customer-password`) never shows — OSC's `email.js` keeps `isLoginVisible`
false — and the OSC Sign In popup is replaced by the social-login modal; both
still get a toggle. Regression: `.ai/evidence/TASK-YHCJ79/run.sh`.

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
