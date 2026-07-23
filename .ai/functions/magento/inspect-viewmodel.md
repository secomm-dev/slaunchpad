# inspect-viewmodel (Magento)

> Function (VI guidance). Magento project only.

## Mục đích
Inspect Magento view-model — data contract với template, Hyva compat, dependency, không leak internal state.

## Trigger
- Prompt snippet: "Inspect view-model {class}: data contract với template, Hyva compat, dependency, leak internal state?"

## Required inputs
- View-model class path

## Required project files to read
- `project-context/09_MAGENTO_MODULE_MAP.md`

## Dependencies
- Agent: magento-reviewer (hyva-migration nếu Hyva)
- Skill: `magento-module-analysis`
- Rule: `backward-compatibility.md`, `security-first.md`

## Execution steps
1. Check view-model interface (`ArgumentInterface`) implement đúng.
2. Check data contract (data trả cho template — không leak internal/repository).
3. Hyva compat (view-model dùng được trong Hyva component).
4. Check dependency injection (không circular, không heavy trong view-model).
5. Finding + verdict.

## Expected output
View-model inspect: finding (contract/compat/dependency/leak) + verdict.

## Evidence required
Inspect output lưu `.ai/evidence/{task}/viewmodel-inspect.md`.

## Memory files to update
- `CONTINUOUS_LEARNING.md`

## Failure handling
- View-model leak internal state / heavy dependency → refactor.

## When to improve/update
- Khi Hyva view-model pattern mới → record.
