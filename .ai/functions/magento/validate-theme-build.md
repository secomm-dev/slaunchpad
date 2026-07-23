# validate-theme-build (Magento)

> Function (VI guidance). Magento project only.

## Mục đích
Validate theme build — Tailwind/Luma build, asset pipeline, static content deploy, không break production deploy.

## Trigger
- Prompt snippet: "Validate theme build: Tailwind compile (Hyva) / LESS compile (Luma), static content deploy, asset pipeline, production deploy risk."

## Required inputs
- Theme change / build config diff

## Required project files to read
- `project-context/09` (theme), `docs/deployment.md`

## Dependencies
- Agent: devops, magento-reviewer (hyva-migration nếu Hyva)
- Dev skill: `hyva-tailwind-section` (Hyva)
- Rule: `production-readiness.md`
- Hook: `before-deploy`

## Execution steps
1. Run theme build local (Tailwind compile Hyva / LESS compile Luma).
2. Check static content deploy (`bin/magento setup:static-content:deploy`).
3. Check asset pipeline (purge Tailwind không remove used class).
4. Check build không break production deploy (test staging).
5. Finding + verdict.

## Expected output
Theme build validation: build pass + asset/pipeline risk + deploy-ready.

## Evidence required
Build output + staging deploy test lưu `.ai/evidence/{task}/theme-build.md`.

## Memory files to update
- `CONTINUOUS_LEARNING.md` (build gotcha)

## Failure handling
- Build fail / Tailwind purge miss class → fix trước deploy.

## When to improve/update
- Khi build tool/version change → update + record.
