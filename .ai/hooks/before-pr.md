# before-pr


- **Mục đích**: AI pre-review pass trước khi TL review.
- **Khi trigger**: Trước khi tạo/yêu cầu PR review.
- **Checklist**: [`../../checklists/ai-pre-review-checklist.md`](../../checklists/ai-pre-review-checklist.md) (dev side) + [`../../checklists/code-review-checklist.md`](../../checklists/code-review-checklist.md) (TL side)
- **Engineering Standards**: validate REVIEW + CODING + ARCHITECTURE standards **trên diff** (không scan full codebase) — so sánh changed code vs standards → violations.
- **Evidence yêu cầu**: AI pre-review result (PASS / PASS WITH WARNINGS).
- **Xử lý khi fail**: Critical finding → fix trước; không tạo PR với NEEDS FIX.

