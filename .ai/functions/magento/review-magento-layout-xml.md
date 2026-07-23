# review-magento-layout-xml (Magento)

> Function (VI guidance). Magento project only.

## Mục đích
Review Magento layout XML change — override correctness, handle name, container/block order, Hyva vs Luma format, không break existing handle.

## Trigger
- Prompt snippet: "Review layout XML change {diff}: handle/container/block, override correctness, Hyva vs Luma format, break existing handle?"

## Required inputs
- Layout XML diff

## Required project files to read
- `project-context/09_MAGENTO_MODULE_MAP.md` (layout override hiện có)

## Dependencies
- Agent: magento-reviewer (hyva-migration nếu Hyva)
- Skill: `magento-module-analysis`
- Rule: `backward-compatibility.md`, `project-conventions-first.md`

## Execution steps
1. Check handle name + container/block reference đúng.
2. Check override (không duplicate existing handle; `<referenceBlock>` vs override).
3. Hyva vs Luma format (Hyva dùng Tailwind/Alpine; layout khác).
4. Check không break existing handle/page.
5. Finding + verdict.

## Expected output
Layout XML review: finding (override/break/convention) + verdict.

## Evidence required
Review output lưu `.ai/evidence/{task}/layout-xml-review.md`.

## Memory files to update
- `CONTINUOUS_LEARNING.md` (layout gotcha)

## Failure handling
- Break existing handle → fix trước merge.

## When to improve/update
- Khi layout pattern Hyva-specific mới → record.
