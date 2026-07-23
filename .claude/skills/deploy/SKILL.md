# Deployment Checklist

## Purpose

Generate a deployment checklist for a release. Covers pre-deployment, deployment steps, post-deployment verification, and rollback triggers.

## When to Use

- Before every production deployment (all modes)
- Before staging deployment for QC testing (Mode A/B)
- When the deployment involves database migrations or third-party API changes
- After hotfix resolution to verify deployment process

## Prerequisites

- `project-context/` — deployment process, environments, integrations
- `docs/deployment.md` — detailed deployment procedures
- Release notes or list of changes being deployed
- PR list or commit log for the release

## Input

Description of the changes being deployed (ticket list, PR list, or release notes) and the target environment.

## Steps

1. Read `project-context/01_PROJECT_OVERVIEW.md` — understand project environment structure
2. Read `project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md` — understand integration impact
3. Read `docs/deployment.md` — understand the deployment process, commands, and environments
4. Analyze changes being deployed:
   - Are there database migrations? If yes → add DB migration steps to checklist
   - Are there third-party API contract changes? If yes → add coordination steps
   - Are there payment/checkout changes? If yes → add specific verification steps
   - Are there configuration changes? If yes → add config verification steps
5. Generate checklist covering:
   - **Pre-deployment**: tests passed, QC signoff, approvals obtained, rollback plan confirmed
   - **Deployment steps**: deploy commands, migration commands, cache clearing
   - **Post-deployment**: smoke tests, monitoring check, error log review
   - **Rollback triggers**: conditions that trigger rollback, rollback steps

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## Deployment Checklist: {release_name / date}

### Pre-Deployment
- [ ] {item}

### Deployment Steps
1. {step}

### Post-Deployment Verification
- [ ] {item}

### Rollback
- **Trigger conditions**: {conditions}
- **Rollback steps**: 1. {step}

### Signoff
- **Deploy approved by**: {name}
- **Rollback confirmed by**: {name}
```

## Quality Checklist

- [ ] Database migration steps included if schema changes exist
- [ ] Third-party API coordination included if integration contracts changed
- [ ] Smoke test URLs specific to the changes (not just homepage)
- [ ] Rollback triggers are explicit (not "if something breaks")
- [ ] Rollback steps include data reversion if applicable

## Escalation Rules

- Database migration in the release → requires SA/TL signoff on checklist
- Third-party API changes → requires SA/TL signoff
- Payment/checkout changes → requires SA/TL signoff
- If rollback test is not possible before deploy → flag as high-risk

## Example

**Input**: Release containing: B2B tiered pricing feature, database migration adding `customer_tier` column, Stripe webhook update.

**Output**:
```markdown
## Deployment Checklist: v2.1.0 — B2B Pricing

### Pre-Deployment
- [ ] All QC test cases passed
- [ ] TL code review approved for all PRs
- [ ] DB migration script tested on staging
- [ ] Rollback plan confirmed: revert deploy + run DOWN migration
- [ ] Stripe webhook endpoint updated in Stripe dashboard

### Deployment Steps
1. Run `bin/magento maintenance:enable`
2. Deploy code via GitHub Actions
3. Run `bin/magento setup:upgrade` (applies DB migration)
4. Run `bin/magento setup:di:compile`
5. Run `bin/magento indexer:reindex`
6. Run `bin/magento cache:flush`

### Post-Deployment Verification
- [ ] Smoke test: checkout flow with B2B account
- [ ] Verify `customer_tier` column populated correctly
- [ ] Stripe webhook test: place order, confirm webhook received

### Rollback
- **Trigger conditions**: Checkout broken, B2B prices incorrect, DB migration errors
- **Rollback steps**: 1. Revert deploy 2. Run DOWN migration 3. Redeploy previous version
```