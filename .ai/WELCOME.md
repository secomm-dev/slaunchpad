# Welcome to Secomm Launchpad

<!-- Enablement artifact — output: .ai/WELCOME.md (VI). First file users read. -->

> **Purpose:** File đầu tiên bạn đọc — onboarding + chỉ điểm đến hàng ngày.
> **Human Owner:** All roles · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "Bắt đầu như thế nào" + "📍 Mỗi ngày mở file này" · **Expected Reading Time:** 3 min
> **Current Status:** Generated · **Next Step:** Mở `.ai/runtime/PROJECT_NAVIGATOR.md`

Bạn đang làm việc trên **Secomm Launchpad** — storefront **Magento 2.4.8-p5 + Hyvä 3.x** dành cho thị trường thời trang Việt Nam (vi_VN/en_US). Stack gồm theme Hyvä (Tailwind CSS v4, Magewire), thanh toán **Mollie + VNPAY**, **Mageplaza One Step Checkout**, và dropdown địa chỉ Việt Nam phân cấp.

Dự án **vừa được khởi tạo** và **sẵn sàng để bắt đầu giao tiếp**. Chưa có feature nào đang chạy — đây là lúc team chốt các quyết định chặn hoặc bắt đầu feature đầu tiên.

## Bắt đầu như thế nào?

Dự án vừa khởi tạo (repo mới, một commit duy nhất). Bạn có hai hướng đi:

1. **Giải quyết các quyết định đang chặn** (nếu bạn là SA / DevOps / TL):
   - **Multi-store intent** — dự án hiện single-store; `launchpad_fashion` theme + branch `development_fashion` gợi ý có thể có store thứ 2. Cần chốt với stakeholder.
   - **Hạ tầng production** — chưa có Redis, Varnish, OpenSearch, CI/CD nào được commit. Magento 2.4.8 yêu cầu OpenSearch ở production.
   - Xem đầy đủ: `.ai/runtime/DECISION_QUEUE.md`.

2. **Bắt đầu feature đầu tiên** (nếu blocking decisions không chặn bạn):
   - Đọc `guides/QUICK_START.md` → `guides/{YOUR_ROLE}_GUIDE.md`.
   - Rồi nói tự nhiên điều bạn muốn làm, ví dụ: *"soạn spec cho feature tìm kiếm sản phẩm"*.

## Supported tools

| Tool | Cách dùng |
|---|---|
| **Claude Code** (primary) | Slash commands + natural language |
| **Codex** | Natural language, hoặc paste command như prompt snippet |
| **GitHub Copilot** | Natural language, hoặc paste command như prompt snippet |

> Natural language **luôn work** ở mọi tool — command chỉ là alias ngắn gọn.

## 📍 Mỗi ngày mở file này

**`.ai/runtime/PROJECT_NAVIGATOR.md`** — bảng điều khiển hàng ngày. File cho bạn biết:
- Bạn đang ở phase nào, làm gì
- File nào cần đọc (kèm section cụ thể)
- Quyết định nào đang chờ bạn approve
- Bước tiếp theo là gì, ai own

> File này **tự cập nhật** — bạn không cần tự hỏi "tiếp theo làm gì". Khi lạc mất phương hướng, chỉ cần nói **"tôi đang ở đâu"**.

## 4 entry point chính

1. **`WELCOME.md`** (file này) — onboarding, đọc một lần.
2. **`.ai/runtime/PROJECT_NAVIGATOR.md`** — điều hướng hàng ngày.
3. **`.ai/runtime/DECISION_QUEUE.md`** — quyết định đang chờ bạn.
4. **`guides/CHEATSHEET.md`** — lệnh + câu nói + tình huống (một trang).

Mọi file khác đều reachable từ 4 entry point trên.

## Current stack

| Component | Value |
|---|---|
| Platform | Magento 2.4.8-p5 |
| Frontend | Hyvä 3.x (default theme 1.5.2) · Tailwind CSS v4 (CSS-first) · Magewire 1.13 |
| Theme chính | `Secomm/launchpad` (Hyvä child theme) |
| PHP / DB | PHP 8.2 · MySQL 8.0 |
| Market | Fashion / apparel, Vietnam (vi_VN primary + en_US) |
| Payments | Mollie (active) · VNPAY (custom, inactive) |
| Checkout | Mageplaza One Step Checkout |
| Workflow mode | A (new-build) |

## Known risks (top 3)

1. **VNPAY custom payment gateway** — chưa enable, cần security review trước khi go-live (IPN, chữ ký).
2. **Production infrastructure chưa định nghĩa** — chưa có Redis/Varnish/OpenSearch/CI; **BLOCKING** cho go-live.
3. **Search engine chưa cấu hình** — Magento 2.4.8 yêu cầu OpenSearch ở production; catalog search/index sẽ hỏng nếu thiếu.

## Useful links

- **📍 Daily nav:** `.ai/runtime/PROJECT_NAVIGATOR.md`
- **📋 Decisions:** `.ai/runtime/DECISION_QUEUE.md`
- Quick Start: `guides/QUICK_START.md`
- Cheat sheet (lệnh + câu nói): `guides/CHEATSHEET.md`
- Role guide của bạn: `guides/{YOUR_ROLE}_GUIDE.md` (TL / DEVELOPER / QC / DEVOPS / BA / SA)
- Commands: `guides/COMMAND_REFERENCE.md`
- FAQ: `guides/FAQ.md`
- Learning (onboarding): `learning/FIRST_DAY.md`
