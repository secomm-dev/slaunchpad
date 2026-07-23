# fix-bug

> Function (VI guidance). Copy vào `.ai/functions/fix-bug.md`. Lifecycle area: **workflow**. Orchestration recipe — depends trên skill `task`/`review-code`, KHÔNG duplicate.

## Purpose
Reproduce → root-cause → minimal fix → test → evidence cho một bug fix, trong scope, không scope creep.

## When to use
- Khi một bug được report (ticket/production) và cần fix.
- Mode C (maintenance) hoặc Mode D (hotfix) bug fix.

## Trigger
- Command: `/fix-bug` (alias prompt snippet)
- Prompt snippet: "Fix bug {ticket}: reproduce → root-cause → minimal fix → test → evidence. Read AGENTS.md §12 (high-risk) + §7.2 (coding standard) + project-context/06. Trong scope; không refactor."

## Required inputs
- Bug report (ticket, steps to reproduce, expected vs actual)
- (Optional) error log, stack trace

## Required project files to read
- `AGENTS.md` (§12 high-risk area, §7.2 coding standard)
- `project-context/02_BUSINESS_RULES.md` (rule affect bug), `04_CUSTOM_MODULES_AND_CODE_AREAS.md`, `06_KNOWN_CONSTRAINTS_AND_RISKS.md`
- `RESEARCH_NOTES.md`, `CONTINUOUS_LEARNING.md` (recurring bug pattern)

## Required agents
- developer (primary), tl (review)

## Required skills
- `task` (impact analysis), `review-code` (before merge), `testcase` (test case)

## Required rules
- `planning-first.md` (short plan trước fix), `security-first.md` (nếu touch security area), `backward-compatibility.md`, `evidence-required.md`

## Required hooks
- `before-task`, `before-commit`, `before-pr`

## Required memory files
- Read: `CURRENT_STATE.md`, `RESEARCH_NOTES.md`, `CONTINUOUS_LEARNING.md`, `LESSONS_LEARNED.md`
- Update: `CURRENT_STATE.md` (status), `CONTINUOUS_LEARNING.md` (bug pattern), `project-context/06` (risk resolved/new)

## Required evidence
- `.ai/evidence/{ticket}/`: reproducing test + fix diff summary + test-result (green)

## Execution steps (11-step runtime pattern)
1. **Load context**: AGENTS.md §12 + §7.2 + project-context/02/04/06 (skip nếu đã load trong run này — function-template §Efficiency)
2. **Load memory**: CURRENT_STATE, RESEARCH_NOTES, CONTINUOUS_LEARNING (recurring pattern)
3. **Load rules**: planning-first (short plan), security-first (nếu relevant), evidence-required
4. **Load skills**: task (impact), testcase (test case)
5. **Load agent**: developer
6. **Research (if needed)**: reproduce bug, inspect code/log → root cause (confirmed, không suspected)
7. **Execute**: viết reproducing test → minimal fix (stop the bleeding, không refactor) → run test green
8. **Validate**: pre-review (no critical); scope clean; business rule (02) respect
9. **Collect evidence**: reproducing test + fix diff + test-result green → `.ai/evidence/{ticket}/`
10. **Update memory**: CURRENT_STATE (status), CONTINUOUS_LEARNING (bug pattern), 06 (risk)
11. **Next action**: prepare-pr → TL review; nếu production incident → Mode D hotfix workflow

## Output format
Bug fix report: root cause (confirmed), fix (what changed, minimal), test (reproducing + green), regression risk, evidence ref.

## Failure handling
- Root cause chưa confirm (suspect) → không fix; research thêm.
- Fix touch high-risk area (payment/checkout/order/DB/security) → escalate Tier 2 trước.
- Test không green → không mark done; fix tiếp.
- Scope creep (fix tiện refactor) → reject; giữ minimal.

## Memory update rules
- `CURRENT_STATE.md`: replace (reflect now)
- `CONTINUOUS_LEARNING.md`: append (bug pattern — repeat/avoid)
- `project-context/06`: risk resolved → mark resolved; new risk → add (diff + human review)
- `LESSONS_LEARNED.md`: append nếu post-incident

## Related audits
- Code Quality audit (bug pattern recurring?), AI Output audit (AI tuân fix-bug process?)

## Related standards
`.ai/project-context/engineering-standards/`: ENGINEERING_PRINCIPLES → DEVELOPMENT, CODING, TESTING, SECURITY, REVIEW + `technologies/{tech}`.

## When to improve/update
- Khi bug type recurrent miss (e.g., cache invalidation bug không được check) → thêm checklist; record `CONTINUOUS_LEARNING.md`.
