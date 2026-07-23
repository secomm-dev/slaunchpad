# Common Mistakes — Secomm Launchpad

<!-- Enablement artifact — output: .ai/learning/COMMON_MISTAKES.md (VI). Top mistakes to avoid (Magento + Hyvä + this project). -->

> **Purpose:** Sai lầm phổ biến + cách sửa — để team không lặp lại.
> **Human Owner:** All roles · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** theo role · **Expected Reading Time:** 5 min
> **Current Status:** Generated · **Next Step:** Đọc `BEST_PRACTICES.md`

## Project-specific (Magento + Hyvä)

| ❌ Don't | Tại sao sai | ✅ Do |
|---|---|---|
| Tạo `tailwind.config.js` | Project dùng Tailwind CSS v4 CSS-first | Dùng `@theme`/`@source` trong `tailwind-source.css` của theme |
| Modify module Mageplaza in-place | Commit dưới dạng source nhưng là third-party; sửa trực tiếp gây version drift + mất khi update | Extend bằng plugin/preference; theo dõi version thủ công |
| Thêm storefront string chỉ 1 ngôn ngữ | Project song ngữ (BR-001) | Thêm vào **cả** `vi_VN.csv` + `en_US.csv` |
| Dùng React/Vue ở frontend | Stack là Hyvä (Alpine.js + Magewire, phtml-driven) | Dùng pattern Hyvä (Alpine component + Magewire) |
| Commit production `env.php` / credentials | Repo chỉ giữ local-dev config; lộ secret + sai cấu hình | Giữ prod env tách riêng; không commit secret |
| Bật VNPAY mà chưa security review | Custom payment: IPN + chữ ký + PCI-sensitive | Enable + security review + test idempotency/chữ ký trước go-live |

## Workflow / AI usage

| ❌ Don't | Tại sao sai | ✅ Do |
|---|---|---|
| Hỏi "tiếp theo làm gì" liên tục | Navigator đã cho biết; AI tự tiếp tục | Mở Navigator; chỉ gõ `/next` khi thực sự lạc |
| Skip pre-review để nhanh | Pre-review bắt buộc trước TL review | Luôn `/review-code` trước khi gửi TL |
| Approve cả document thay vì decision | Decision (DEC-ID) mới là đơn vị approve | Approve DEC cụ thể (`/approve DEC-001 opt-b`) |
| Tự quyết change VNPAY/checkout khi chạm high-risk | Tier 2 — cần SA | Escalate SA; không tự sửa |
| Rollback "if something goes wrong" | Trigger chung chung = không rollback được | Trigger cụ thể + post-verify |

## Developer-specific

| ❌ Don't | ✅ Do |
|---|---|
| Implement ngoài scope | Giữ scope; mở scope phải approve |
| Hardcode secret/URL | Config/env; không hardcode |
| Bỏ error handling / business rule | Respect BR-001..006 + error handling |

## QC-specific

| ❌ Don't | ✅ Do |
|---|---|
| Chỉ test happy path | Edge (empty/boundary) + negative + business rule interaction |
| Bỏ regression affected area | Regression trên area bị ảnh hưởng (checkout/address/payment) |
| Bug report thiếu steps | Structured: steps + actual/expected + env + evidence |

## DevOps-specific

| ❌ Don't | ✅ Do |
|---|---|
| Deploy không smoke test | Smoke test checkout/payment/search bắt buộc |
| Quên watch window sau deploy | Monitoring watch window (đặc biệt cron throughput) |
| Put secret vào log/prompt | Không secret trong output/log |

## BA-specific

| ❌ Don't | ✅ Do |
|---|---|
| AC không testable | Mỗi AC QC biết pass/fail |
| Trộn assumption vào requirement | Ghi assumption/open question riêng |
| Gửi client comms chưa review | Draft → PM/TL review trước |

## SA-specific

| ❌ Don't | ✅ Do |
|---|---|
| Approve integration contract theo assumption | Xác nhận contract thực tế |
| Schema change thiếu rollback | Migration + rollback + backward-compat |
| Phá backward-compat | Giữ backward-compat |

## TL-specific

| ❌ Don't | ✅ Do |
|---|---|
| Skip pre-review để nhanh | Đọc pre-review report rồi quyết |
| Rollback chung chung | Yêu cầu trigger cụ thể |
| Tự quyết architecture payment | Escalate SA (Tier 2) |
