# Release Checklist

## Pre-Release

- [ ] Tất cả PRs đã merge vào release branch
- [ ] QC sign-off đã obtain
- [ ] Deployment checklist đã generate và review
- [ ] Rollback plan đã confirm
- [ ] Database migration script đã test trên staging
- [ ] Third-party API changes đã coordinate
- [ ] Release notes đã chuẩn bị
- [ ] Stakeholders đã notify về release window

## Deployment

- [ ] Deployment steps thực hiện đúng thứ tự
- [ ] Database migrations đã execute (nếu applicable)
- [ ] Cache đã clear (nếu applicable)
- [ ] Static assets đã deploy (nếu applicable)
- [ ] Deployment scripts hoàn thành không có errors

## Post-Deployment

- [ ] Smoke test: critical user flow hoạt động
- [ ] Smoke test: new features hoạt động
- [ ] Error logs không có unexpected errors
- [ ] Monitoring dashboard stable
- [ ] Integration syncs đang chạy (ERP, webhooks, v.v.)

## Rollback

- [ ] Rollback triggers đã define
- [ ] Rollback steps đã document
- [ ] Rollback đã test (nếu possible)
