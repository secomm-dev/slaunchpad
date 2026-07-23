# before-deploy


- **Mục đích**: Release gate — checklist + rollback plan + QC signoff.
- **Khi trigger**: Trước production deployment.
- **Checklist**: [`../../checklists/release-checklist.md`](../../checklists/release-checklist.md) (Pre-Release section)
- **Engineering Standards**: validate DEPLOYMENT + critical SECURITY standards (release gate).
- **Evidence yêu cầu**: Deployment checklist completed; rollback plan; migration tested staging.
- **Xử lý khi fail**: Block deploy — resolve S0; SA/TL approval.

