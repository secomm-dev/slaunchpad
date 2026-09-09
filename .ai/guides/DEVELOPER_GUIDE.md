# Developer Guide — Secomm Launchpad

<!-- Enablement artifact — output: .ai/guides/DEVELOPER_GUIDE.md (VI). Role: Developer. -->

> **Purpose:** Hướng dẫn Developer — responsibilities + intents + workflow + tình huống.
> **Human Owner:** Developer · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "🧭 Tình huống thực tế" + "Supported intents" · **Expected Reading Time:** 8 min
> **Current Status:** Generated · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## Role responsibilities

Bạn implement code **theo approved plan, trong scope**. Dùng AI nhiều nhất trong team — phân tích ticket, code, review-code, cập nhật context. Bạn **không** tự quyết approve spec/architecture/deploy; high-risk area → escalate chứ không tự sửa.

**Ranh giới:**
- Implement theo plan; không modify code ngoài scope; không thêm dependency mà chưa TL approval.
- Bất kỳ change tới VNPAY (payment/IPN/chữ ký) → **SA review bắt buộc** (Tier 2).
- Bất kỳ change tới Mageplaza OSC checkout → end-to-end checkout QC + test payment.

## Supported intents

| Intent | Bạn nói | Command | Bạn nhận được |
|---|---|---|---|
| Phân tích ticket | "phân tích ticket SEC-42" | `/task` | Risk, affected areas, effort, blockers |
| Implement task | "implement ticket SEC-42" | (NL) | Plan gợi ý → code change trong scope |
| Pre-review code | "review code tôi vừa làm" | `/review-code` | Pre-review report (scope, security, business rule) **trước** TL review |
| Phân tích module Magento | "phân tích tác động module X" | `/magento-module-analysis` | Plugin/preference/observer/dependency impact |
| Impact checkout | "đổi X ảnh hưởng checkout không?" | `/magento-checkout-impact` | Tác động checkout/payment/shipping/order |
| Security review | "review bảo mật change này" | `/security-review` | Secret/permission/input findings |
| Cập nhật context | "cập nhật context sau change" | `/update-memory` | Diff đề xuất cập nhật project-context |
| Lưu session | "lưu session lại" | `/compact-context` | Session compact vào memory |
| Phục hồi session | "phục hồi session trước" | `/continue` | Trạng thái dựng lại từ memory |

## Typical workflow (business language)

**Discovery → Spec → Build → Review → Deploy**

Trong phase Build, bạn: nhận ticket + AC từ BA → plan từ TL → research vừa đủ → implement trong scope → **pre-review** (`/review-code`) → gửi PR cho TL. AI tự đi qua research/plan/code nội bộ và chỉ dừng khi cần bạn approve (ví dụ: phát hiện cần mở scope, hoặc chạm high-risk area).

## 🧭 Tình huống thực tế

| Tình huống | Bạn nói | AI làm gì | Kết quả mong đợi |
|---|---|---|---|
| Nhận ticket mới từ BA/TL | "implement ticket SEC-42" | Đọc objective + AC → research vừa đủ → plan + blockers | Plan gợi ý + câu hỏi phần thiếu + bước tiếp theo |
| Change chạm checkout/payment | "đổi X ảnh hưởng checkout không?" | Impact checkout end-to-end | Danh sách tác động + điểm phải test + có cần SA review không |
| Change module Magento | "phân tích tác động module Secomm_AddressDropdown" | Plugin/preference/observer/dependency impact | Impact map + rủi ro backward-compat |
| Trước khi gửi PR | "review code tôi vừa làm" | So sánh vs spec → scope → security → business rule | Pre-review report; chỉ decision TL phải làm |
| Thêm storefront string | (NL) — mô tả | Nhắc thêm vào cả `vi_VN.csv` + `en_US.csv` | String song ngữ |
| Sửa frontend Hyvä | "tạo Hyvä section mới cho Y" | Dùng dev skill Hyvä (Alpine + Tailwind v4 CSS-first) | Component theo pattern Hyvä (không React/Vue) |
| Hết session | "lưu session lại" | Compact context vào memory | Session lưu, sẵn sàng resume |

## Coding conventions (project-specific)

- **Tailwind CSS v4 CSS-first**: dùng `@theme`/`@source` trong `tailwind-source.css` — **KHÔNG** tạo `tailwind.config.js`.
- **Hyvä patterns**: Alpine.js + Magewire 1.13; phtml-driven, **không** React/Vue.
- **Vendor prefix**: `Secomm_` (project). `Mageplaza_*` = third-party — **không modify in-place**, extend bằng plugin/preference.
- **PHP 8.2+**, `strict_types`, Magento coding standard (compatible 8.2–8.4).
- **Storefront string** → thêm vào cả `vi_VN.csv` + `en_US.csv` (BR-001).
- **Đừng commit** production `env.php` / Redis / OpenSearch credentials.

## Expected outputs
- Code change (commit, trong scope) + AI pre-review report.
- Test case gợi ý / test written.
- Context diff (project-context update) sau task.
- Estimation actual log.

## Common mistakes

| Mistake | Fix |
|---|---|
| Sửa Mageplaza in-place | Extend bằng plugin/preference — coi như third-party |
| Tạo `tailwind.config.js` | Tailwind v4 CSS-first: `@theme`/`@source` trong `tailwind-source.css` |
| Thêm string chỉ 1 ngôn ngữ | Thêm vào **cả** `vi_VN.csv` + `en_US.csv` |
| Bỏ qua pre-review để nhanh | Pre-review (`/review-code`) bắt buộc **trước** TL review |
| Tự sửa VNPAY/checkout khi chạm high-risk | Escalate SA (Tier 2) — không tự quyết |

## Best practices
- [ ] Plan match + cover mọi AC trước khi code.
- [ ] Scope clean — không out-of-scope.
- [ ] No hardcoded secret/URL.
- [ ] Error handling + tôn trọng business rule (BR-001..006).
- [ ] Pre-review pass + evidence saved trước khi gửi TL.

> Phần kỹ thuật nội bộ được xử lý tự động — bạn chỉ cần biết intents + commands + workflow.
