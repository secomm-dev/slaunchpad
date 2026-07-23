# generate-project-toolkit

> Function (VI). Initialization phase → final generation. Generates the full `.ai/` project toolkit from approved blueprint.

## Purpose
The FINAL generation step — runs the full v4 generator pipeline on the approved blueprint, producing all `.ai/` artifacts (agents, skills, functions, rules, hooks, MCP, audits, memory, engineering standards, workflows, intents, enablement kit).

## Trigger
- Intent: "Generate the project toolkit"
- State 11 (Project Toolkit Generated)
- **BLOCKED** if blueprint NOT approved (state ≠ 10)

## Required inputs
- `PROJECT_AI_BLUEPRINT.md` (FINAL — `document_status = reviewed/approved`)
- `BLUEPRINT_READINESS_REPORT.md`: APPROVED FOR GENERATION

## Required project files to read
- `PROJECT_TOOLKIT_GENERATION_RULES.md` (the pipeline)
- `generator-rules/` (all selection rules)

## Execution steps
0. **Resolve target project** (preflight per [project-selection-safety.md](../../generator-rules/project-selection-safety.md)). If not unique → **BLOCK** (enumerate candidates + ask; resume on confirm). If none → BLOCK. 1. **Verify resolved target ≠ generator root** (output-file-rules Rule 0). If equal → **BLOCK** ("Generator repo is not a project write target"). 2. Load context (approved blueprint at `{resolved_target}/.ai/toolkit/`) 3. Validate: `BLUEPRINT_READINESS_REPORT.md` = APPROVED (if NOT → BLOCK) 4. Execute full pipeline (steps 1–17 per GENERATION_RULES):
   - capability detection → matrix → standards/agents/skills/functions/rules/hooks/audits/MCP/memory/intents/workflows selection
   - emit v4 artifacts
   - merge AGENTS.base + overlay
   - generate enablement kit
5. Validate: `GENERATOR_VALIDATION_CHECKLIST` (all sections incl. §23 Navigator + §24 Project Selection Safety) 6. Evidence: validation report 7. Memory: GENERATION_LOG (+ preflight entry), WORKFLOW_HISTORY 8. Next: Enablement Kit Generated / Delivery Ready

## Output
Full `.ai/` toolkit (all v4 artifacts) + enablement kit (WELCOME + guides + reference + examples + learning) — written into the **confirmed target project**, never the generator repo.

## Evidence
`.ai/toolkit/validation-report.md` (PASS).

## Memory
GENERATION_LOG (+ `preflight:` block: resolved_target / resolved_via / candidates / route_selected); WORKFLOW_HISTORY (transition: Blueprint Approved → Toolkit Generated → Delivery Ready).

## Failure handling
- **Target project not uniquely resolved** → **BLOCK**: "Multiple candidate projects: {list}. Confirm the target." (Hard Gate 6)
- **Resolved target == generator root** → **BLOCK**: "Generator repo is not a project write target."
- Blueprint NOT approved → **BLOCK**: "Blueprint not approved. Review + approve first."
- Validation FAIL → report what failed; fix + re-run.
- Quickstart mode → allow draft blueprint + WARNING; full review before production. (Quickstart does NOT bypass Step 0.)
