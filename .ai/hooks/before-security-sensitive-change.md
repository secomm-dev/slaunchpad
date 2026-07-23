# before-security-sensitive-change


- **Mục đích**: Change touch auth/PII/payment/checkout/order/secret → security review + escalation.
- **Khi trigger**: Trước khi modify code vùng security-sensitive.
- **Checklist**: [`../../checklists/security-review-checklist.md`](../../checklists/security-review-checklist.md)
- **Evidence yêu cầu**: security-review result; Tier 2 escalation confirm (nếu high-risk).
- **Xử lý khi fail**: Critical security finding → STOP, không merge, escalate Tier 2.

