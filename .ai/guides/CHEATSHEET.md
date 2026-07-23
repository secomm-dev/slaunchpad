# Cheat Sheet — Secomm Launchpad (mọi role)

<!-- Enablement artifact — output: .ai/guides/CHEATSHEET.md (VI). One page — top intents + navigation + scenarios. -->

> **Purpose:** 1 trang — top intents + điều hướng + tình huống thực tế.
> **Human Owner:** All roles · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "🧭 Điều hướng & quyết định" + "Tình huống thực tế" · **Expected Reading Time:** 3 min
> **Current Status:** Generated · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## Top 5 intents (dùng nhiều nhất)

| Bạn nói | Command | Bạn nhận được |
|---|---|---|
| "phân tích ticket X" | `/task` | Risk + affected areas + effort + bước tiếp theo |
| "soạn spec cho feature Y" | `/spec` | Feature spec draft (objective, AC, out-of-scope) |
| "review code tôi vừa làm" | `/review-code` | Pre-review report trước TL review |
| "sinh test case cho spec" | `/testcase` | Test case list (happy/edge/negative) |
| "tóm tắt status dự án" | `/status` | Status summary shareable |

## 🧭 Điều hướng & quyết định (luôn dùng được, mọi role)

| Bạn nói | Command | Khi nào dùng |
|---|---|---|
| "tôi đang ở đâu" / "bị kẹt" | `/help-nav` | Lạc hướng — orientation + 3 action gợi ý |
| "tiếp theo làm gì" / "tiếp tục" | `/next` | Xem 1 action kế tiếp + file cần đọc (chỉ re-render, **không** chạy thêm bước) |
| "phải approve gì" / "xem các quyết định" | `/decisions` | Xem decision đang chờ (read-only) |
| "cho xem bằng chứng" | `/evidence [DEC-ID]` | Muốn chứng cứ cho 1 decision |
| "bước này để làm gì" | `/explain` | Giải thích bước hiện tại + kết quả mong đợi |
| "tôi đồng ý" / "approve DEC-001 opt-b" | `/approve` ⚠️ | Chốt 1 decision — **cần DEC-ID, AI sẽ confirm trước khi ghi** |
| "để hỏi client" / "chưa quyết" | `/tbd DEC-002` ⚠️ | Trì hoãn 1 decision — **cần DEC-ID** |

> ⚠️ = **mutating** (`/approve`, `/tbd`) — AI luôn xin xác nhận + yêu cầu DEC-ID trước khi thay đổi state. Không bao giờ "approve cả document" — chỉ approve từng decision (DEC-ID).
> **Tool support:** Claude Code = slash command thật (skill `.claude/skills/` — nav: `next`, `help-nav`, `decisions`, `evidence`, `explain`, `approve`, `tbd`; workflow: `task`, `spec`, `review-code`, `testcase`, `deploy`, `status`, `continue`, `update-memory`, `compact-context`). Codex / GitHub Copilot = gõ natural language (không support slash command) — cùng kết quả.
> Bạn **hiếm khi cần** hỏi "tiếp theo làm gì". AI tự đi qua các bước nội bộ (research → plan → code → review) và **chỉ dừng** khi cần bạn approve một decision. Natural language là first-class — không cần gõ command chính xác.

## Workflow (business language)
**Discovery → Spec → Build → Review → Deploy**

## Tình huống thực tế (scenarios)

| Tình huống | Bạn nói | AI làm gì |
|---|---|---|
| Chưa biết làm gì tiếp | "tôi đang ở đâu" | Phase + file cần đọc + decision chờ + 3 action gợi ý |
| Có quyết định chặn dự án | "phải approve gì" | Liệt kê decision đang chờ + owner + impact |
| Nhận requirement mới (BA) | "client vừa gửi requirement" | So sánh vs blueprint → hỏi chỉ phần thiếu → update Navigator |
| Được assign ticket (Dev) | "implement ticket SEC-42" | Đọc objective + AC → research vừa đủ → plan → blockers |
| Gửi task chờ review (Dev) | "review code này" | So sánh vs spec → risk → pre-review report cho TL |
| Chuẩn bị test (QC) | "sinh test case cho spec" | Test case happy/edge/negative theo AC + business rule |
| Chuẩn bị deploy (DevOps) | "sinh deployment checklist" | Pre-deploy + rollback (trigger cụ thể) + post-deploy verify |

## Magento/Hyvä — Impact check (Tier 2)

| Tình huống | Bạn nói | AI làm gì |
|---|---|---|
| Đổi module Magento | "phân tích tác động module X" | Plugin/preference/observer/dependency impact |
| Đổi checkout/payment/shipping | "đổi X ảnh hưởng checkout không?" | Tác động end-to-end checkout (Tier 2) |
| Change nhạy cảm bảo mật | "review bảo mật change này" | Secret/permission/input findings |

> **Quy tắc project:** bất kỳ change nào tới VNPAY (payment/IPN/chữ ký) → cần SA review. Bất kỳ change nào tới Mageplaza OSC checkout → end-to-end checkout QC + test payment.

## Common mistakes (top 3)

| ❌ Don't | ✅ Do |
|---|---|
| Hỏi "tiếp theo làm gì" liên tục | Mở `../runtime/PROJECT_NAVIGATOR.md`; AI chỉ dừng khi cần approve |
| Tự sửa code Mageplaza in-place | Extend bằng plugin/preference — không modify third-party |
| Tạo `tailwind.config.js` | Tailwind v4 CSS-first: dùng `@theme`/`@source` trong `tailwind-source.css` |

## Tips

- Mở `../runtime/PROJECT_NAVIGATOR.md` mỗi ngày — không cần tự hỏi "tiếp theo làm gì".
- Approve **decision** (DEC-ID), không approve cả document.
- Storefront string mới → thêm vào **cả** `vi_VN.csv` và `en_US.csv`.
- Đừng commit production `env.php` / Redis / OpenSearch credentials — repo chỉ giữ local-dev config.

## First day
Đọc `../learning/FIRST_DAY.md` → mở `../runtime/PROJECT_NAVIGATOR.md` → thử *"tôi đang ở đâu"* → đọc output.
