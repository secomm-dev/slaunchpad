# Client Handoff Checklist

Dùng trước khi hand một feature/release cho client (UAT, go-live, hoặc project closeout). Đây là milestone outward-facing — [no-ai-blind-trust.md](../core/no-ai-blind-trust.md) và [production-ai-security.md](../core/production-ai-security.md) §5 (client confidentiality) apply đầy đủ. AI có thể draft artifacts; human review mọi client-facing item trước khi gửi.

## Functionality Verified

- [ ] Acceptance criteria đã meet và demonstrate
- [ ] UAT guide đã chuẩn bị (xem `uat-guide-template.md`); UAT signoff đã obtain hoặc pending-tracked
- [ ] Smoke test của critical flows pass (catalog, search, cart, checkout, payment, order email)
- [ ] Known issues đã document (với workaround hoặc timeline) — không giấu

## Documentation

- [ ] Release notes đã chuẩn bị (xem `release-note-template.md`) — human-reviewed
- [ ] User-facing docs đã update nơi behavior thay đổi
- [ ] project-context/ reflect delivered state
- [ ] Rollback/rollback-owner đã document cho mọi thứ đã live

## Confidentiality & Security

- [ ] Không có client secrets/PII/production data trong bất kỳ handoff artifact
- [ ] Không có thông tin của client khác trong deliverables của project này
- [ ] Security review đã pass cho mọi thứ touch auth/PII/payment
- [ ] Credentials/access transfer qua secure channel (không qua email/chat)

## Release Readiness

- [ ] Deployment plan + rollback plan đã confirm (DevOps)
- [ ] Stakeholders đã notify về go-live window
- [ ] Support/handover owner đã named + reachable

## Signoff

- [ ] TL signoff (technical readiness)
- [ ] PM signoff (scope + client communication)
- [ ] Tất cả client-facing text đã review bởi PM/TL trước khi gửi (Hard Gate)
