# after-deploy


- **Mục đích**: Verify post-deploy + trigger context update.
- **Khi trigger**: Sau production deployment.
- **Checklist**: [`../../checklists/release-checklist.md`](../../checklists/release-checklist.md) (Post-Deployment section) + [`../../checklists/post-release-context-update-checklist.md`](../../checklists/post-release-context-update-checklist.md)
- **Evidence yêu cầu**: Smoke test pass; monitoring stable; (rollback nếu fail).
- **Xử lý khi fail**: Smoke test fail / error spike → rollback trigger → incident workflow.

