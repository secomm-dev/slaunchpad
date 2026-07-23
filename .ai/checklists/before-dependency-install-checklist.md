# Before Dependency Install Checklist

Dùng trước khi install một dependency mới (composer package, npm package, Magento module, Shopify app, integration). Dependency = supply-chain risk. Authority: [`core/production-ai-security.md`](../core/production-ai-security.md) §4 + §7.

## Provenance

- [ ] Package source là official/marketplace (Packagist official, Shopify App Store, Magento Marketplace) — không unverified repo/raw tarball
- [ ] Maintainer reputation check (tên không typosquat)
- [ ] Last release gần đây (không abandonware)
- [ ] Open security advisories đã check (GitHub Advisories, security advisories DB)

## Scope & permissions

- [ ] Required scope/permission review (Shopify app scope, Magento module perm) — không over-privileged
- [ ] Package không require secrets trong config (env reference only)
- [ ] Check package dep tree — không kéo theo dep rủi ro

## Approval & version

- [ ] TL approval recorded (Hard Gate — new dependency)
- [ ] Version pinned cho production (không `dev-master` / `latest`)
- [ ] `composer.lock` / `package-lock.json` sẽ commit + review diff
- [ ] Post-install script review (`post-install-cmd`, npm lifecycle) — arbitrary code?

## Install environment

- [ ] Install chạy ở sanctioned environment (không production trực tiếp)
- [ ] Không bypass `.gitignore` / không commit `.env`/`auth.json`

## Verdict

- [ ] Không Critical (provenance/scope/advisory) — hoặc resolved
- [ ] Decision record vào `project-context/06` (và `DECISIONS.md` nếu significant)
- [ ] Sau install: smoke test chức năng liên quan
