# Evidence Policy

> **Ngôn ngữ:** Vietnamese (guidance); technical terms stay English. File này là nguồn (source) copy vào `.ai/evidence/evidence-policy.md`. Thư mục `.ai/evidence/` là nơi lưu evidence runtime.

**Quy tắc cốt lõi: Done requires evidence.**

Không task nào được mark "done" (Definition of Done) nếu không có evidence. "AI nói xong" không phải evidence. "Code chạy được trên máy tôi" không phải evidence. Evidence phải là **artifact có thể verify**.

> Evidence bổ sung — không thay thế — Hard Gate và human review ([`core/no-ai-blind-trust.md`](../../core/no-ai-blind-trust.md)). Evidence chứng minh việc đã làm; human review chứng minh việc làm đúng.

---

## Loại evidence (9 loại)

Mỗi loại có chỗ lưu phù hợp. Không phải mọi task cần mọi loại — nhưng mọi task "done" cần ít nhất một.

| # | Loại | Khi nào | Lưu ở đâu |
|---|------|---------|-----------|
| 1 | **Command output** | Build, test command, migration, deploy command | `.ai/evidence/{task}/command-output.txt` (excerpt, không full log) |
| 2 | **Test result** | Unit/integration test pass/fail | `.ai/evidence/{task}/test-result.md` (pass count, failed cases, regression check) |
| 3 | **Screenshot** | UI change, visual fix, UAT | `.ai/evidence/{task}/screenshot-*.png` + caption |
| 4 | **Log excerpt** | Debugging, incident, monitoring confirm | `.ai/evidence/{task}/log-excerpt.md` (relevant lines, mask secret/PII) |
| 5 | **Code diff summary** | PR, refactor, scope-confirm | `.ai/evidence/{task}/diff-summary.md` (files + dòng changed + tại sao) |
| 6 | **Deployment checklist** | Release | `.ai/evidence/{task}/deploy.md` (completed) |
| 7 | **QA confirmation** | Mode A/B QC pass | `.ai/evidence/{task}/qa-signoff.md` (TC pass, regression clean) |
| 8 | **Client approval** | UAT, change request, go-live | `.ai/evidence/{task}/client-approval.md` (who, when, scope) |
| 9 | **TL approval** | Code review, release gate, escalation | `.ai/evidence/{task}/tl-approval.md` (PR + reviewer + date) |

---

## Quy tắc evidence

1. **Mask secret/PII** trong mọi evidence. Không bao giờ paste token/credential/PII thật. (Xem [`core/production-ai-security.md`](../../core/production-ai-security.md) §5–6.)
2. **Excerpt, không full dump.** Lấy dòng relevant + context, không paste cả log file.
3. **Link, không copy.** Nếu evidence đã ở chỗ khác (PR, ticket, QC report), link đến nó thay vì duplicate.
4. **Trace về task.** Mỗi evidence nằm dưới `.ai/evidence/{ticket_id}/` để trace được.
5. **Không evidence giả.** Không fabricate output/screenshot. Thiếu evidence thật = task chưa done.

---

## Done requires evidence (rule)

Trong Definition of Done ([`core/definition-of-done.md`](../../core/definition-of-done.md)), thêm:

- [ ] Có ít nhất một evidence artifact trong `.ai/evidence/{task}/` (command output / test result / screenshot / approval)
- [ ] Evidence không chứa secret/PII
- [ ] Evidence trace được về ticket/spec

Task "chỉ là config change" cũng cần evidence (screenshot hoặc log excerpt xác nhận change apply + verify). Task "documentation only" cần evidence là diff summary.

> Auditor kiểm evidence trong AI Output Audit ([`workflow-guides/audit-workflows.md`](../../workflow-guides/audit-workflows.md) §8) và Deployment Readiness Audit (§9).

---

## .ai/evidence/ structure (generated)

```
.ai/evidence/
├── evidence-policy.md       ← file này (copy from shared-core)
└── {ticket_id}/             ← tạo runtime, mỗi task một thư mục
    ├── command-output.txt
    ├── test-result.md
    └── ...
```

Thư mục `{ticket_id}/` là **project-owned runtime** — không generate sẵn, tạo khi cần.

---

## Cross-References

- Rule "Done requires evidence": [`shared-core/rules/evidence-required.md`](../rules/evidence-required.md)
- Instinct #7, #11: [`shared-core/instincts/instincts.md`](../instincts/instincts.md)
- Definition of Done: [`core/definition-of-done.md`](../../core/definition-of-done.md)
- No AI Blind Trust: [`core/no-ai-blind-trust.md`](../../core/no-ai-blind-trust.md)
- Security (mask secret/PII): [`core/production-ai-security.md`](../../core/production-ai-security.md)
- Audits dùng evidence: [`workflow-guides/audit-workflows.md`](../../workflow-guides/audit-workflows.md)
