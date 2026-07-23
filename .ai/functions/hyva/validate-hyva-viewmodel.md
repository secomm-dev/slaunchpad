# validate-hyva-viewmodel

> Function (VI). Hyvä stack. Lifecycle: **platform**. Validate Hyvä-compatible view-model — `ArgumentInterface`, JSON-serializable data, Hyva compat, no heavy dependency.

## Purpose
Validate rằng một view-model hoạt động đúng trong Hyva context — implement `ArgumentInterface`, data JSON-serializable (cho Alpine), không heavy dependency trong constructor (dùng proxy nếu cần), Hyva child-theme compat.

## When to use
- Khi create/modify view-model cho Hyva template.
- Migration Luma→Hyva (view-model adaptation).

## Trigger
- Prompt snippet: "Validate Hyva view-model {class}: ArgumentInterface, JSON-serializable, Hyva compat, no heavy dep. Read 09."

## Required inputs
- View-model class path

## Required project files to read
- `project-context/09_MAGENTO_MODULE_MAP.md`

## Required agents / skills / rules / hooks
- Agents: hyva-migration, magento-reviewer
- Skills: `magento-module-analysis` + dev skill `hyva-alpine-component`
- Rules: `backward-compatibility.md`, `project-conventions-first.md`
- Hooks: `before-pr`

## Required memory / evidence
- Memory: `CONTINUOUS_LEARNING.md`
- Evidence: `.ai/evidence/{task}/viewmodel-validate.md`

## Execution steps
1. Context (09) 2. Memory 3. Rules 4. Dev skill 5. Agent 6. Research existing VM pattern 7. Validate: `ArgumentInterface` implement, `jsonSerialize()` trả data an toàn (no internal/repository object), Hyva compat (dùng được trong Hyva component), no heavy dep (proxy nếu cần), no business logic leak 8. Validate: JSON output đúng cho Alpine 9. Evidence 10. Memory 11. Next

## Output format
View-model validation: interface + JSON-serializable + Hyva compat + dependency + leak + verdict.

## Failure handling
- Heavy constructor dep → add proxy (lazy).
- Internal object leaked via JSON → serialize only safe data.
- Not `ArgumentInterface` → implement.

## Related audits / standards
- Audits: Hyva, Magento
- Standards: HYVA_STANDARD (ViewModel), MAGENTO_STANDARD (ViewModel, DI), DEVELOPMENT
