# FAQ — Secomm Launchpad

<!-- Enablement artifact — output: .ai/guides/FAQ.md (VI). Generated from project context + known limitations + workflow. Never invent unsupported answers. -->

> **Purpose:** Câu hỏi thường gặp + known limitations của project.
> **Human Owner:** All roles · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "Getting started" + "Known limitations" + "Project-specific" · **Expected Reading Time:** 5 min
> **Current Status:** Generated · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## Getting started

**Q: Tôi bắt đầu từ đâu?**
A: Đọc `../WELCOME.md` → `QUICK_START.md` → `{YOUR_ROLE}_GUIDE.md`. Dự án vừa khởi tạo nên bước đầu thường là giải quyết quyết định chặn (xem `../runtime/DECISION_QUEUE.md`) hoặc bắt đầu feature đầu tiên.

**Q: Làm sao biết làm gì tiếp theo?**
A: Mở `../runtime/PROJECT_NAVIGATOR.md`, hoặc nói *"tôi đang ở đâu"*. AI tự tiếp tục qua các bước nội bộ và chỉ dừng khi cần bạn approve một decision.

**Q: Tool nào được support?**
A: **Claude Code** (primary — slash command + NL), **Codex** và **GitHub Copilot** (NL, hoặc paste command như prompt snippet). Chỉ 3 tool này được support.

## Workflow

**Q: Workflow của project là gì?**
A: Mode A (new-build): **Discovery → Spec → Build → Review → Deploy**. Plan approval, code review, AI pre-review đều bắt buộc.

**Q: Khi nào AI dừng lại hỏi tôi?**
A: Chỉ khi cần bạn approve một **decision** (ví dụ chọn option kiến trúc, hoặc confirm scope out-of-scope). Decision hiện lên với DEC-ID — bạn approve bằng `/approve` (hoặc "tôi đồng ý").

**Q: Tôi có thể dùng natural language thay vì commands không?**
A: Có — NL luôn works, ở mọi tool. Command chỉ là alias ngắn gọn.

**Q: Command nào tôi KHÔNG có?**
A: Chỉ những command được generate cho project/role này mới có (xem `COMMAND_REFERENCE.md`). Command không có ở đó = chưa được support — không dùng được.

## Project-specific (Magento + Hyvä)

**Q: Tailwind config ở đâu? Tôi có tạo `tailwind.config.js` không?**
A: **Không.** Project dùng Tailwind CSS v4 CSS-first — config nằm trong `@theme`/`@source` trong `tailwind-source.css` của theme (`app/design/frontend/Secomm/launchpad`). Đừng tạo `tailwind.config.js`.

**Q: Tôi sửa module Mageplaza như thế nào?**
A: Mageplaza được commit dưới dạng **source** trong `app/code/Mageplaza` — coi như third-party. **Không modify in-place**; extend bằng plugin/preference. Theo dõi version thủ công.

**Q: Thêm string storefront mới thì làm gì?**
A: Phải thêm vào **cả** `vi_VN.csv` (primary) **và** `en_US.csv`. Project song ngữ (BR-001).

**Q: VNPAY dùng được chưa?**
A: **Chưa.** VNPAY (custom module) mặc định **inactive**. Cần enable + cấu hình TmnCode/hash secret + security review (IPN, chữ ký) trước khi go-live. Mollie mới là payment active hiện tại.

**Q: Multi-store hay single-store?**
A: Hiện **single-store** (scope chỉ ở DB). Nhưng `launchpad_fashion` theme + branch `development_fashion` gợi ý có thể có store thứ 2 → **cần chốt với stakeholder** (decision đang chặn).

**Q: Search dùng gì?**
A: Hiện chỉ **core Magento search** (không ElasticSuite/Smile). Magento 2.4.8 yêu cầu **OpenSearch** ở production — chưa cấu hình, cần setup trước launch.

## Known limitations

**Q: Có CI/CD chưa?**
A: **Chưa.** Repo chưa commit pipeline nào. Bitbucket Pipelines là lựa chọn tự nhiên (repo host = Bitbucket) — `[TBD]`.

**Q: Có staging/production env chưa?**
A: **Chưa.** Chỉ có local-dev (`MAGE_MODE=developer`, MySQL 127.0.0.1, file cache + file sessions). Staging + production: `[TBD]`.

**Q: Local-dev có Redis/Varnish/OpenSearch/RabbitMQ không?**
A: **Không.** Local-dev dùng file cache + file sessions. Production **phải** cấu hình Redis (cache + sessions) + Varnish (FPC) + OpenSearch. Đừng commit production `env.php`.

**Q: Có bug nào đã biết chưa?**
A: Chưa — repo vừa khởi tạo (single commit). Xem `../project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md` cho rủi ro + constraint.

## Performance & scalability

**Q: Performance target là gì?**
A: `[TBD]` — confirm với stakeholder. Hiện AbandonedCart cron chạy mỗi phút → cần theo dõi throughput dưới load ở production.
