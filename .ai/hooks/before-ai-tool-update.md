# before-ai-tool-update


- **Mục đích**: Guard trước khi update project AI tool — confirm reason recorded, memory không overwrite blind, security-audit nếu relevant.
- **Khi trigger**: Trước khi chạy `update-project-ai-tool` function (sửa agent/skill/rule/hook/prompt/memory/workflow).
- **Checklist**: [`../rules/ai-tool-self-update.md`](../rules/ai-tool-self-update.md)
- **Evidence yêu cầu**: Reason documented; change scope (project-specific vs reusable); nếu affect security → `audit-security` result TRƯỚC.
- **Xử lý khi fail**: Reason không recorded / memory overwrite blind / security-audit pending → block update.

