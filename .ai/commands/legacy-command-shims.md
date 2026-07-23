# Legacy Command Shims

> **Language:** English (operational snippets). Copied per-shim into `.ai/commands/{name}.md`. These keep legacy command names working against the v4 workflow.

Each shim below is the **prompt snippet** a user invokes. Shims that map to a real Claude Code skill say so; others are pure snippets usable in any tool (Claude Code / Codex / Copilot chat). Output language follows the project `output_language` (skill runtime rule).

> All shims enforce: planning-first, no-scope-creep, human-review-before-merge, evidence-required. They do NOT bypass Hard Gates.

---

## `/plan` — implementation plan

```
Read AGENTS.md + relevant project-context/ + this ticket: {ticket}.
Produce an implementation plan (Mode A/B) or approach note (Mode C): files to modify, step-by-step changes, regression risks, test approach.
Reference: skills-source/task (analysis) + templates/implementation-plan-template.md.
Do NOT write code yet — plan only.
```

## `/spec` — generate spec/mini-spec

```
Generate a {feature-spec|mini-spec} for: {requirement/ticket}.
Read project-context/02_BUSINESS_RULES.md for relevant business rules.
Output per templates/feature-spec-template.md (Mode A) or mini-spec-template.md (Mode B).
Skill: spec.
```

## `/task` — analyze ticket

```
Analyze this ticket against AGENTS.md + project-context/: {ticket}.
Output: affected areas, risks (with severity), dependencies, effort range, escalation needed (yes/no + tier).
Skill: task.
```

## `/review` — AI pre-review

```
AI pre-review these changes: {diff/files} for {ticket}.
Check against AGENTS.md §7.2, project-context/02 business rules, §12 high-risk areas.
Output: Critical / Warning / Notes, scope check, regression risks, recommendation (PASS / PASS WITH WARNINGS / NEEDS FIX).
Skill: review-code. If security-sensitive, also run security-review.
```

## `/audit` — run an audit

```
Run the {architecture|code-quality|security|performance|magento|shopify|hyva|api|deployment|documentation|estimation|ai-output} audit for this project.
Follow workflow-guides/audit-workflows.md for objective, inputs, checklist, severity (S0-S3), output format, next actions.
Output evidence-backed findings; verdict.
```

## `/estimate` — effort estimate

```
Estimate effort for: {ticket/feature}.
Base on task output + similar patterns in LESSONS_LEARNED/CONTINUOUS_LEARNING.
Output: range (Xh-Yh), drivers, unknowns that widen the range, escalation if > mode threshold.
```

## `/compact-context` — compact session to memory

```
Compact the current session into project-context/memory/: CURRENT_STATE.md, NEXT_TASK.md (replace stale); append DECISIONS.md / LESSONS_LEARNED.md; risk diff for 06.
Skill: compact-context. Preserve "why". No secrets/logs/scratch.
```

## `/resume-work` — resume from memory

```
Reconstruct working state from project-context/memory/ (CURRENT_STATE, NEXT_TASK, DECISIONS, LESSONS_LEARNED) + standing project-context/.
Confirm with human before changing code (planning-first).
Skill: continue.
```

## `/update-memory` — refresh memory + context diff

```
Refresh CURRENT_STATE.md + NEXT_TASK.md to current state. If business rules/architecture/modules changed, generate a diff for project-context/ (human review).
Skills: compact-context (memory) + update-memory (context diff).
```

## `/record-decision` — append an ADR

```
Append an Architecture Decision Record to project-context/memory/DECISIONS.md:
- Context: {why decided}
- Decision: {what decided}
- Rationale: {why this option}
- Consequences: {impact}
- Status: Accepted
Format: templates/decisions-template.md (ADR-NNNN).
```

## `/record-risk` — add a risk

```
Add a risk to project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md (single source — do NOT create a separate KNOWN_RISKS file):
- Risk: {description}
- Area: {module/integration}
- Severity: {high/medium/low}
- Owner: {role}
- Mitigation: {action}
Human review before commit.
```

## `/record-lesson` — append a lesson

```
Append to project-context/memory/LESSONS_LEARNED.md (retro/incident) or CONTINUOUS_LEARNING.md (in-flight):
- Lesson: {what learned}
- Source: {ticket/incident/retro}
- Type: {repeat|avoid}
- Action/prevention: {change}
Format: templates/lessons-learned-template.md. No blame; summarize; link evidence.
```

---

## Tool-support note

- **Claude Code**: real slash commands via skills where a skill exists; snippets otherwise. The `.ai/commands/*.md` files are read by the AI when the user invokes the name.
- **Codex / Copilot**: no real slash-command support — these are **prompt snippets**. The user pastes the snippet body into chat. Document this in the generated `.ai/commands/README.md`.

## Cross-References

- Index: `commands-index.md`
- Skills: `skills-source/INDEX.md`
- Memory standard: `core/project-memory-standard.md`
- Audit workflows: `workflow-guides/audit-workflows.md`
