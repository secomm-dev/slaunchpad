# Learning Path — Secomm Launchpad

<!-- Enablement artifact — output: .ai/learning/LEARNING_PATH.md (VI). Onboarding path for new team members. -->

> **Purpose:** Lộ trình onboarding — từ ngày đầu đến tự làm việc được.
> **Human Owner:** New team members · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "Lộ trình 5 ngày" + "Per-role" · **Expected Reading Time:** 6 min
> **Current Status:** Generated · **Next Step:** Làm `FIRST_DAY.md` → mở `../runtime/PROJECT_NAVIGATOR.md`

Lộ trình này giúp thành viên mới trở nên productive nhanh. Dự án **vừa khởi tạo** nên phần lớn thời gian đầu là hiểu bối cảnh + chốt blocking decisions.

## Lộ trình tổng thể (đọc theo thứ tự)

1. `../WELCOME.md` — bối cảnh + 4 entry point + supported tools.
2. `../guides/QUICK_START.md` — bạn là ai, thử action đầu tiên.
3. `../guides/PROJECT_OVERVIEW.md` — objective, stack, business rules, integrations, high-risk.
4. `../guides/{YOUR_ROLE}_GUIDE.md` — role của bạn (BA/SA/TL/Developer/QC/DevOps).
5. `../guides/COMMAND_REFERENCE.md` — tất cả command + câu nói.
6. `../guides/CHEATSHEET.md` — 1 trang để tra nhanh.
7. `FIRST_DAY.md` → `ROLE_CHECKLIST.md` → `COMMON_MISTAKES.md` → `BEST_PRACTICES.md`.

## Lộ trình 5 ngày

| Ngày | Mục tiêu | Hoạt động |
|---|---|---|
| **Ngày 1** | Hiểu bối cảnh + chạy thử | Đọc WELCOME → QUICK_START → PROJECT_OVERVIEW. Mở Navigator. Nói *"tôi đang ở đâu"*. |
| **Ngày 2** | Hiểu role + stack | Đọc role guide của bạn + FAQ. Hiểu Tailwind v4 CSS-first, Hyvä, Mageplaza OSC, VNPAY (inactive), address dropdown VN. |
| **Ngày 3** | Hiểu business rules + high-risk | Đọc `PROJECT_OVERVIEW.md` "Business rules" + "High-risk areas". Hiểu BR-001..006 + 5 khu vực rủi ro. |
| **Ngày 4** | Thử workflow | Theo role: BA → soạn 1 spec nhỏ (`/spec`); Developer → phân tích 1 ticket (`/task`); QC → sinh test case (`/testcase`); DevOps → xem blocking decision hạ tầng. |
| **Ngày 5** | Tự làm việc | Hoàn thành `ROLE_CHECKLIST.md`. Đọc `COMMON_MISTAKES.md` + `BEST_PRACTICES.md`. Bắt đầu task thật dưới hướng dẫn TL. |

## Per-role learning path

| Role | Đọc thêm | Action thực hành |
|---|---|---|
| **BA** | `BA_GUIDE.md` + open questions (multi-store, objective) | Soạn 1 mini-spec cho feature đơn giản (`/spec`) |
| **SA** | `SA_GUIDE.md` + high-risk areas | Xem xét DEC multi-store + VNPAY integration contract |
| **TL** | `TL_GUIDE.md` + gate checklist | Xem `../runtime/DECISION_QUEUE.md`; hiểu 2 blocking decisions |
| **Developer** | `DEVELOPER_GUIDE.md` + coding conventions | Phân tích 1 ticket (`/task`); hiểu Tailwind v4 + Hyvä patterns |
| **QC** | `QC_GUIDE.md` + test focus | Sinh test case cho 1 AC (`/testcase`); hiểu checkout/address VN |
| **DevOps** | `DEVOPS_GUIDE.md` + production readiness | Xem blocking decision hạ tầng; hiểu Redis/Varnish/OpenSearch gap |

## Khi nào bạn "sẵn sàng"?
- [ ] Biết 4 entry point + supported tools.
- [ ] Hiểu workflow Mode A + khi nào AI dừng để hỏi.
- [ ] Hiểu 6 business rules + 5 high-risk areas.
- [ ] Thử được ít nhất 1 command theo role.
- [ ] Hoàn thành `ROLE_CHECKLIST.md`.

> Đừng cố nhớ tên bước nội bộ — chỉ cần biết intents + commands + workflow. Khi lạc, nói *"tôi đang ở đâu"*.
