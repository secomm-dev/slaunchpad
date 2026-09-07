# Diff Summary — TASK-T0DWZ5 (SLP-44)

## Files

- `app/design/frontend/Secomm/launchpad/Magento_Review/templates/form.phtml`
  - Theme override copied from the installed Hyvä `Magento_Review::form.phtml`.
  - The single `getInputHtml(ReCaptcha::RECAPTCHA_FORM_ID_PRODUCT_REVIEW)` call moved from immediately after `form_fields_before` to immediately before the `Submit Review` button.
  - No vendor file changed; validation JS, legal notice, GraphQL mutation, and `X-ReCaptcha` header logic are unchanged.
- `.ai/records/tasks/TASK-T0DWZ5.md`
  - Canonical Mode C record with embedded Mini-Spec, approach, acceptance criteria, and verification.

## Scope confirmation

The semantic template diff is one deletion plus one insertion of the same reCAPTCHA render call. No dependency, configuration, backend validation, or styling change was introduced.
