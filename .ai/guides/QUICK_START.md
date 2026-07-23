# Quick Start — Secomm Launchpad (15 phút)

<!-- Enablement artifact — output: .ai/guides/QUICK_START.md (VI). -->

> **Purpose:** Onboarding 15 phút — biết bạn là ai, thử action đầu tiên.
> **Human Owner:** All roles (self-onboarding)
> **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** All (short) · **Expected Reading Time:** 5 min
> **Current Status:** Generated at init · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## 1. Bạn là ai?

| Role | Guide | Việc chính trong dự án vừa khởi tạo |
|---|---|---|
| BA | `BA_GUIDE.md` | Soạn spec/requirement đầu tiên; chốt objective với stakeholder |
| SA | `SA_GUIDE.md` | Chốt multi-store intent + integration contract |
| TL | `TL_GUIDE.md` | Chốt blocking decisions; approve plan đầu tiên |
| Developer | `DEVELOPER_GUIDE.md` | Implement feature đầu tiên khi đã có spec |
| QC | `QC_GUIDE.md` | Chuẩn bị test approach cho checkout/payment/address |
| DevOps | `DEVOPS_GUIDE.md` | Chốt hạ tầng production (Redis, Varnish, OpenSearch, CI) |

## 2. Dự án đang ở đâu?

Vừa khởi tạo, **chưa có feature nào chạy**. Hai việc có thể làm ngay:
- **Giải quyết quyết định chặn** → mở `../runtime/DECISION_QUEUE.md`.
- **Bắt đầu feature đầu tiên** → làm bước 3 bên dưới.

## 3. Thử ngay (action đầu tiên)

Chọn theo role — nói tự nhiên hoặc gõ command:

| Bạn muốn | Bạn nói | Command |
|---|---|---|
| Biết làm gì tiếp | "tôi đang ở đâu" | (AI tự re-render vị trí) |
| Xem quyết định chờ | "phải approve gì" | `/decisions` |
| Soạn spec đầu tiên (BA) | "soạn spec cho feature X" | `/spec` |
| Phân tích ticket (Developer) | "phân tích ticket ABC-123" | `/task` |
| Sinh test case (QC) | "sinh test case cho spec này" | `/testcase` |
| Sinh deployment checklist (DevOps) | "sinh deployment checklist" | `/deploy` |

**Bạn nhận được** (ví dụ `/task`): danh sách risk, affected areas, effort ước lượng, và bước tiếp theo kèm owner.

## 4. Workflow của bạn (business language)

**Discovery → Spec → Build → Review → Deploy**

AI tự đi qua các bước nội bộ (research → plan → code → review) và **chỉ dừng** khi cần bạn approve một quyết định. Khi đó decision hiện lên — bạn approve bằng `/approve` (hoặc "tôi đồng ý").

## 5. Top 3 commands (dùng được mọi role)

| Command | Natural language | Bạn nhận được |
|---|---|---|
| `/decisions` | "phải approve gì" | Danh sách quyết định đang chờ + owner |
| `/task` | "phân tích ticket X" | Risk + affected areas + effort + bước tiếp theo |
| `/review-code` | "review code này" | Pre-review report (scope, security, business rule) trước khi TL review |

## 6. Đọc tiếp

- `{YOUR_ROLE}_GUIDE.md` — role guide đầy đủ (ví dụ `DEVELOPER_GUIDE.md`).
- `COMMAND_REFERENCE.md` — tất cả command + câu nói.
- `CHEATSHEET.md` — cheat sheet 1 trang.
- `../learning/FIRST_DAY.md` — checklist ngày đầu.
