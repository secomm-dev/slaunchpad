# First Day — Secomm Launchpad

<!-- Enablement artifact — output: .ai/learning/FIRST_DAY.md (VI). Day-1 onboarding checklist. -->

> **Purpose:** Checklist ngày đầu — 60 phút đầu tiên của bạn.
> **Human Owner:** New team members · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "60 phút đầu" · **Expected Reading Time:** 4 min
> **Current Status:** Generated · **Next Step:** Hoàn thành → đọc role guide → mở Navigator

Chào mừng bạn đến **Secomm Launchpad** — storefront Magento 2.4.8 + Hyvä cho thị trường thời trang Việt Nam. Dưới đây là 60 phút đầu tiên.

## 60 phút đầu

### 1. Đọc bối cảnh (15 phút)
- [ ] Đọc `../WELCOME.md` — biết project là gì, supported tools, 4 entry point.
- [ ] Đọc `../guides/QUICK_START.md` — biết bạn là ai trong dự án.

### 2. Mở điều hướng hàng ngày (5 phút)
- [ ] Mở `../runtime/PROJECT_NAVIGATOR.md` — đây là "bảng điều khiển" mỗi ngày.
- [ ] Nói (hoặc gõ): *"tôi đang ở đâu"* — xem AI phản hồi thế nào.
- [ ] Mở `../runtime/DECISION_QUEUE.md` — xem có quyết định nào đang chờ không.

### 3. Hiểu bối cảnh kỹ thuật (15 phút)
- [ ] Đọc `../guides/PROJECT_OVERVIEW.md` — objective, stack, business rules, high-risk.
- [ ] Ghi nhớ 3 điều quan trọng:
  - Tailwind CSS v4 CSS-first — **không** tạo `tailwind.config.js`.
  - Mageplaza OSC thay checkout mặc định; VNPAY hiện **inactive** (Mollie active).
  - Production infra chưa có (Redis/Varnish/OpenSearch/CI) — **BLOCKING** cho go-live.

### 4. Đọc role guide của bạn (15 phút)
- [ ] Mở `../guides/{YOUR_ROLE}_GUIDE.md` (BA / SA / TL / DEVELOPER / QC / DEVOPS).
- [ ] Note lại 2-3 command bạn sẽ dùng nhiều nhất.

### 5. Thử 1 action (10 phút)
- [ ] Theo role, thử 1 command (ví dụ Developer → *"phân tích ticket demo"* / `/task`; BA → *"soạn spec demo"* / `/spec`).
- [ ] Đọc output — hiểu AI phản hồi dạng nào.

## Sau ngày đầu
- Đọc `../guides/CHEATSHEET.md` (1 trang).
- Đọc `COMMON_MISTAKES.md` + `BEST_PRACTICES.md`.
- Làm `ROLE_CHECKLIST.md`.

## 3 điều đừng làm
1. **Đừng** hỏi "tiếp theo làm gì" liên tục — mở Navigator; AI chỉ dừng khi cần approve.
2. **Đừng** tạo `tailwind.config.js` — Tailwind v4 là CSS-first.
3. **Đừng** modify Mageplaza in-place — coi như third-party, extend bằng plugin/preference.

## Khi cần giúp
- Lạc hướng: *"tôi đang ở đâu"*.
- Không hiểu bước: *"bước này để làm gì"*.
- Câu hỏi chung: đọc `../guides/FAQ.md`.
