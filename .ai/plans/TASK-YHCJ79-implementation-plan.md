# Implementation Plan: TASK-YHCJ79 — Show/hide password icon (Mageplaza popups + OSC checkout)

| Field | Value |
|---|---|
| Specification | Mini-Spec (embedded) — [TASK-YHCJ79](../records/tasks/TASK-YHCJ79.md) |
| Workflow Mode | B |
| Risk | medium — UI-only tren OSC page (§12 checkout, Tier-2 formality) |
| Date | 2026-09-25 |

## Steps

1. **Template** `app/code/Launchpad/MageplazaSocialLogin/view/frontend/templates/password-toggle.phtml`
   - `<style>` scoped class `lp-password-toggle*`: host `position: relative`, input `padding-right`,
     nut absolute ben phai, reset style `button` cua luma/OSC, mau token (Mini-Spec Constraints).
   - Inline JS (vanilla): doc `root_selectors` (block data), `enhance(input)` cho moi
     `input[type=password]` chua gan (`data-lp-password-toggle`): chen `<button type="button">` ngay sau
     input, `aria-controls`, click → doi `type` + icon + `aria-pressed`/`aria-label`.
     `ResizeObserver` dat `top`/`height` nut theo `offsetTop`/`offsetHeight` cua input (input an → hien,
     loi validation chen sau input khong day lech nut). `MutationObserver` tren moi root cho input render muon.
   - Icon `HeroiconsSolid::eyeHtml/eyeOffHtml(20)`, label `__('Show Password')`/`__('Hide Password')`,
     `$hyvaCsp->registerInlineScript()`.
2. **Layout**
   - `view/frontend/layout/hyva_default.xml`: them block vao `before.body.end`,
     `root_selectors` = `#social-login-popup`, `#authentication-popup` (giu referenceBlock SLP-207).
   - `view/frontend/layout/onestepcheckout_index_index.xml`: them block, `root_selectors` = `body`
     (modal luma bi move ra cuoi body).
3. **i18n** `app/code/Launchpad/MageplazaTranslate/i18n/{vi_VN,en_US}.csv`: "Show Password", "Hide Password"
   (theme CSV khong nam trong fallback luma — LL-0011).
4. `cache:flush` as secomm (khong can setup:upgrade — module da enable, khong class PHP moi).
5. **Verify** (Playwright Chrome 150, evidence `.ai/evidence/TASK-YHCJ79/`): AC-001..006; screenshot
   1280/375 popup home + OSC; regression login popup (mock endpoint), validation messages, console errors.
6. README + CHANGELOG `Launchpad_MageplazaSocialLogin`; CURRENT_STATE; → pre-review → TL review → QC e2e checkout.

## Rollback

Xoa 2 block trong layout (hoac file template) + `cache:flush`. Khong co data/schema.
