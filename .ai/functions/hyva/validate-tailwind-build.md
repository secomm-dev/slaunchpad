# validate-tailwind-build

> Function (VI). Hyvä stack. Lifecycle: **platform**. Validate Tailwind build — compile pass, purge config correct (no missing class), theme asset pipeline, production deploy-ready.

## Purpose
Validate Tailwind CSS build cho Hyva — compile không lỗi, purge config không remove class đang dùng, asset pipeline đúng, production build deploy-ready.

## When to use
- Sau khi modify Tailwind config / add new utility class / theme change.
- Pre-deploy validation.
- CI/CD gate.

## Trigger
- Prompt snippet: "Validate Tailwind build cho Hyva: compile, purge config (no missing class), asset pipeline, deploy-ready."

## Required inputs
- Tailwind config diff / theme change

## Required project files to read
- `project-context/09_MAGENTO_MODULE_MAP.md` (theme), `docs/deployment.md`

## Required agents / skills / rules / hooks
- Agents: devops, hyva-migration
- Dev skills: `hyva-tailwind-section`
- Rules: `production-readiness.md`
- Hooks: `before-deploy`

## Required memory / evidence
- Memory: `CONTINUOUS_LEARNING.md` (purge gotcha)
- Evidence: `.ai/evidence/{task}/tailwind-build.md` (compile output)

## Execution steps
1. Context (09/deployment.md) 2. Memory 3. Rules (production-readiness) 4. Dev skill 5. Agent (devops) 6. Research purge config + content path 7. Run Tailwind build 8. Validate: compile pass, purge không miss class (check dynamic class `class="{{ var }}"`), CSS size reasonable 9. Evidence (build output + CSS size) 10. Memory 11. Next: deploy-ready nếu pass

## Output format
Tailwind build result: compile pass/fail, purge check, CSS size, deploy-ready.

## Failure handling
- Purge miss class (style broken) → add safelist hoặc explicit class.
- Compile error → fix config.
- CSS quá lớn → audit unused class.

## Related audits / standards
- Audits: Hyva, Performance, Deployment Readiness
- Standards: HYVA_STANDARD (Tailwind, Performance), DEPLOYMENT, PERFORMANCE
