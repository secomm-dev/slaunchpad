# Solution Architect (SA) Guide — Secomm Launchpad

<!-- Enablement artifact — output: .ai/guides/SA_GUIDE.md (VI). Role: SA. -->

> **Purpose:** Hướng dẫn SA — architecture, integration contract, design decision, ADR.
> **Human Owner:** Solution Architect · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "🧭 Tình huống thực tế" + "Architecture decisions" · **Expected Reading Time:** 8 min
> **Current Status:** Generated · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## Role responsibilities

Bạn **own architecture + integration contract + design decision**: đánh giá architecture option, approve integration contract/schema, viết ADR. Bạn decide architecture/integration/schema (Tier 2 authority); **không** execute deploy; **không** approve own code review (TL làm).

**Trong dự án vừa khởi tạo**, việc ưu tiên: chốt **multi-store intent** (architecture-affecting) + **integration contract** cho VNPAY (payment/IPN/chữ ký). Đây là 2 blocking decisions lớn nhất cần SA.

## Supported intents

| Intent | Bạn nói | Command | Bạn nhận được |
|---|---|---|---|
| Phân tích module Magento | "phân tích tác động module Secomm_AddressDropdown" | `/magento-module-analysis` | Plugin/preference/observer/dependency impact |
| Impact checkout/payment | "đổi VNPAY IPN ảnh hưởng gì?" | `/magento-checkout-impact` | Tác động end-to-end checkout/payment/order |
| Security review (payment) | "review bảo mật VNPAY change" | `/security-review` | Secret/permission/input findings |
| Phân tích ticket (architecture) | "phân tích ticket SEC-42" | `/task` | Affected areas + architecture risk |
| Xem quyết định chờ | "phải approve gì" | `/decisions` | Decision architecture/integration đang chờ |
| Approve decision | "tôi đồng ý opt-b" | `/approve` | Architecture decision chốt → ADR |

## Typical workflow (business language)

**Discovery → Spec → Build → Review → Deploy**

Bạn vào ở phase **Design/Architecture** (Mode A): nhận requirement từ BA → architecture question từ TL → đánh giá option (pros/cons) → integration contract xác nhận → schema change approval (có migration + rollback) → ADR. Bạn là Tier 2 authority cho architecture/DB/integration.

## 🧭 Tình huống thực tế

| Tình huống | Bạn nói | AI làm gì | Kết quả mong đợi |
|---|---|---|---|
| Chốt multi-store intent | "phải approve gì" | DEC multi-store + option + impact | Bạn approve → ADR → TL/Dev triển khai |
| Thiết kế integration VNPAY | "đổi VNPAY IPN ảnh hưởng gì?" | Impact payment/IPN/order | Contract + idempotency + chữ ký + ADR |
| DB schema change | (NL) — mô tả | Migration + rollback + backward-compat check | Approval schema + migration plan |
| Architecture review | "phân tích tác động module X" | Plugin/preference/observer impact | Findings + backward-compat rủi ro |
| Refactor major | (NL) — mô tả | Option + pros/cons | ADR với rationale |

## Architecture decisions (project-specific — cần SA)

| Khu vực | Vấn đề | Tier |
|---|---|---|
| **Multi-store intent** | Single-store hiện tại; dấu hiệu store thứ 2 (`launchpad_fashion`) → ảnh hưởng scope/theme/catalog | **Tier 2 — BLOCKING** |
| **VNPAY payment** | Custom gateway: IPN, chữ ký, idempotency, enable config | **Tier 2 — SA review bắt buộc** |
| **Mageplaza OSC** | Thay checkout mặc định; tương tác payment/address | Tier 2 |
| **Address dropdown VN** | Custom GraphQL surface + data import | Tier 2 |
| **Search** | OpenSearch vs core search — production bắt buộc OpenSearch | Tier 2 |

## SA review checklist
- [ ] Architecture option + pros/cons document.
- [ ] Integration contract xác nhận (không assumption).
- [ ] DB schema change có migration + rollback.
- [ ] Backward compatibility giữ.
- [ ] ADR ghi rationale rõ.

## Coding conventions (SA phải giữ)
- **Tailwind CSS v4 CSS-first** — không `tailwind.config.js`.
- **Hyvä patterns** — Alpine.js + Magewire; phtml-driven.
- **Vendor prefix**: `Secomm_` (project), `Vnpayment_` (payment); `Mageplaza_*` = third-party, extend bằng plugin/preference.
- **PHP 8.2+**, strict_types, backward-compat (8.2–8.4).

## Common mistakes

| Mistake | Fix |
|---|---|
| Approve integration contract theo assumption | Xác nhận contract thực tế — không guess |
| Schema change thiếu rollback | Mỗi schema change có migration + rollback |
| Phá backward-compat | Giữ backward-compat (rule project) |

## Best practices
- [ ] Mỗi architecture decision có ADR + rationale.
- [ ] Integration contract xác nhận trước khi giao dev.
- [ ] DB schema change: migration + rollback + backward-compat.
- [ ] VNPAY/checkout/IPN change → SA review (bạn là authority).

> Phần kỹ thuật nội bộ được xử lý tự động — bạn quyết architecture bằng business language.
