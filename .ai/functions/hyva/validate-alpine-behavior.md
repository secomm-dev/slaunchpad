# validate-alpine-behavior

> Function (VI). Hyvä stack. Lifecycle: **platform**. Validate Alpine.js behavior — component state, x-data/x-init, event handler, reactivity, no jQuery, no heavy JS.

## Purpose
Validate Alpine.js behavior trong Hyva component — state management đúng, x-data/x-init structure, event handler ($dispatch/on), reactivity, accessibility, no jQuery, minimal JS bundle.

## When to use
- Khi create/modify Hyva Alpine component.
- Code review Hyva frontend behavior.

## Trigger
- Prompt snippet: "Validate Alpine behavior {component}: x-data/x-init, event handler, reactivity, no jQuery, minimal JS. Read 09."

## Required inputs
- Component (template with Alpine x-data)

## Required project files to read
- `project-context/09_MAGENTO_MODULE_MAP.md`

## Required agents / skills / rules / hooks
- Agents: hyva-migration, magento-reviewer
- Dev skills: `hyva-alpine-component`
- Rules: `project-conventions-first.md`, `backward-compatibility.md`
- Hooks: `before-pr`

## Required memory / evidence
- Memory: `CONTINUOUS_LEARNING.md`
- Evidence: `.ai/evidence/{task}/alpine-validate.md` (+ screenshot/behavior test)

## Execution steps
1. Context (09) 2. Memory 3. Rules 4. Dev skill (hyva-alpine-component) 5. Agent 6. Research existing Alpine convention 7. Validate: x-data structure (init state đúng), x-init (side effect đúng), event handler ($dispatch/on đúng), reactivity (x-model/x-show/x-if), no jQuery, no heavy JS (Hyva = minimal JS) 8. Validate: behavior test (mobile/desktop interaction), accessibility (keyboard/aria) 9. Evidence 10. Memory 11. Next

## Output format
Alpine validation: x-data/x-init + event handler + reactivity + JS size + accessibility + verdict.

## Failure handling
- jQuery dependency → remove; convert to Alpine.
- Heavy JS bundle → audit; Hyva premise = minimal JS.
- State mutation ngoài x-data → move vào x-data (reactivity).

## Related audits / standards
- Audits: Hyva, Performance, Code Quality
- Standards: HYVA_STANDARD (Alpine.js, Performance), PERFORMANCE, REVIEW
