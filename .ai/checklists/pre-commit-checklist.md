# Pre-Commit Checklist

Local commit guard — check cuối cùng trước `git commit`. Catch những mistakes không bao giờ nên đến PR: scope leaks, secrets, debug code, unintended dependencies. Nhanh; chạy mỗi commit.

## Scope

- [ ] Chỉ files liên quan đến task này được staged (không có unrelated changes lẫn vào)
- [ ] Không có out-of-scope refactoring / cleanup lẫn vào
- [ ] Commit message: `type(scope): description` — một commit = một concern

## Secrets & Sensitive Files

- [ ] Không có API keys, tokens, passwords, `.env` values, `auth.json`, `*.pem` staged
- [ ] Không có customer PII / production data trong diff
- [ ] `.gitignore` cover `.env`, `auth.json`, `*.pem`, `config/local/`
- [ ] Diff đã scan cho `password`, `secret`, `token`, `key`, `BEGIN PRIVATE KEY` (không có real)

## Code Hygiene

- [ ] Không có debug code (`console.log`, `var_dump`, `dd()`, `print_r`, breakpoints)
- [ ] Không có commented-out code; mọi `TODO`/`FIXME` có ticket id + owner
- [ ] Không có hardcoded values (credentials, URLs, environment-specific config)

## Dependencies

- [ ] Không có new dependency added mà không có TL approval (Hard Gate)
- [ ] Nếu `composer.json` / `package.json` thay đổi: lockfile đã update và review

## Branch & Ticket

- [ ] Commit reference ticket/spec (hoặc PR sẽ làm)
- [ ] Không commit trực tiếp vào protected/main branch
