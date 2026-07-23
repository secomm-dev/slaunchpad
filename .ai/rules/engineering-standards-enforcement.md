# Rule: Engineering-Standards-Enforcement

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/engineering-standards-enforcement.md`. Engineering Standards là **first-class + enforceable**, không chỉ documentation.

## Rule

Engineering Standards (`.ai/project-context/engineering-standards/`) phải được **load trước + validate sau** ở mỗi engineering action. Standards drive agent behavior, function execution, hook, rule, audit — không chỉ doc.

- **Trước action (function)**: load các Engineering Standard mà capability matrix chỉ định (function "Required Engineering Standards"). Apply `ENGINEERING_PRINCIPLES` trước (priority cao nhất); rồi category + technology standard (tech override shared — more specific wins).
- **Validation (hook)**: hook validate standards — `before-commit` validate Development/Coding; `before-pr` validate Review; `before-deploy` validate Deployment + critical Security.
- **Audit**: audit dùng standards làm criteria → severity (S0–S3) + improvement recommendation.
- **Block**: rule này (với các rule khác) **có thể block**:
  - `planning-first` / `research-first` → block implementation nếu chưa research/plan.
  - `evidence-required` → block completion nếu chưa có evidence.
  - `security-first` → block deployment nếu chưa pass security.
  - `architecture-review-required` → block major refactoring nếu chưa SA review (capability Architecture Design).

## Khi nào apply

- Mọi implementation/review/refactor/test/deploy (function load standards trước).
- Mỗi commit/PR/deploy (hook validate).
- Mỗi audit (standards = criteria).

## Enforce

- Function có field "Required Engineering Standards" (matrix-driven) — load trước step.
- Hook `before-task`/`before-commit`/`before-pr`/`before-deploy` validate standards.
- `CODING_RULES.md` (`[BLOCK]`/`[WARN]`) là enforcement-grade compact rules; standards là rationale — chúng bổ sung, không duplicate.
- Auditor check standards-compliance trong Code Quality / AI Output audit.

## Liên kết

- Standards library: `.ai/project-context/engineering-standards/` + `ENGINEERING_CAPABILITY_MATRIX.md`
- Compact enforcement: `CODING_RULES.md` (từ `coding-rules/`)
- Rule kèm: `planning-first.md`, `research-first.md`, `security-first.md`, `evidence-required.md`, `backward-compatibility.md`
- Instincts (top): `ENGINEERING_PRINCIPLES.md` → `shared-core/instincts/instincts.md`
- Functions: `implement-task`, `review-code`, `audit-code-quality`, `refactor-code`, `generate-unit-test`, `prepare-pr`
- Hooks: `before-task`, `before-commit`, `before-pr`, `before-deploy`
