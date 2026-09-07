---
id: TASK-J6HKAJ
type: task
title: 'Migrate MoMo & ZaloPay payment controllers from Action inheritance to composition'
project_code: SLP
parent: null
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: high
status: completed
created: 2026-09-03
updated: 2026-09-03
external_refs: {}
legacy_ids: []
decisions: []
decision_assessment: none-material
components:
  - CMP-MOMO                  # Secomm_MoMo
  - CMP-ZALOPAY               # Secomm_ZaloPay
  - CMP-PAYMENT               # Payment integration layer
source_areas:
  - app/code/Secomm/MoMo/Controller/Payment/
  - app/code/Secomm/ZaloPay/Controller/Payment/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
verified_against_commit: 547b80f610c2280e9f91f6885e97dcc4c91d22cc
last_verified: 2026-09-04
supersedes: []
---

# [SLP][TASK-J6HKAJ] Migrate MoMo & ZaloPay payment controllers from Action inheritance to composition

<!-- Composition refactor theo constitution §1 (composition over inheritance) + team rule "không dùng class/hàm deprecated". Static analysis (VSCode PHP6406) flag `extends \Magento\Framework\App\Action\Action` (deprecated 103.0.0). -->

## Summary

Toàn bộ 6 payment controllers của `Secomm_MoMo` (`Redirect`, `Notify`, `ReturnAction`) và `Secomm_ZaloPay` (`Start`, `Ipn`, `ReturnAction`) đang `extends \Magento\Framework\App\Action\Action` — class deprecated từ 103.0.0. Chuẩn team (constitution §1: composition over inheritance) cấm dùng class deprecated trong code mới/refactor. Task này migrate cả 6 controllers sang composition: `implements HttpGetActionInterface` / `HttpPostActionInterface` (+ `CsrfAwareActionInterface` nơi cần), inject dependencies thay vì kế thừa `Context`. Behaviour giữ nguyên 100%.

## Mini Spec

### Goal

Không controller nào của 2 module payment còn kế thừa class deprecated; tất cả đi qua interface chuẩn Magento (GET/POST action + CSRF), deps inject qua constructor.

### Expected Behavior

1. Mỗi controller `implements HttpGetActionInterface` (GET-only) hoặc `implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface` (nhận cả 2 verbs + custom CSRF), không `extends Action`, không `parent::__construct($context)`.
2. `_redirect()`, `getRequest()`, `getResponse()` thay bằng injected `RedirectFactory`/`RequestInterface` (hoặc `Request\Http` khi cần `isPost()`/`getContent()`)/`JsonFactory`.
3. CSRF semantics giữ nguyên: `createCsrfValidationException()` trả null, `validateForCsrf()` trả true (PSP callbacks không mang form_key).
4. Legacy behavior preserved: `Start::executeLegacy()` fall-through null, `Ipn` non-POST trả null, route/ACL/xml routing không đổi.

### Constraints / Rules

- Behaviour-preserving refactor — KHÔNG đổi logic business, chỉ đổi cấu trúc plumbing.
- Payment là scope sensitive (CLAUDE.md) — user đã authorize trực tiếp từng bước ("refactor tieesp").
- PHPCS Magento2 sạch trên file sửa; PHP 8.3 compatible.
- Controller đọc raw body type-hint concrete `Magento\Framework\App\Request\Http` (interface không có `getContent()`/`isPost()`).

### Out of Scope

- Refactor controllers ngoài payment (shipping, VietQR…).
- Pre-existing issues không liên quan inheritance: `json_encode` trong log của ZaloPay `Ipn`, unused `use Magento\Checkout\Model\Session` import.
- Thay đổi routing/ACL/system.xml.

### Acceptance Criteria

- [x] **DoD-001**: 6/6 controllers `implements` interface chuẩn, không `extends Action` / `parent::__construct` / `_redirect()` / `getRequest()` / `getResponse()` (grep sạch).
- [x] **DoD-002**: `setup:di:compile` pass, không lỗi/exception.
- [x] **DoD-003**: PHPCS Magento2 0 error/0 warning trên các file sửa.
- [x] **DoD-004**: MoMo unit suite (18 tests) pass sau refactor; ZaloPay không có unit tests cho controllers (không bị vỡ).

## Implementation Notes

- MoMo `ReturnAction`/`Redirect`: trả `Result\Redirect` từ `RedirectFactory`; `Notify` trả `Result\Json` từ `JsonFactory`, inject `Request\Http` cho raw body.
- ZaloPay `Start`: giữ payment-first flag + legacy fallback; `handleFailure()` dùng `PaymentFailuresInterface`.
- ZaloPay `Ipn`: mirror MoMo Notify (`Http` cho raw body, `RequestInterface` chỉ cho 2 CSRF methods).
- ZaloPay `ReturnAction`: giữ 3 nhánh (payment-first qua `ReturnProcessor`, legacy order-first, fall-through).

## Known Issues / Verification Status

- ✅ **Verification PASSED (2026-09-04, stack `slaunchpad-phpfpm-1`)**:
  - `phpunit`: **18/18 tests, 36 assertions PASS** (warning duy nhất = Allure extension config missing — môi trường, không liên quan code).
  - `phpcs --standard=Magento2`: **0 error / 0 warning** trên `MoMo/Controller/`, `MoMo/Test/Unit/Controller/`, `ZaloPay/Controller/` (sau khi fix docblock constructor `Start.php`: 12 `@param` stale cho 10 params thật → duplicates/out-of-order).
  - `setup:di:compile`: **success**.
  - `php -l` pass 6/6 (bắt được 1 fatal đã fix: MoMo `Redirect` class name trùng `use ...Result\Redirect` → alias `as ResultRedirect`).
