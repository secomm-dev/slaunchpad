# Commands Index

> **Language:** English (meta/mapping). The generated `.ai/commands/` holds prompt-snippet shims (per-tool notes).

Legacy command shims map familiar command names to the v4 workflow so existing team habits keep working. **Tools differ in command support** — shims are delivered as prompt snippets (universally usable), with Claude Code skills referenced where a real skill exists.

> Constraint: only Claude Code, Codex, GitHub Copilot are supported. Tools without real slash-command support use these as **prompt snippets** (paste into chat), not executable commands.

> **Source of truth:** [`../registry/toolkit-capability-registry.yaml`](../registry/toolkit-capability-registry.yaml). The table below is a human-readable render; every command name + adapter must match the registry. Validate with `bash ../bin/project-ai-validate --check-commands`. `/review` is an alias of canonical `/review-code`; `/resume-work` is an alias of `/continue`; `/tl-review` is **deprecated** (an approval gate, not a command).

---

## Command → v4 mapping

| Command | Maps to | Real skill? | Codex prompt | Copilot prompt |
|---|---|---|---|---|
| `/plan` | Planning-First Sequence → implementation plan | — (use `task` skill output + plan template) | `codex/prompts/implementation-plan.md` | `copilot/prompts/implementation-plan.prompt.md` |
| `/spec` | Spec generation (feature/mini-spec) | `spec` skill | — (draft via skill) | — |
| `/task` | Ticket analysis (risks, affected areas, effort) | `task` skill | `codex/prompts/analyze-ticket.md` | — |
| `/review` | AI pre-review before TL review | `review-code` skill (+ `security-review` if sensitive) | `codex/prompts/code-review.md` | `copilot/prompts/code-review.prompt.md` |
| `/audit` | Run an audit workflow | — (use `workflow-guides/audit-workflows.md`) | — | — |
| `/estimate` | Effort estimate from ticket analysis | `task` skill (effort field) | — | — |
| `/compact-context` | Compact session into memory files | `compact-context` skill | — | — |
| `/resume-work` | Reconstruct state from memory files | `continue` skill | — | — |
| `/update-memory` | Refresh CURRENT_STATE/NEXT_TASK + context diff | `compact-context` + `update-memory` skills | — | — |
| `/record-decision` | Append an ADR to DECISIONS.md | — (shim) | — | — |
| `/record-risk` | Add risk to project-context/06 | — (shim) | — | — |
| `/record-lesson` | Append to LESSONS_LEARNED / CONTINUOUS_LEARNING | — (shim) | — | — |

## How shims are delivered (generated `.ai/commands/`)

- **Claude Code**: where a real skill exists (`/spec`, `/task`, `/review`, `/compact-context`, `/resume-work`), the command references the skill in `.claude/skills/`. Where no skill exists (`/record-decision`, `/record-risk`, `/record-lesson`, `/audit`, `/estimate`, `/update-memory`), the shim is a **prompt snippet** (`.ai/commands/{name}.md`) the user pastes/invokes.
- **Codex**: shim = prompt snippet referencing `codex/prompts/*` where one exists.
- **Copilot**: shim = prompt snippet referencing `copilot/prompts/*` where one exists.

> Shim files (prompt snippets) are **English** (they reference English skills/prompts and are operational). Each shim states: purpose, what it invokes, expected output, and the tool-specific note (skill vs snippet).

## Cross-References

- Shim definitions: `legacy-command-shims.md`
- Skills: `skills-source/INDEX.md`
- Codex prompts: `codex/prompts/`
- Copilot prompts: `copilot/prompts/`
- Audit workflows: `workflow-guides/audit-workflows.md`
