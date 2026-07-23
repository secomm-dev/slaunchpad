# Technical Lead (TL) Guide — Secomm Launchpad

<!-- Enablement artifact — output: .ai/guides/TL_GUIDE.md (VI). Role: TL. -->

> **Purpose:** Hướng dẫn TL — quality gate + plan/code review + escalation Tier 1.
> **Human Owner:** Technical Lead · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "🧭 Tình huống thực tế" + "Gate" · **Expected Reading Time:** 8 min
> **Current Status:** Generated · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## Role responsibilities

Bạn **own quality gate**: plan approval, code review (sau AI pre-review), gate enforcement (Requirement / Code / Test / Release), escalation Tier 1. Bạn approve plan/code/release; **không** decide architecture Tier 2 (escalate SA); **không** deploy (DevOps execute). Không skip AI pre-review để nhanh.

**Trong dự án vừa khởi tạo**, việc ưu tiên: chốt 2 blocking decisions (multi-store intent; hạ tầng production) cùng SA/DevOps, và approve plan đầu tiên khi team bắt đầu feature.

## Supported intents

| Intent | Bạn nói | Command | Bạn nhận được |
|---|---|---|---|
| Xem quyết định chờ | "phải approve gì" | `/decisions` | Decision đang chờ + owner + impact |
| Approve decision | "tôi đồng ý" / "approve DEC-001 opt-b" | `/approve` | Decision chốt, Navigator cập nhật |
| Phân tích ticket/plan | "phân tích ticket SEC-42" | `/task` | Risk + affected areas + effort |
| Review code (đọc pre-review) | "review code này" | `/review-code` | Pre-review report để bạn xét |
| Security review | "review bảo mật change này" | `/security-review` | Secret/permission/input findings |
| Impact checkout/payment | "đổi X ảnh hưởng checkout không?" | `/magento-checkout-impact` | Tác động end-to-end (Tier 2) |
| Tóm tắt status | "tóm tắt status dự án" | `/status` | Status summary cho stakeholder |

## Typical workflow (business language)

**Discovery → Spec → Build → Review → Deploy**

Bạn là gate ở các phase: approve **Spec** (plan cover mọi AC), approve **Code** (sau pre-review của Developer), approve **Release** (deployment checklist có rollback cụ thể). AI pre-review luôn chạy trước — bạn đọc pre-review report rồi quyết.

## Gate checklist (TL phải giữ)

- [ ] Plan cover mọi AC của ticket.
- [ ] AI pre-review **pass** (no critical) — không skip.
- [ ] High-risk area check trên diff (VNPAY, Mageplaza OSC, address dropdown).
- [ ] Scope clean — không out-of-scope.
- [ ] Deployment checklist có **rollback trigger cụ thể** (không "if something goes wrong").
- [ ] Context update review trước commit.

## 🧭 Tình huống thực tế

| Tình huống | Bạn nói | AI làm gì | Kết quả mong đợi |
|---|---|---|---|
| Developer gửi task chờ review | "review code này" | So sánh vs spec → scope → security → business rule | Pre-review report; chỉ decision bạn phải làm |
| Phải chốt multi-store intent | "phải approve gì" | Liệt kê DEC + option + impact | Bạn approve option → Navigator cập nhật |
| Change chạm VNPAY/checkout | "đổi X ảnh hưởng checkout không?" | Impact end-to-end + có cần SA không | Escalate SA (Tier 2) nếu payment/IPN |
| Approve release | "sinh deployment checklist" | Pre-deploy + rollback + post-deploy | Bạn signoff readiness → DevOps execute |
| Lạc hướng ưu tiên | "tôi đang ở đâu" | Phase + decision chờ + 3 action | Biết làm gì tiếp |

## Escalation

| Tier | Khi nào | Ai |
|---|---|---|
| Tier 1 (bạn) | Code/scope/quality decision thông thường | TL |
| Tier 2 | VNPAY payment/IPN/chữ ký; DB schema; integration contract; architecture | Escalate **SA** |

## Common mistakes

| Mistake | Fix |
|---|---|
| Skip pre-review để nhanh | Pre-review (`/review-code`) bắt buộc trước — đọc report rồi quyết |
| Approve release mà rollback chung chung | Yêu cầu rollback **trigger cụ thể** + post-verify |
| Tự quyết architecture payment | VNPAY/checkout/IPN → escalate SA (Tier 2) |

## Best practices
- [ ] Gate enforcement đều đặn — đừng tích lũy.
- [ ] Mỗi PR có pre-review; mỗi release có rollback cụ thể.
- [ ] High-risk area (§12) check trên mỗi diff.
- [ ] Coordinate với SA + DevOps cho 2 blocking decisions.

> Phần kỹ thuật nội bộ được xử lý tự động — bạn giữ gate bằng business language.
