# Commit plan (r6, 2026-09-22) — PROPOSAL ONLY, chưa commit

Recommended order (compile-safe per step; mỗi commit chạy được test độc lập):

## 1. `fix(addressdropdown): SQLi, ACL tree, POST-only deletes, canonical guard, master switch, factories`

- Files: toàn bộ `app/code/Secomm/AddressDropdown/**` modified/untracked thuộc Batch A
  (37 files theo manifest `diff-manifest-batch-a.txt` + untracked services/tests/factories).
- Why together: cùng module, các fix reuse factory/canonical guard; tách ra sẽ có commit
  intermediate thiếu dependency (Guard dùng RegionFactory; resolver tests dùng factories).
- Tests: AddressDropdown suite (96/223) + `GhnTransportContractTest`-style static checks.
- Rollback: revert 1 commit — không schema/data.
- Risks: ACL tree thay đổi menu hiển thị restricted role (BC note trong acl.xml comment).

## 2. `fix(vietnamaddress): PRE-2025 snapshot contract, sha256 guard, exact keyset resync patch`

- Files: `app/code/Secomm/VietNamAddress/**` (9+ manifest files + manifest JSON + Resync patch
  + tests).
- Dependency: none (independent).
- Tests: VietNamAddress suite (192/776) + reconciliation tool trên DB staging per runbook §A.
- Rollback: revert commit; patch đã apply trên env → patch_list cần thủ công xoá entry nếu
  rollback dữ liệu (ghi trong commit body).

## 3. `fix(launchpad/mptablerate): first-save settings, recoverable partial save, quote-based customer group`

- Files: `app/code/Launchpad/MageplazaTableRate/**` Batch C (7+ files: SettingsCapture,
  MethodResourcePlugin, MethodSavePlugin rewrite, SettingsPersister transaction, 
  LaunchpadMethod preference, tests).
- Dependency: Mageplaza vendor (read-only); ShippingCore committed contracts.
- Tests: Launchpad suite (93/175).
- Rollback: revert — settings trở về first-save-broken (ghi known-issue khi rollback).

## 4. `feat(ghn): wire rate path through CarrierRateExecutionService (FEAT-QA23PZ)`

- Files: `app/code/Secomm/Ghn/Model/Carrier/Ghn.php` (wiring hunks), `Model/Rate/*` contributor
  + factory, `Model/Config` scope/mode accessors, `etc/adminhtml/system.xml`, zone execution
  tests — theo manifest `diff-manifest-feat-qa23pz-ghn.txt` TRỪ các recordDecision hunks.
- Dependency: ShippingCore 0.19/0.20 contracts; independent from commit 5 EXCEPT Ghn.php
  recordDecision hunks (xem squash note).
- Tests: Ghn suite (368).
- Rollback: revert — GHN quay lại self-orchestration (collector vẫn nhận outcome).

## 5. `feat(shippingcore): atomic decision transport + full decision identity + catch-all reason`

- Files: `ShippingCore/Api/Rate/CarrierRateDecisionRecord*`, `CarrierRateOutcomeCollector*`,
  `ShippingFailureReason` (+UNEXPECTED_RUNTIME_FAILURE), tests (15 decision + 10 flow).
- Dependency: none new (additive).
- Tests: ShippingCore 424/1113.

## 6. `fix(ghn): transported decision records + success commit point + catch-all fail-closed`

- Files: Ghn.php recordDecision hunks + `ShippingFailureReason` nếu chưa ở commit 5 + API
  fixture tests (`CalculatorApiClientFixtureTest`, `RealtimeContributorCallCountTest`,
  `GhnTransportContractTest`).
- Overlap: Ghn.php chứa cả commit 4+6 hunks → nếu tách commit gây intermediate không compile,
  SQUASH 4+6 thành một commit `feat(ghn): execution-service wiring with transported decisions
  and fail-closed catch-all`.
- Tests: Ghn 368/210,924.

## 7. `fix(launchpad): coordinator consumes transported eligibility (compat path for legacy carriers)`

- Files: FallbackCoordinator + tests.
- Tests: 93/175 + composition matrices.

## 8. `test: production-composed D3 matrices + call-count fixtures`

- Files: RateCollectionMatrixTest, PolicyConfigMatrixTest, CalculatorApiClientFixtureTest,
  RealtimeContributorCallCountTest, FlowIntegrationTest (nếu chưa ở 5).
- Tests: toàn bộ mới, 0 fail.

## 9. `docs: governance/evidence/runbook/review packages`

- Files: `.ai/**` (CURRENT_STATE, architecture, evidence TASK-SEC-AUDIT/*, review-packages,
  runbook, commit-plan).

## Overlap/squash notes

- Ghn.php: nếu tách 4/6 gây intermediate broken → squash 4+6 (đã ghi).
- `FallbackCoordinator.php`: hunks thuộc FEAT-QA23PZ (OUT_OF_SCOPE guard) + transport
  (consume-first) → đưa cả hai hunks vào commit 7 (một owner Launchpad).
- Governance files đa stream: commit cuối, mô tả từng stream trong body.
