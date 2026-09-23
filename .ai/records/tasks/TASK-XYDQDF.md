# TASK-XYDQDF — MoMo native refund idempotency + uncertainty safety (MOMO-02)

---
id: TASK-XYDQDF
type: task
title: "[MoMo][MOMO-02] Make native Credit Memo refunds idempotent and uncertainty-safe"
project_code: SLP
mode: A
specification_level: FULL
risk: high
status: READY_FOR_REVIEW
base_sha: c685e47968627eed2e17e2b450f389679098d148
correction_rounds:
  - round: 1 (coordinator verdict CORRECTION_REQUIRED, 2026-09-21)
    findings: |
      4 blockers on the provider-contract boundary, all fixed:
      (1) refund/query reused the stored refund requestId → resolve now mints
      a fresh query requestId (-QQ) per invocation via
      OrderRefBuilder::buildRefundQueryRequestId() and signs with it; the
      stored refund requestId stays immutable submission evidence.
      (2) classifyQuery single-entry fallback accepted an unrelated refund →
      exact refundTrans[].orderId match required; ambiguity/mismatch → UNKNOWN.
      (3) direct refund SUCCESS too permissive → strict integer grammar for
      amounts (no (int) cast of "150000abc") and SUCCESS additionally requires
      a valid positive refund transId (same rule on both paths).
      (4) only 7002 was non-final → full provider non-final set (10/11/12/13,
      20/21/22, 40/41/42/43/45/47, 7000, 7002, 9000) is UNKNOWN
      (provider_processing) keeping the slot open; only FINAL failures →
      FAILED. Regression coverage added for 7000 + representative non-final
      system/merchant codes on both direct and query paths.
      Incidental fix caught by the new resolve-command regression test:
      RefundResolveCommand caught a non-existent
      Secomm\MoMo\Gateway\Http\ClientException (transport errors crashed the
      command); it now catches Magento\Payment\Gateway\Http\ClientException.
    schema_change: none (per correction boundary — none required)
  - round: 2 (coordinator verdict CORRECTION_REQUIRED ROUND 2, 2026-09-21)
    findings: |
      Round-1 status: 3/4 findings FIXED; 2 remaining blockers, both fixed:
      (1) classifyQuery did not validate the refund/query response's
      TOP-LEVEL identity echoes → now requires response.requestId == the
      exact fresh query requestId sent, response.orderId == the refund's
      orderId, and partnerCode conflict-intolerant (absence tolerated) —
      all checked BEFORE interpreting refundTrans entries; mismatch/missing
      material identity → UNKNOWN (echo_mismatch), never terminal.
      (2) NON_FINAL_RESULT_CODES omitted 1000 ("transaction initiated,
      waiting for user confirmation", Final Status = No) → added; regression
      coverage on direct + query paths.
    schema_change: none
decisions:
  - "Durable secomm_momo_refund table keyed UNIQUE(momo_order_ref, momo_trans_id, open_flag) with the NULL-trick: open_flag=1 for open rows (pending/unknown), NULL for terminal — at most one open refund per payment; terminal rows release the slot (sequential partials + retry-after-FAILED stay native)."
  - "All refund-row persistence goes through an independent DB connection (ConnectionFactory::create on db/connection/default + manual table prefix) because CreditmemoService::refund() wraps the gateway call inside the sales-connection transaction; FAILED/UNKNOWN evidence must survive the native rollback that aborts the creditmemo."
  - "Refund response classification is echo-based (requestId/orderId/amount against the exact request sent) + resultCode: 0=SUCCESS, 7002=UNKNOWN (provider processing), other non-zero=FAILED; transport/timeout/malformed=UNKNOWN never FAILED. Backed by official docs fetched 2026-09-18: the refund response carries no signature; requestId is the provider idempotency key (>=31 days); refund orderId must differ from the purchase orderId."
  - "Minted refund identity (refund_order_id + request_id, <=50 chars each) stored on the row; the row IS the logical operation since the creditmemo id does not exist at gateway time; creditmemo_id backfilled post-commit by a read-only sales plugin writing only the MoMo-owned table."
  - "HTTP timeout 45s on the refund transfer (docs minimum 30s; Laminas default ~10s would misclassify slow-but-successful refunds as UNKNOWN)."
  - "Budget drift guard: sum(SUCCESS row amounts) > payment amount_refunded blocks new submissions; operator runbook = offline creditmemo to realign."
  - "Operator CLI momo:refund:list / momo:refund:resolve (query-only via /v2/gateway/api/refund/query, never re-POST the refund) closes UNKNOWN rows via guarded transitions; manual, per-invocation — not automated reconciliation."
components:
  - app/code/Secomm/MoMo/etc/db_schema.xml
  - app/code/Secomm/MoMo/etc/db_schema_whitelist.json
  - app/code/Secomm/MoMo/etc/di.xml
  - app/code/Secomm/MoMo/Api/Data/RefundRequestInterface.php
  - app/code/Secomm/MoMo/Api/RefundRequestRepositoryInterface.php
  - app/code/Secomm/MoMo/Model/RefundRequest.php
  - app/code/Secomm/MoMo/Model/RefundRequestFactory.php
  - app/code/Secomm/MoMo/Model/RefundRequestRepository.php
  - app/code/Secomm/MoMo/Model/Config.php
  - app/code/Secomm/MoMo/Model/OrderRefBuilder.php
  - app/code/Secomm/MoMo/Gateway/Command/RefundCommand.php
  - app/code/Secomm/MoMo/Gateway/Request/RefundBuilder.php
  - app/code/Secomm/MoMo/Gateway/Response/RefundHandler.php
  - app/code/Secomm/MoMo/Gateway/Http/TransferFactory.php
  - app/code/Secomm/MoMo/Service/RefundClassification.php
  - app/code/Secomm/MoMo/Service/RefundConnectionProvider.php
  - app/code/Secomm/MoMo/Service/RefundResultClassifier.php
  - app/code/Secomm/MoMo/Service/RefundRequestManager.php
  - app/code/Secomm/MoMo/Plugin/Sales/CreditmemoService.php
  - app/code/Secomm/MoMo/Console/Command/RefundListCommand.php
  - app/code/Secomm/MoMo/Console/Command/RefundResolveCommand.php
  - app/code/Secomm/MoMo/Test/Unit/Service/RefundResultClassifierTest.php
  - app/code/Secomm/MoMo/Test/Unit/Service/RefundRequestManagerTest.php
  - app/code/Secomm/MoMo/Test/Unit/Gateway/Command/RefundCommandTest.php
  - app/code/Secomm/MoMo/Test/Unit/Model/OrderRefBuilderRefundIdentityTest.php
  - app/code/Secomm/MoMo/Test/Unit/Plugin/Sales/CreditmemoServiceTest.php
  - app/code/Secomm/MoMo/Test/Unit/Console/Command/RefundResolveCommandTest.php
  - app/code/Secomm/MoMo/README.md
  - app/code/Secomm/MoMo/CHANGELOG.md
changes_request:
  - "Modified: db_schema.xml + db_schema_whitelist.json (secomm_momo_refund), di.xml (preferences, refund command/transfer factories, CLI registration, sales plugin), Config.php (PATH_REFUND_QUERY), TransferFactory.php (clientConfig/timeout), OrderRefBuilder.php (buildRefundOrderId/Id), RefundBuilder.php (row-driven identity, refund-specific orderId), RefundHandler.php (refund transId/requestId on payment), README.md + CHANGELOG.md."
changes_add:
  - "Added: Api contracts (RefundRequestInterface, RefundRequestRepositoryInterface), entity + factory, raw-SQL repository on an independent connection (RefundConnectionProvider/RefundRequestRepository), RefundRequestManager (guards + lifecycle), RefundResultClassifier + RefundClassification VO, Gateway/Command/RefundCommand, Plugin/Sales/CreditmemoService (post-commit creditmemo_id backfill), Console momo:refund:list / momo:refund:resolve, 5 focused unit test files."
changes_delete:
  - "None (out of scope: RefundValidator legacy class left untouched; no longer referenced by di.xml)."
validation:
  - "php -l: all changed/new PHP files clean (validation env m2r-php, PHP 8.3.20)."
  - "phpunit (dev/tests/unit/phpunit-secomm.xml, filter Secomm.MoMo): 160 tests, 423 assertions, PASS (5 pre-existing PHPUnit deprecations, framework-level)."
  - "PHPCS Magento2 standard, severity>=6, on the module diff: 0 errors (2 fixable warnings auto-fixed; 1 deliberate static VO factory warning left with note)."
  - "setup:install on a fresh DB with the NEW schema: EXIT=0 (1456/1456); secomm_momo_refund created; DDL captured in .ai/evidence/TASK-XYDQDF/db-schema-after-install.txt."
  - "Incremental migration path: fresh install with the BASE module (git archive HEAD — no secomm_momo_refund), then copy the new module and setup:upgrade → table created incrementally, 'Upgrade completed successfully', setup:db:status → 'All modules are up to date' (schema + whitelist in sync). DDL in .ai/evidence/TASK-XYDQDF/db-schema-after-upgrade.txt."
  - "setup:di:compile: EXIT=0 ('Generated code and dependency injection configuration successfully')."
  - "CLI smoke: bin/magento list shows momo:refund:list / momo:refund:resolve; momo:refund:list executes against the real DB (temp row listed + cleaned) exercising the independent-connection read path."
  - "Correction round 1 re-validation (final tree): php -l clean on all changed files; phpunit (phpunit-secomm.xml, filter Secomm.MoMo): 177 tests / 492 assertions PASS (5 pre-existing framework deprecations); PHPCS Magento2 severity>=6 on the diff: 0 errors 0 warnings; fresh setup:install with the final tree EXIT=0; setup:di:compile EXIT=0 (resolve command gained an OrderRefBuilder constructor dependency — concrete class, autowired, no di.xml change); schema unchanged vs first submission."
  - "Correction round 2 re-validation (final tree): php -l clean on all changed files; phpunit: 182 tests / 510 assertions PASS (5 pre-existing framework deprecations; +5 query-echo-binding tests, +1000-code regressions, + resolve-command classifier-identity assertion); PHPCS Magento2 severity>=6 on the changed files: 0 errors 0 warnings; fresh setup:install on an empty DB EXIT=0 with secomm_momo_refund created (schema unchanged — see the environment note in .ai/evidence/TASK-XYDQDF/correction-round-2-validation.txt: two UNRELATED upstream live-stack modules, Secomm_Ahamove and Mageplaza_ExtraFee, plus Hyva_Koti*SampleData patches, break ANY fresh install of the current main checkout and were disabled in the throwaway validation env only); setup:di:compile EXIT=0; CLI smoke: momo:refund:list / momo:refund:resolve listed, momo:refund:list executes."
verified_against_commit: c685e47968627eed2e17e2b450f389679098d148
external_refs:
  - "github:thanhle74/slaunchpad#4"
---
