# Rule: No-Duplicate-Knowledge

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/no-duplicate-knowledge.md`. Nguyên tắc xuyên suốt toolkit (single source of truth).

## Rule

**Một sự thật ở một chỗ. Link, không copy.** Khi thông tin đã có ở đâu đó (project-context, AGENTS.md, DECISIONS.md, LESSONS_LEARNED), reference nó — không viết lại. Risk → `project-context/06` (không tạo `KNOWN_RISKS.md` riêng). Business rule → `02_BUSINESS_RULES.md`. Coding standard → `CODING_RULES.md`. Convention phát hiện → `CONTINUOUS_LEARNING.md`.

## Canonical records (Phase 1a)

Một work item = MỘT canonical record. Không tách rẽ thành nhiều file cùng nói một thứ:

- **Feature mới** → một file `.ai/records/features/FEAT-*.md` (context + AC + approach/decisions + implementation notes + test summary + compatibility + references). KHÔNG tự động sinh thêm `tickets/`, `specs/`, `plans/`, `testcases/`, `handoff/` riêng cho cùng work item.
- **Bug mới** → một file `.ai/records/bugs/BUG-*.md`.
- **Release** → một file `.ai/records/releases/REL-*.md`.
- **Decision bền vững (mới)** → một file `.ai/records/decisions/DEC-*.md` (canonical store) + **một dòng index** trong `memory/DECISIONS.md` trỏ tới nó (compatibility pointer; Navigator `adr_ref` vẫn resolve qua `DECISIONS.md`). KHÔNG restatement body ở cả hai nơi. Decision phải anchor frontmatter `work_items` tới ≥1 work-item ID (`FEAT-`/`SL-`/`BUG-`/`REL-`; link, không restate) — ngăn re-litigate cùng chủ đề dưới DEC-ID khác khi nhiều staff làm song song. `work_items: []` chỉ hợp lệ khi `decision_type ∈ {process, tooling, governance}`. File naming theo primary work-item (Entry h): `DEC-{CODE}-{NNN}.md` với `{CODE}` = work-item ID bỏ gạch nối (SL-015→SL015), `{NNN}` = sequence 3 chữ số theo work-item — chống trùng tên file giữa các ticket chạy song song; exempt decision giữ legacy global `DEC-NNN.md`, legacy file không rename.
- **Mode A ⇒ decision assessment MANDATORY** ([`core/decision-assessment.md`](../../core/decision-assessment.md)): assess 13 durable-choice dimensions; material choice ⇒ DEC records (status `proposed` OK) + link trong record `decisions:` + DECISIONS.md index. Record khai báo `decision_assessment: material | none-material`. `bin/project-ai-validate --check-records` enforce (Mode A không khai báo assessment, hoặc `material` mà `decisions:` rỗng → FAIL).

**Legacy (retained, non-default):** `tickets/`, `specs/`, `plans/`, `testcases/`, `handoff/` được giữ cho backward compat — không phải default path, không xóa vội. Khi chạm legacy artifact, consolidate vào canonical record (link ngược legacy source trong §References) — không viết lại knowledge ở cả hai nơi.

Frontmatter record là contract — `decisions`/`components` phải LINK (không restate); `bin/project-ai-validate --check-records` kiểm tra link resolve.

## Khi nào apply

- Mỗi khi AI sắp viết lại thông tin đã có.
- Khi tạo memory/rule/instinct mới — check trùng trước.

## Enforce

- Verification: grep no-duplicate trong audit (Code Quality, AI Output) — [`workflow-guides/audit-workflows.md`](../../workflow-guides/audit-workflows.md).
- Rule/instinct file PHẢI reference (không restate) nguồn `core/`.

## Liên kết

- Instinct: #6 — [`shared-core/instincts/instincts.md`](../instincts/instincts.md)
- Single-source design: [`core/project-memory-standard.md`](../../core/project-memory-standard.md) (KNOWN_RISKS = 06)
- AGENTS.md SSOT: `stack-templates/_base/AGENTS.base.md`
