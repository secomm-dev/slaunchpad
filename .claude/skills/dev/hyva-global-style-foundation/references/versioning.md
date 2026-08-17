# Versioning

Three independent versions describe this capability. The Project AI Tool
records what was applied in `.ai/toolkit/toolkit-version.md` under
`applied_capabilities:` (written/updated by `bin/project-ai-upgrade
--capability hyva`), and every generation run stamps the transformer +
contract versions into `design-tokens.manifest.json`.

| Version | Where declared | Meaning |
|---|---|---|
| `skill_version` | `dev-skill-manifest.yaml` entry (`hyva-global-style-foundation.skill_version`) | The skill package as a whole — SKILL.md workflow, references, templates. Semver. |
| `contract_schema_version` | Contract `schema_version:` field (currently `1`), validated + echoed by the transformer (`CONTRACT_SCHEMA_VERSION`) | The contract file format. Breaking changes to allowed keys/shapes bump this and require contract migration. |
| `transformer_version` | `TRANSFORMER_VERSION` constant in `scripts/token-transformer.mjs` | The generation engine. Any change that can alter generated output for identical inputs bumps this. |

```yaml
# .ai/toolkit/toolkit-version.md (project-side stamp, auto-managed)
applied_capabilities:
  hyva-global-style-foundation:
    skill_version: 1.0.0
    transformer_version: 2.1.0
    contract_schema_version: 1
```

## Update semantics — what a retrofit must do per change type

| Change type | Signal | Retrofit action |
|---|---|---|
| Documentation-only update | `skill_version` patch bump, `transformer_version` unchanged | Re-copy the skill folder; no project regeneration, no contract change |
| Skill behavior update (workflow/references/templates) | `skill_version` minor/major bump | Re-copy skill folder; re-read on next invocation; no forced regeneration |
| Transformer update | `transformer_version` bump | Re-copy the skill; projects must re-copy the theme-owned transformer and regenerate (deterministic — diff shows only real changes) |
| Contract migration | `contract_schema_version` bump | Skill re-copy + **project contract migration required**: update the theme-owned contract to the new schema, re-audit, re-approve modes; do not regenerate against an old-schema contract |
| Project-specific migration | contract content/values changed by the project | Re-run the audit + first-run decision points; regeneration alone is not sufficient |

Never blind-replace on every skill update: the upgrade path compares the
stamped `applied_capabilities` versions and performs only the actions the
table requires. Locally modified skills are preserved unless `--force`
(existing retrofit ownership rule).
