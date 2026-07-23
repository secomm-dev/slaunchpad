# DOCKER_STANDARD

> Technology standard — Docker / containers. English. Applies to containerized builds/deploy.

## Mandatory Rules
- Pin base images (digest or version); minimal images; non-root user.
- No secrets baked into images (build args/env at runtime via secret manager); `.dockerignore` covers `.git`, `.env`, `vendor`, `node_modules`.
- Multi-stage builds; layer cache friendly; small final image.
- Healthchecks defined; resource limits set.

## Recommended Practices
- Scan images for CVEs; keep base images updated.

## Anti-patterns
`:latest` base; root container; secrets in image; huge images; missing healthcheck.

## Validation Checklist
- [ ] Pinned + minimal + non-root image; no secrets baked
- [ ] `.dockerignore` correct; healthcheck + limits present

## Related
**Agents**: devops · **Skills**: deploy · **Functions**: prepare-deployment-checklist · **Rules**: security-first, production-readiness · **Audits**: Deployment Readiness, Security
