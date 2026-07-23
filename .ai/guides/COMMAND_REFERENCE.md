# Command Reference — Secomm Launchpad

<!-- Enablement artifact — output: .ai/guides/COMMAND_REFERENCE.md (VI). Only supported commands documented. -->

> **Purpose:** Tham chiếu tất cả command + câu nói được support cho project này.
> **Human Owner:** All roles · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "Commands cho project này" · **Expected Reading Time:** 4 min
> **Current Status:** Generated · **Next Step:** Thử 1 command, hoặc mở `../runtime/PROJECT_NAVIGATOR.md`

> Chỉ những command được support cho project này mới xuất hiện ở đây. Command không có = chưa được generate cho project/role này. **Natural language luôn work** ở mọi tool — command chỉ là alias ngắn.

## Tool support

| Tool | Cách dùng command |
|---|---|
| **Claude Code** (primary) | Gõ slash command trực tiếp (`/task`, `/spec`, ...) hoặc natural language. |
| **Codex / Copilot** | Natural language; hoặc paste nội dung command như một prompt snippet. |

## Commands — theo delivery phase

### Discovery & Requirement (BA)

| Command | Natural language | Bạn nhận được |
|---|---|---|
| `/spec` | "soạn spec cho feature X" | Feature spec / mini-spec draft (objective, AC, out-of-scope) |
| `/task` | "phân tích ticket ABC-123" | Risk, affected areas, effort ước lượng, bước tiếp theo |
| `/estimate` | "ước lượng ticket này" | Effort estimate + estimation drivers |
| `/status` | "tóm tắt status dự án" | Status summary shareable cho stakeholder |

### Build (Developer)

| Command | Natural language | Bạn nhận được |
|---|---|---|
| `/task` | "phân tích ticket ABC-123" | Plan gợi ý + affected areas + blockers |
| `/review-code` | "review code tôi vừa làm" | AI pre-review report (scope, security, business rule) **trước** TL review |
| `/magento-module-analysis` | "phân tích tác động module này" | Plugin/preference/observer/dependency impact của một module Magento |
| `/magento-checkout-impact` | "đổi X ảnh hưởng checkout không?" | Tác động checkout/payment/shipping/order (Tier 2) |
| `/security-review` | "review bảo mật change này" | Findings bảo mật (secret, permission, input) |
| `/update-memory` | "cập nhật context sau change" | Diff đề xuất cập nhật project-context sau thay đổi |

### Test (QC)

| Command | Natural language | Bạn nhận được |
|---|---|---|
| `/testcase` | "sinh test case cho spec này" | Test case list (happy/edge/negative) theo AC |
| `/review-code` | "review scope QC cho change này" | Phạm vi cần test + điểm rủi ro |

### Deploy (DevOps)

| Command | Natural language | Bạn nhận được |
|---|---|---|
| `/deploy` | "sinh deployment checklist" | Checklist pre-deploy + deploy + post-deploy + rollback (trigger cụ thể) |

### Session & memory (mọi role)

| Command | Natural language | Bạn nhận được |
|---|---|---|
| `/compact-context` | "lưu session lại" | Session được lưu gọn lại để resume |
| `/continue` | "phục hồi session trước" | Trạng thái làm việc được dựng lại từ memory |
| `/update-memory` | "refresh trạng thái hiện tại" | Trạng thái + context diff cập nhật |

### Điều hướng & quyết định (luôn dùng được, mọi role)

| Command | Natural language | Khi nào dùng |
|---|---|---|
| `/decisions` | "phải approve gì" / "xem các quyết định" | Xem decision đang chờ |
| `/approve` | "tôi đồng ý" / "approve DEC-001 opt-b" | Chốt 1 decision đang chờ |
| `/tbd` | "để hỏi client" / "chưa quyết" | Trì hoãn 1 decision |
| `/evidence` | "cho xem bằng chứng" | Muốn chứng cứ cho 1 decision |
| `/help-nav` | "tôi đang ở đâu" / "bị kẹt" | Lạc hướng — re-render vị trí hiện tại |
| `/explain` | "bước này để làm gì" | Giải thích bước hiện tại |
| `/next` | "tiếp tục" | Re-render vị trí (chỉ khi thực sự lạc hướng) |

> Bạn hiếm khi cần hỏi "tiếp theo làm gì" — AI tự tiếp tục qua các bước nội bộ và **chỉ dừng** khi cần bạn approve một decision.

## Natural language (luôn works)

Bạn luôn có thể nói tự nhiên thay vì gõ command:
- *"soạn spec cho feature giỏ hàng"* → spec draft.
- *"phân tích ticket SEC-42"* → risk + affected areas + effort.
- *"review code tôi vừa commit"* → pre-review report.
- *"tôi đang ở đâu"* → phase + file cần đọc + decision chờ.

## Dev skills (Magento + Hyvä)

Khi implement, AI dùng bộ dev skill HOW-TO sau (theo stack Magento + Hyvä của project): `create-module`, `create-plugin`, `create-observer`, `create-db-schema`, `create-cron-job`, `create-api-endpoint`, `create-admin-grid`, `create-graphql-resolver`, `hyva-alpine-component`, `hyva-tailwind-section`. Bạn không cần gọi trực tiếp — chỉ cần mô tả việc cần làm (ví dụ *"tạo module mới X"*).
