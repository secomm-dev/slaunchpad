# validate-di-compile-impact

> Function (VI). Magento platform. Lifecycle: **platform**. DI compile (`setup:di:compile`) impact — proxy/interceptor/factory generation, compile error, production deploy risk.

## Purpose
Validate rằng một DI change (plugin/proxy/preference/factory/virtual type) không break `bin/magento setup:di:compile` — compile error block production deploy.

## When to use
- Sau khi modify `di.xml` hoặc thêm plugin/proxy/preference.
- Pre-deploy validation.
- CI/CD gate.

## Trigger
- Prompt snippet: "Validate DI compile impact: run setup:di:compile on staging/CI sau di.xml change. Check proxy/interceptor/factory generation, compile error."

## Required inputs
- DI change (di.xml diff or module change)

## Required project files to read
- `project-context/09_MAGENTO_MODULE_MAP.md`, `docs/deployment.md`

## Required agents / skills / rules / hooks
- Agents: devops, magento-reviewer
- Skills: `magento-module-analysis`
- Rules: `production-readiness.md`, `backward-compatibility.md`
- Hooks: `before-deploy`

## Required memory / evidence
- Memory: `CONTINUOUS_LEARNING.md` (DI compile gotcha), `project-context/06`
- Evidence: `.ai/evidence/{task}/di-compile-result.md` (compile output)

## Execution steps
1. Context (09/deployment.md) 2. Memory (CONTINUOUS_LEARNING) 3. Rules (production-readiness) 4. Skill 5. Agent (devops) 6. Research: check proxy/factory/interface declaration đúng 7. Run `bin/magento setup:di:compile` trên staging/CI 8. Validate: compile pass, no error, generated code correct 9. Evidence (compile output) 10. Update memory 11. Next: deploy-ready nếu pass

## Output format
DI compile result: pass/fail, error (nếu có), generated code check, deploy-ready verdict.

## Failure handling
- Compile error → block deploy; fix DI declaration (missing interface/proxy/factory) trước.
- Missing proxy cho heavy dependency → add proxy (lazy load).

## Related audits / standards
- Audits: Magento, Deployment Readiness
- Standards: MAGENTO_STANDARD (DI, Upgrade-safe), DEPLOYMENT, PRODUCTION (production-readiness rule)
