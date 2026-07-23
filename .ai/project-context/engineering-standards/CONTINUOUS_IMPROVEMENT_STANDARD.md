# CONTINUOUS_IMPROVEMENT_STANDARD

> Engineering standard — Continuous improvement. English. Wires to the `update-project-ai-tool` self-update subsystem.

## Purpose / Scope / Applicability
Evolve standards and capture learning during delivery. Applies continuously (not just retro).

## Mandatory Rules
- When a better practice is found: proposal → review → approve → update project standard → record.
- Every standard change updates `CHANGELOG_AI_TOOL.md` (version/date/file/reason).
- Significant changes get an ADR in `DECISIONS.md`; reusable lessons go to `CONTINUOUS_LEARNING.md`.
- Project-specific changes stay in the project; reusable ones → propose backport to the toolkit.
- Security-affecting changes → `audit-security` first.
- Never overwrite project memory blind; never delete a rule without recording the decision.

## Recommended Practices
- Mark changed artifacts with version/date; deprecate, don't silently delete.
- Periodic review of `CONTINUOUS_LEARNING.md` + `LESSONS_LEARNED.md` to promote patterns into standards.

## Anti-patterns
Silent standard changes; deleting rules without ADR; pushing one-off fixes into the toolkit without review; memory overwrite blind.

## Validation Checklist
- [ ] Change logged in `CHANGELOG_AI_TOOL.md` + version marked
- [ ] ADR for significant changes; lesson recorded
- [ ] Project-specific kept local; reusable proposed for backport
- [ ] Security change audited first

## Related
**Agents**: tl, sa, project-auditor · **Functions**: update-project-ai-tool, record-decision, record-lesson · **Rules**: ai-tool-self-update, no-duplicate-knowledge, engineering-standards-enforcement · **Hooks**: before-ai-tool-update, after-ai-tool-update · **Audits**: AI Output, Documentation · **Memory**: CHANGELOG_AI_TOOL, DECISIONS, CONTINUOUS_LEARNING
