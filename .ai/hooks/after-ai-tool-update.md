# after-ai-tool-update


- **Mục đích**: Verify change được record đúng sau update — CHANGELOG + version + decision + lesson.
- **Khi trigger**: Sau khi `update-project-ai-tool` function apply change.
- **Checklist**: [`../rules/ai-tool-self-update.md`](../rules/ai-tool-self-update.md)
- **Evidence yêu cầu**: `CHANGELOG_AI_TOOL.md` entry added; artifact version/date marked; `DECISIONS.md` ADR (nếu significant); `CONTINUOUS_LEARNING.md` lesson (nếu pattern); (reusable) backport proposal note.
- **Xử lý khi fail**: CHANGELOG/version/decision miss → complète trước commit.

---

