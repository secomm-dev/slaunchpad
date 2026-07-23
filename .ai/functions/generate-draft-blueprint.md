# generate-draft-blueprint

> Function (VI). Initialization phase. Generates Draft PROJECT_AI_BLUEPRINT.md from assessment + discovery.

## Purpose
Generate draft blueprint from: client request, source assessment, discovery questions, discovery review, meeting notes, assumptions, risks. Marks `[TBD]`/`[ASSUMPTION]`/`[UNKNOWN]`/`[RISK]`. NOT for final generation.
For migration projects, the assessment must already include the technical audit, capability inventory, target mapping, gaps, complexity, discovery focus, and estimation drivers.

## Trigger
- Intent: "Generate the draft blueprint"
- State 8 (Draft Blueprint Ready)

## Required inputs
- `CURRENT_SYSTEM_ASSESSMENT.md` (if source audit done)
- `DISCOVERY_REVIEW.md` (answered/unanswered/conflicts/assumptions/risks)
- Client brief / meeting notes

## Required project files to read
- `blueprint/PROJECT_AI_BLUEPRINT.template.md` (target structure)
- `shared-core/initialization/BLUEPRINT_DRAFT_RULES.md`
- `templates/PROJECT_AI_BLUEPRINT_DRAFT.template.md`

## Execution steps
1. Load context (assessment + discovery review) 2. Memory (RESEARCH_NOTES, DECISIONS) 3. Rules (draft rules) 4. (no skill — uses blueprint-generation-prompt) 5. Agent (BA/SA) 6. Research: resolve what can be resolved from assessment + discovery 7. Execute: fill blueprint sections; mark unknowns explicitly 8. Validate: all 16 sections present; unknowns marked (not hidden) 9. Evidence: draft blueprint 10. Memory: DECISIONS (preliminary) 11. Next: Blueprint Under Review

## Output
`PROJECT_AI_BLUEPRINT_DRAFT.md` with `document_status = draft`.

## Evidence
Draft blueprint file.

## Memory
DECISIONS (preliminary decisions from discovery); RESEARCH_NOTES (remaining unknowns).

## Failure handling
- Not enough info for draft → mark all unknown sections `[TBD]`; proceed (draft is allowed to be incomplete).
- Conflicting discovery info → document conflict in `[RISK]`; proceed.
