---
id: TASK-MCHN2T
type: task
title: 'ZLP-OPS-01 — ZaloPay operator diagnostics (CLI), debug mode và configurable checkout branding'
project_code: SLP
parent: null
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-18
updated: 2026-09-18
legacy_ids: []
external_refs:
  - github:thanhle74/slaunchpad#8
  - external-id:ZLP-OPS-01
decisions: []
components:
  - Secomm_ZaloPay
source_areas:
  - app/code/Secomm/ZaloPay/Console/Command/DiagnoseCommand.php
  - app/code/Secomm/ZaloPay/Gateway/Helper/RefundQuerySubjectBuilder.php
  - app/code/Secomm/ZaloPay/Gateway/Http/Client/Zend.php
  - app/code/Secomm/ZaloPay/Logger/DebugHandler.php
  - app/code/Secomm/ZaloPay/Model/System/Config/Backend/Logo.php
  - app/code/Secomm/ZaloPay/Model/ZaloPayConfigProvider.php
  - app/code/Secomm/ZaloPay/Cron/RefundCronjob.php
  - app/code/Secomm/ZaloPay/etc/di.xml
  - app/code/Secomm/ZaloPay/etc/adminhtml/system.xml
  - app/code/Secomm/ZaloPay/etc/config.xml
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
last_verified: 2026-09-18
supersedes: []
---

# [SLP][TASK-MCHN2T] ZLP-OPS-01 — ZaloPay operator diagnostics (CLI), debug mode và configurable checkout branding

> External ticket: [GitHub thanhle74/slaunchpad#8](https://github.com/thanhle74/slaunchpad/issues/8) (`ZLP-OPS-01`, `READY_TO_START`, branch `thanhle74/zalopay-zlp-ops-01-add-operator-diagnostics-debu`, BASE_SHA `87f4db44`).

## Bối cảnh (Context)

Vận hành/manual QA ZaloPay hiện phải sửa code hoặc chạy storefront end-to-end để kiểm tra: không có Symfony Console surface cho diagnostics; `Zend.php` đã build debug context + mask key nhạy cảm nhưng payload request/response không có config gate tường minh; checkout logo hardcode asset module trong `ZaloPayConfigProvider` — admin không thể thay logo không qua static deploy.

## Mini Spec

### Goal

Ba năng lực operator-facing cho `Secomm_ZaloPay`: (1) CLI diagnostics read-only; (2) Admin Debug Mode gate provider request/response debug logging; (3) logo checkout upload được với default fallback.

### Expected Behavior

1. `bin/magento zalopay:diagnose` (read-only): report sandbox/live + gateway URL, active, debug on/off, credentials **presence only** (không in giá trị), callback/start/return URL paths; exit 0 khi config hợp lệ, exit 1 khi invalid/incomplete. `--json` cho machine-readable.
2. `--query-payment=<app_trans_id>`: query provider qua `query_transaction` command pool (canonical, shape giống `ReturnProcessor::queryTransaction`), in whitelist field an toàn.
3. `--query-refund=<m_refund_id>`: resolve local refund row theo `m_refund_id`; reuse đường reconciliation chuẩn (stored payload → re-sign → `RefundQueryCommand::getRefundQuery`); không đụng state/budget row.
4. `payment/zalopay/debug` (default OFF): OFF → provider request/response debug payload không được log (error/critical giữ nguyên); ON → payload masked ghi vào `var/log/zalo-pay.log` (reuse core `Magento\Payment\Model\Method\Logger` gate + masking đệ quy); key1/key2/mac/signature/secrets không bao giờ xuất hiện trong log.
5. Admin upload logo (`payment/zalopay/logo`, type image, PNG/JPG/JPEG/WEBP — SVG bị chặn): checkout dùng custom logo từ media storage khi set, fallback `Secomm_ZaloPay::images/logo.png` khi rỗng; renderer tiếp tục tiêu thụ một contract `logoSrc`.

### Constraints / Rules

- Không đổi payment-first lifecycle, IPN authority, refund state machine/accounting, không DB schema.
- Không duplicate gateway request/signature logic — CLI reuse canonical services/commands.
- Không tạo transaction/refund thật từ CLI; query read-only tuyệt đối.
- Không log/in secret nào (key1/key2, MAC, signature); masking reuse path `Zend.php` + core `Method\Logger`.
- Error/critical operational log không được biến mất khi debug OFF.
- RefundCronjob chỉ nhận behavior-preserving refactor (extract subject-builder service dùng chung với CLI).

### Out of Scope

- General-purpose diagnostics framework đa provider; Admin UI redesign ngoài 3 năng lực; sửa `appuser`/`app_user` config-key mismatch (ghi nhận, ngoài scope); SVG upload; thay đổi Composer deps.

### Acceptance Criteria

- AC1: Operator chạy documented diagnostics command không cần storefront.
- AC2: Default CLI path không mutation tài chính provider-side.
- AC3: Payment query CLI reuse canonical query gateway/service + validate response chuẩn.
- AC4: CLI output không bao giờ expose credentials/MAC/signature.
- AC5: Admin có Debug Mode default OFF.
- AC6: Debug OFF suppress provider debug request/response logging, giữ error/critical.
- AC7: Debug ON sinh masked diagnostics trong ZaloPay log path (`var/log/zalo-pay.log`).
- AC8: Admin upload được logo hợp lệ (PNG/JPG/JPEG/WEBP).
- AC9: Checkout dùng custom logo khi cấu hình, default module logo khi không.
- AC10: Logo type invalid/unsafe bị từ chối.
- AC11: Payment-first/IPN/return/recovery/refund tests giữ green (baseline 7 errors pre-existing Tracking, 0 regression).
- AC12: Không đổi schema/state-machine payment-refund.

## Approach

Plan: [TASK-MCHN2T-implementation-plan](../../plans/TASK-MCHN2T-implementation-plan.md)

| Specification | Embedded Mini-Spec (TASK-MCHN2T — behavioral contract từ GitHub issue #8 / ZLP-OPS-01) |
|---|---|

## Verification

- [x] AC1–AC12 — evidence: `.ai/evidence/TASK-MCHN2T/` (validation.md + unit-suite-output.txt). Lưu ý AC11: baseline "7 errors Tracking" đã stale — full suite hiện 16 errors + 2 failures ĐỀU ở FulfillmentCore/PromotionMaxDiscount/Tracking (module khác drift trong base chung); diff của task gói trong `app/code/Secomm/ZaloPay/**` + `.ai/`, 0 lỗi ZaloPay.
- [x] Unit suite ZaloPay green (390 tests / 1397 assertions, 0 fail — gồm 23 test mới); PHPCS Magento2 severity 10 = 0 errors toàn module; `setup:di:compile` OK; CLI smoke (list/report/--json/exit codes/mutual exclusion/query-refund unknown id) PASS; `--check-specs`/`--check-identity`: 0 FAIL/WARN nhắc TASK-MCHN2T (toàn bộ lỗi là artifact cũ của task khác).

## Implementation Notes

- Phát hiện thiết kế: core `Magento\Payment\Model\Method\Logger` đã gate theo `payment/zalopay/debug` + mask đệ quy `maskKeys` → feature B chỉ thêm field/default/wire path; fix thêm `key1` vào sensitive list `Zend.php` (trước đây thiếu — leak tiềm ẩn khi debug bật) và truyền maskKeys cho core logger để mask đệ quy cả response.
- Refund query CLI path: `RefundCronjob::buildQuerySubject()` extract thành `Gateway\Helper\RefundQuerySubjectBuilder` — cron + CLI dùng chung, không duplicate signing. Refactor behavior-preserving (message/caching contract giữ nguyên); `RefundCronjobTest` chuyển sang boundary mock của builder (contract: decode payload, terminal khi malformed, MAC re-signed).
- `DiagnoseCommand` đăng ký qua `CommandListInterface` trong di.xml; deps được wire tường minh (`ZaloPayConfig`, `ZaloPayCommandPool`, `RefundQueryCommand`); còn lại autowire. Response projection whitelist: `return_code`, `sub_return_code`, `return_message`, `zp_trans_id`, `refund_id`, `m_refund_id` — không bao gồm MAC/signature/secrets.
- Logo: backend model `Secomm\ZaloPay\Model\System\Config\Backend\Logo` (allow-list png/jpg/jpeg/webp, thay gif của core Image backend); URL custom = media base + `zalopay/` + giá trị lưu (prefix scope `websites/<id>/` nhúng sẵn trong giá trị); đọc website scope trước rồi default scope; fallback asset module. `ZaloPayConfigProvider` giữ contract `logoSrc` duy nhất; đồng thời dọn dep không dùng (`PaymentHelper`, `ResolverInterface`).
- KHÔNG commit/push; không comment issue #8 (chờ user/TL). Record chuyển `in_review` chờ TL review theo Mode B.

## Correction Round 1 (coordinator verdict CORRECTION_REQUIRED, 2026-09-18)

Ba material findings đã sửa (bounded: same issue/branch/workspace, không đụng state machine):

1. **Return endpoint sai** — report giờ nêu route canonical `zalopay/payment/returnaction` (khớp `OrderAdditionalInformationDataBuilder::$controllerAction`, `Controller/Payment/ReturnAction.php`); kèm assertion test chặn path stale `payment/return(?!action)`.
2. **`app_user` thiếu khỏi required set** — thêm vào `$required` của config health check (contract v2 create bắt buộc; `ZaloAppInfoDataBuilder` luôn emit) → `app_user` rỗng giờ fail report (exit 1) và được nêu tên; test text + JSON projection.
3. **PII leak trong debug log** — `app_user` (user identifier merchant-side: id/username/name/phone/email) được thêm vào `Zend::sensitiveKeys()` → masked '****' cả pre-mask request lẫn maskKeys đệ quy response; regression test chứng minh giá trị raw không bao giờ tới Monolog output. Không mở rộng sang mismatch `appuser`/`app_user` merchant_info (out of scope theo chỉ định).

Re-validation: focused tests 16/16; ZaloPay suite 391 tests / 1407 assertions green; PHPCS severity 10 = 0 errors; `setup:di:compile` OK; CLI smoke xác nhận cả 3 fix; validators 0 nhắc TASK-MCHN2T. Commit mới + non-force push cùng branch, handoff READY_FOR_REVIEW mới với TIP_SHA chính xác.
