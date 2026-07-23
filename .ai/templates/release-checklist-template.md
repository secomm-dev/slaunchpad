# Release Checklist: {Tên release / version}

> Release/deployment checklist. Hard Gate 5: No Production Deploy Without Release Gate. Output của `deploy` skill / `/deploy`.

## Metadata

| Field | Value |
|-------|-------|
| Release | {version / tag} |
| Type | Feature / Hotfix / Maintenance |
| Window | {date / time} |
| Owner | {DevOps} |
| Rollback owner | |

## 1. Pre-deploy

- [ ] Code merged + reviewed (TL sign-off)
- [ ] QC pass (happy/edge/negative) — `testcase`
- [ ] High-risk validation L3 (nếu chạm payment/checkout/auth/secret/DB) — PASS
- [ ] Staging smoke pass
- [ ] Backup DB / snapshot (nếu migration)
- [ ] Rollback plan ready (xem mục 4)
- [ ] Stakeholders notified + window confirmed

## 2. Deploy steps

1. {step — command / portal / pipeline}
2. {step}
3. {verify sau mỗi step}

## 3. Post-deploy verify

- [ ] Smoke test production (critical path: search → PDP → cart → checkout → order)
- [ ] Monitor errors / latency ({window})
- [ ] No new S0/S1 alerts
- [ ] Business confirm (PM/Client)

## 4. Rollback triggers + steps

**Rollback triggers:**
- {error rate > X} / {checkout fail > Y} / {critical feature broken}

**Rollback steps:**
1. {revert deploy / restore DB snapshot / feature flag off}
2. {verify}
3. {notify}

## 5. Release record

- Deployed at: {ISO8601}
- Outcome: success / rolled back
- Change log → `change-log-template.md`
