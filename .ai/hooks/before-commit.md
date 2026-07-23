# before-commit


- **Mục đích**: Local commit guard — không secret/scope-leak/debug code vào commit.
- **Khi trigger**: Trước mỗi `git commit`.
- **Checklist**: [`../../checklists/pre-commit-checklist.md`](../../checklists/pre-commit-checklist.md)
- **Engineering Standards**: validate DEVELOPMENT + CODING standards trên diff (rule `engineering-standards-enforcement.md`).
- **Evidence yêu cầu**: Diff scan sạch (no secret/PII/debug).
- **Xử lý khi fail**: Unstage/sửa trước khi commit.

