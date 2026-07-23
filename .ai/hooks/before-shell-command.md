# before-shell-command


- **Mục đích**: Shell safety — không interpolate untrusted data; destructive verb require approval.
- **Khi trigger**: Trước khi AI propose/chạy shell command (đặc biệt destructive hoặc trên production).
- **Checklist**: [`../../checklists/before-shell-command-checklist.md`](../../checklists/before-shell-command-checklist.md)
- **Evidence yêu cầu**: Command build từ fixed string + validated arg; destructive verb có human approval.
- **Xử lý khi fail**: Không chạy — rebuild command an toàn; escalate nếu production/destructive.

