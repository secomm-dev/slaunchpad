# TASK-PMKWS6 Implementation Plan — Expiry cron: verify + cancel

| Field | Value |
|---|---|
| Specification | tickets/TASK-PMKWS6-paymentcore-expiry-cron.md (`## Mini Spec`) · canonical parent SPEC-FEAT-CSWYEJ §4.1/§4.7, AC-008→AC-012 |
| Decisions | DEC-FEATCSWYEJ-001 (D3 gốc 3 lớp) → **DEC-FEATCSWYEJ-004 rev**: bỏ querydr, expired + pending = not paid · DEC-003 (window 15') |

> **Mode A** · Tier 2 (order cancel) · Status: **Retro-canonical** — Dev complete theo DEC-004 (2 lớp race guard); runtime command path verified.

---

## PART 1 — ANALYSIS

| Câu hỏi | Kết luận | Nguồn |
|---|---|---|
| Race guard bao nhiêu lớp? | **2 lớp** sau DEC-004: per-order lock + state reload/canCancel. Layer querydr bị remove — order state là payment truth (IPN chuyển state trong vài giây khi trả tiền thật) | DEC-FEATCSWYEJ-004 |
| Trade-off đã chấp nhận? | Khách trả sát cuối window + IPN delay > window → cancel nhầm. TL chấp nhận (window 15' hẹp); ops uncancel thủ công nếu xảy ra | DEC-004 Consequences |
| Cron đọc enabled flag? | **Không** — snapshot semantics (D5): disable chỉ dừng record mới, record cũ vẫn drain | DEC-FEATCSWYEJ-001 D5 |
| Cron group? | `secomm_paymentcore` riêng, 5', `use_separate_process=1` — không block default group | spec §4.1 |
| crontab.xml format? | `schedule` phải đứng TRƯỚC `config_path` (XSD); đã bỏ config_path (batch_size đọc runtime) | QC session 2026-08-25 |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | Cron registration | etc/crontab.xml · etc/cron_groups.xml | ✅ (fix XSD order runtime) |
| 2 | Batch entry + force-close (D3b 7 ngày) | Model/Lifecycle/ExpirePayments.php | ✅ |
| 3 | Per-order cancel (lock + state reload) | Model/Lifecycle/CancelExpiredOrder.php | ✅ (rev DEC-004: bỏ querydr) |
| 4 | Unit test matrix (cancel/paid/canceled/throw/lock/canCancel-false) | Test/Unit/Model/Lifecycle/CancelExpiredOrderTest.php | ✅ code |
| 5 | Runtime verify command path | `paymentcore:expire:run` chạy qua pipeline, log đúng channel | ✅ user QC |

## Remaining steps (human)

1. QC matrix S-5 (đợi hết hạn thật → cancel + salable qty trả — verify MSI compensation).
2. QC S-7 rev (thanh toán xong + IPN xử lý → cron không cancel).
3. TL review Tier-2 — đặc biệt đọc DEC-004 trade-off trước khi approve.

## Verification summary

| Mini-Spec clause | Bằng chứng |
|---|---|
| Cancel qua OrderManagementInterface (không SQL) | CancelExpiredOrder::cancelIfStillPending |
| Cron không chết khi 1 record fail | try/catch per-record + log `[cancel_failed]` |
| Force-close không cancel order | ExpirePayments::forceClose — chỉ set status error |
