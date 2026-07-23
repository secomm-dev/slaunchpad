# Hooks Index

> **Language:** English (meta). Hook definitions (Vietnamese, thin) live in `HOOKS.md`. Each hook points to a checklist in `checklists/` (no duplication).

Hooks are **checklist-based, not runtime-enforced** (hard constraint: no runtime hooks, no external deps). The generated `.ai/hooks/{name}.md` is a thin doc: purpose / when-triggered / checklist-pointer / required-evidence / failure-handling.

> Reuse: the 10 hooks map to existing checklists where they exist. v4 adds 2 genuinely missing checklists: `before-dependency-install`, `before-shell-command`.

---

## Hook → checklist mapping

| Hook (`.ai/hooks/`) | Checklist (`checklists/`) | Status |
|---|---|---|
| `before-task.md` | `pre-task-checklist.md` | existing |
| `after-task.md` | `post-task-checklist.md` | existing |
| `before-commit.md` | `pre-commit-checklist.md` | existing |
| `before-pr.md` | `ai-pre-review-checklist.md` (+ `code-review-checklist.md` TL side) | existing |
| `before-deploy.md` | `release-checklist.md` (pre-deploy section) | existing |
| `after-deploy.md` | `release-checklist.md` (post-deploy section) + `post-release-context-update-checklist.md` | existing |
| `before-client-handoff.md` | `client-handoff-checklist.md` | existing |
| `before-dependency-install.md` | `before-dependency-install-checklist.md` | **NEW (v4)** |
| `before-shell-command.md` | `before-shell-command-checklist.md` | **NEW (v4)** |
| `before-security-sensitive-change.md` | `security-review-checklist.md` | existing |
| `before-ai-tool-update.md` | `shared-core/rules/ai-tool-self-update.md` | **NEW (v4 self-update)** |
| `after-ai-tool-update.md` | `shared-core/rules/ai-tool-self-update.md` | **NEW (v4 self-update)** |

## Generation

- Copy each hook's definition (from `HOOKS.md`) into `.ai/hooks/{name}.md` (Vietnamese).
- Each hook references its checklist by relative path.
- All 12 hooks are generated for every project (deterministic); project may extend under `--- END GENERATED ---`.

## Cross-References

- Hook definitions: `HOOKS.md`
- Checklists folder: `checklists/` (+ `checklists/README.md` lifecycle sequence)
- Security baseline hooks: `shared-core/memory/security-baseline-template.md` §8
- Instincts (when hooks fire): `shared-core/instincts/instincts.md`
