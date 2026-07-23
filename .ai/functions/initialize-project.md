# initialize-project

> Function (VI). Initialization phase. Entry point cho project initialization workflow.

## Purpose
Entry point — receives client request, sets up delivery workspace, creates `.ai/initialization/`, starts initialization flow.

## Trigger
- Intent: "Initialize a new project" / "Start project setup"
- Function invoked by: initialization workflow state 1 (Request Received)

## Required inputs
- Client brief / CR / email / meeting notes
- (Optional) source code access

## Required project files to read
- `blueprint/PROJECT_AI_BLUEPRINT.template.md` (to know the target structure)
- `shared-core/initialization/PROJECT_INITIALIZATION_WORKFLOW.md`

## Dependencies
- Rule: `planning-first.md`, `research-first.md`
- Hook: `before-task`

## Execution steps (11-step)
1. Load context (client brief) 2. Memory (none yet) 3. Rules 4. (no skill) 5. Agent (BA/TL) 6. Research: determine project type from brief 7. Execute: ask project name, type, workspace mode; create `.ai/initialization/`; set `CURRENT_INITIALIZATION_STATE.md` = Request Received 8. Validate: workspace created 9. Evidence: workspace structure 10. Memory: INIT_STATE created 11. Next: guide to Source Audit or Discovery

## Output
`.ai/initialization/` with `CURRENT_INITIALIZATION_STATE.md` (state = Request Received).

## Evidence
Workspace structure screenshot/ls.

## Memory
Create `CURRENT_INITIALIZATION_STATE.md`.

## Failure handling
- Missing project type → ask user.
- Missing workspace mode → default to single-project.
