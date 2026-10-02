# Kế hoạch triển khai: BUG-JC7JWG — MoMo recovery command-pool binding

| Field | Value |
|---|---|
| Ticket / Spec | BUG-JC7JWG / MOMO-03 |
| Specification | Full Spec — [SPEC-TASK-4ZW0WG](../specs/SPEC-TASK-4ZW0WG-momo-lost-ipn-payment-recovery.md) (VALID) |
| Record | [BUG-JC7JWG](../records/bugs/BUG-JC7JWG.md) · external SLP-17 |
| Workflow Mode | A — payment recovery, Tier 2 |
| Date | 2026-10-02 |

## 1. Approach

Use the existing `MoMoCommandPool` virtual type as the `commandPool` argument
for `Secomm\MoMo\Service\PaymentRecovery`, matching the established wiring
for `ReturnProcessor` and other MoMo payment services. This restores the
MOMO-03 query path without changing payment behavior.

## 2. Files affected

| File | Change type | Lý do |
|------|-------------|-------|
| `app/code/Secomm/MoMo/etc/di.xml` | modify | Inject `MoMoCommandPool` into `PaymentRecovery` (AC-001/002) |

## 3. Steps

1. Bind the `PaymentRecovery::commandPool` argument to `MoMoCommandPool` — risk: high — deps: none.
   - Keep the existing command pool and query mapping unchanged.
   - Verify the XML binding and Magento DI construction path.
2. Confirm the recovery query still routes through the existing `query_transaction` command — risk: high — deps: step 1.
   - Run scoped payment DI/QC in a controlled development environment before release.

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Recovery cron cannot query eligible MoMo attempts | high | Bind the configured pool and verify the DI/cron path under Tier 2 QC |
| Unintended change to payment state or order creation | high | DI-only change; compare the MOMO-03 service and lifecycle code unchanged |

## 5. Test approach

- Static: parse `etc/di.xml` and validate the argument names and virtual type.
- Component: verify Magento can construct `PaymentRecovery` with `MoMoCommandPool`.
- High-risk validation (L3): controlled cron-path QC with a safe eligible fixture; do not use production payment data.

## 6. Out of scope

Recovery algorithm, payment classification, order finalization, schema, cron frequency, refunds, and provider API contract.

## 7. Open questions / Escalation

- Tier 2 reviewer to complete DI/cron QC and approve before release, per `.ai/AGENTS.md` §§ 8.6, 9, 11, and 12.
