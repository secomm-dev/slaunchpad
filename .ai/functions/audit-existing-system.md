# audit-existing-system

> Function (VI). Initialization phase. Audits existing source system for migration/maintenance/upgrade.

## Purpose
Perform the technical audit only. Produce the raw evidence that feeds the migration assessment pipeline, not the full migration assessment itself.

## Trigger
- Intent: "Audit the existing system"
- State 3 (Source Audit In Progress)

## Required inputs
- Source code access (repo clone, config files, admin access)

## Required project files to read
- `shared-core/initialization/SOURCE_SYSTEM_AUDIT_GUIDE.md`
- `templates/CURRENT_SYSTEM_ASSESSMENT.template.md`

## Required agents / skills / rules
- Agents: sa, legacy-code-auditor (if legacy)
- Skills: `research-implementation`, `audit-architecture`, `audit-code-quality`
- Rules: `research-first.md`, `security-first.md`
- Research: mandatory (deep for migration; lightweight for maintenance)

## Execution steps
1. Load context (source code) 2. Memory (none yet) 3. Rules 4. Skills (audit-*) 5. Agent (sa) 6. Research: audit per SOURCE_SYSTEM_AUDIT_GUIDE checklist 7. Execute: fill CURRENT_SYSTEM_ASSESSMENT template per area; mark `[VERIFIED]`/`[ASSUMPTION]`/`[UNKNOWN]` 8. Validate: SA/TL review assessment 9. Evidence: assessment file 10. Memory: RESEARCH_NOTES (audit findings), project-context/06 (legacy risks) 11. Next: Discovery Prepared

## Output
Technical audit notes and evidence for the migration assessment pipeline.

## Evidence
Assessment file + source references.

## Memory
RESEARCH_NOTES (audit findings `[VERIFIED]`/`[ASSUMPTION]`/`[UNKNOWN]`); project-context/06 (identified risks).

## Failure handling
- No source access → skip audit; flag `[UNKNOWN]` for all areas; proceed to discovery.
- Partial access → audit what's available; mark rest `[UNKNOWN]`.
- For migration projects, do not present this function's output as the final assessment.
