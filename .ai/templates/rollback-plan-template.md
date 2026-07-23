# Rollback Plan: {Release / Version}

## Trigger Conditions

Rollback nếu bất kỳ điều nào sau xảy ra sau deployment:

- [ ] Critical error rate tăng đột biến (ví dụ 5x bình thường)
- [ ] Checkout / payment flow bị hỏng
- [ ] Major feature từ release này không hoạt động
- [ ] Database migration errors
- [ ] {custom trigger}

## Rollback Steps

1. {step — ví dụ "Revert deploy về commit trước"}
2. {step — ví dụ "Run rollback database migration"}
3. {step — ví dụ "Clear tất cả caches"}
4. {step — ví dụ "Verify deployment scripts hoàn thành"}

## Verification After Rollback

- [ ] Critical user flow hoạt động: {flow}
- [ ] Error rate trở lại bình thường
- [ ] Monitoring dashboard stable
- [ ] Tất cả feature của previous version hoạt động

## Communication Plan

| Audience | Message | Who Sends |
|----------|---------|-----------|
| Team | Rollback notification | TL |
| Client | Rollback notification + ETA cho deploy kế tiếp | PM |
| Stakeholders | Status update | PM |
