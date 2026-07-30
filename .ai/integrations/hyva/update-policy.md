# Hyvä integration — upstream update workflow

Upstream `main` is **never** auto-followed during project task execution. The
integration is pinned (`version-lock.yaml`). Updating is an explicit, reviewed,
audited action performed in the Production Toolkit, then offered to projects via
retrofit.

## Lifecycle

```text
1. discover upstream update   (check current main vs pinned commit)
2. inspect diff               (added / removed / renamed / changed skills)
3. review license/provenance  (OSL-3.0 obligations; record any uncertainty)
4. update canonical sources    (skills-source/dev-skills/magento/hyva-*/)
5. regenerate metadata         (upstream-manifest sha256, dependency-map, version-lock)
6. test in Production Toolkit  (validators + fixtures)
7. publish toolkit version     (CHANGELOG + bump hyva_integration_version)
8. offer project retrofit      (bin/project-ai-upgrade --capability hyva)
```

## Check command (read-only)

Compare the pinned commit against upstream main:

```bash
git ls-remote https://github.com/hyva-themes/hyva-ai-tools.git refs/heads/main
# compare against version-lock.yaml :: locked_commit
```

Or against a local checkout:

```bash
git -C /path/to/hyva-ai-tools rev-parse HEAD
```

If the values differ, an update is **available**, not applied. Perform the
lifecycle above before changing the pinned commit.

## Rules

- Existing project toolkits must **not** silently change when upstream changes.
- A bump updates `version-lock.yaml`, `upstream-manifest.yaml` (sha256),
  `provenance.yaml` (commit + retrieval_date), and the toolkit CHANGELOG.
- Renamed/removed upstream skills must be reflected in `dev-skill-manifest.yaml`
  and surfaced as a retrofit conflict for projects that had the old skill.
