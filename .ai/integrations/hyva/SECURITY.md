# Hyvä integration — security controls

Consolidates the execution/security controls for the imported Hyva skills (prompt §21). The authoritative guardrails live in [`SECOMM_OVERLAY.md`](SECOMM_OVERLAY.md); this file is the indexed summary for reviewers and the §I report.

## Security-sensitive skill

`hyva-exec-shell-cmd` (and every skill that depends on it: `hyva-child-theme`, `hyva-compile-tailwind-css`, `hyva-create-module`, `hyva-cms-*`, `hyva-ui-component`) executes shell commands inside the Magento dev environment.

Upstream audit (commit `5f094b6`):
- `scripts/detect_env.sh` — **read-only** environment detection (reads `.env` for `WARDEN_ENV_NAME`, checks `bin/clinotty`, `.ddev/config.yaml`). No writes, no network, no credential access.
- The skill uses a **stream-over-stdin** pattern to run bundled helper scripts without copying them into the project tree (container-mount-agnostic). No `curl | sh`, no credential printing in the upstream body.

## Secomm controls (overlay-enforced)

1. **No destructive command** without an explicit task need.
2. **No production mutation by default** — dev environment only.
3. **No printing secrets** from `auth.json`, env vars, Composer credentials, deployment config, or the private Hyvä Packagist repository URL.
4. **No unreviewed `curl | sh`** during normal task execution; prefer pinned, reviewed sources.
5. **Distinguish read-only inspection from mutation**; show the exact command before risky execution.
6. **Preserve the project's Warden / DDEV / docker / local wrapper** (do not assume local execution succeeds).
7. **Refuse unsafe path traversal**; validate meaningful command output / side-effects; do not assume a command succeeded.
8. **Capture concise evidence**, not full noisy logs.

## Copy-vs-symlink decision

**Deterministic generated copies** (not symlinks). Symlinks are unreliable across Git/Bitbucket, Windows, WSL, Docker bind mounts, and CI, and would couple generated projects to an external clone. The retrofit/update mechanism keeps copies verifiable (`upstream-manifest.yaml` sha256) and independently usable after the toolkit repo is removed from the workspace.

## Local-checkout note

slaunchpad-style projects use a **local checkout** (no Warden/docker/DDEV); `detect_env.sh` returns `local`. The overlay reminds that some bundled scripts (e.g. `dump_cms_components.php`) need a PHP interpreter — on a hardened host without one, the skill must report that a containerized dev environment (or local interpreter) is required rather than failing silently.
