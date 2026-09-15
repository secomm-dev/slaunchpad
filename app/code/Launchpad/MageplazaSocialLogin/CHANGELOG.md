# Changelog — Launchpad_MageplazaSocialLogin

All notable changes to this project module are documented here.
The module exists to keep `Mageplaza_SocialLogin` / `Mageplaza_SocialLoginPro`
vendor code untouched.

## [Unreleased]

### Added (2026-09-15) — BUG-KQ5A1D (SLP-222)

- `Plugin/SocialLoginPro/OneTapPlugin.php` + `etc/di.xml`: trim the Google
  One Tap client id (`sociallogin/google/app_id`) so an admin paste with
  leading/trailing whitespace no longer breaks One Tap rendering.
