# Before Shell Command Checklist

Dùng trước khi AI đề xuất/chạy một shell command, đặc biệt khi destructive hoặc trên production. Authority: [`core/production-ai-security.md`](../core/production-ai-security.md) §3.

## Input safety

- [ ] Command build từ fixed string + validated argument (không string-concatenate untrusted data)
- [ ] Mọi argument được quote/bound
- [ ] Không `eval` / `sh -c` trên untrusted input
- [ ] Không shell metacharacter (`;`, `&&`, `` ` ``, `$()`) từ untrusted input

## Destructive verbs

- [ ] Destructive verb (`rm`, `drop`, `truncate`, `force`, `--no-data-check`, bulk `DELETE/UPDATE`) → require human approval
- [ ] Bulk operation trên live data → không (human + script + rollback)

## Environment

- [ ] Không chạy trên production by default (production DB/CLI = human-initiated)
- [ ] Nếu phải production: explicit human approval + logging

## Secret safety

- [ ] Không đọc secret (`SHOPIFY_ACCESS_TOKEN`, DB password, `.env`) vào command output/log
- [ ] Command output không expose secret/PII

## Verdict

- [ ] Command an toàn (fixed + validated + không destructive-unapproved) → proceed
- [ ] Nếu destructive/production → human approval recorded trước khi chạy
- [ ] Output (nếu lưu evidence) được mask secret/PII
