# Deployment Checklist: {Tên release / Version}

## Pre-Deployment

- [ ] Tất cả PRs đã merge vào release branch
- [ ] QC sign-off đã obtain
- [ ] TL đã approve deployment checklist
- [ ] Database migration script đã test trên staging
- [ ] Rollback plan đã ready và confirm
- [ ] Third-party API changes đã coordinate (nếu applicable)
- [ ] Stakeholders đã notify về release window

## Deployment Steps

1. {deploy step — ví dụ "Run deploy pipeline trong GitHub Actions"}
2. {deploy step — ví dụ "Run bin/magento setup:upgrade"}
3. {deploy step — ví dụ "Clear cache"}
4. {deploy step}

## Post-Deployment Verification

- [ ] Smoke test — critical user flow: {flow}
- [ ] Smoke test — new features: {danh sách feature}
- [ ] Database migrations hoàn thành thành công
- [ ] Error logs không có unexpected errors
- [ ] Monitoring dashboard stable

## Rollback

- **Trigger conditions**: {các điều kiện sẽ trigger rollback}
- **Rollback steps**: 1. {step} 2. {step}
- **Rollback tested**: Yes / No

## Signoff

| Role | Name | Date |
|------|------|------|
| TL/SA | | |
| Deploy by | | |
