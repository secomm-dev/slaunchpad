# QC Guide — Secomm Launchpad

<!-- Enablement artifact — output: .ai/guides/QC_GUIDE.md (VI). Role: QC. -->

> **Purpose:** Hướng dẫn QC — test case, acceptance verification, regression.
> **Human Owner:** QC · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "🧭 Tình huống thực tế" + "Test focus (Magento/Hyvä)" · **Expected Reading Time:** 7 min
> **Current Status:** Generated · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## Role responsibilities

Bạn **verify deliverable trước khi đến client**: sinh test case, test acceptance criteria, regression check. Bạn test từng feature (không quyết release — đó là TL/PM); manual testing (UI/UX/responsive) vẫn cần human.

**Trong dự án vừa khởi tạo**, việc ưu tiên: chuẩn bị test approach cho 3 khu vực high-risk — **VNPAY** (khi enable), **Mageplaza OSC checkout**, **address dropdown VN phân cấp**.

## Supported intents

| Intent | Bạn nói | Command | Bạn nhận được |
|---|---|---|---|
| Sinh test case | "sinh test case cho spec này" | `/testcase` | Test case list (happy/edge/negative) theo AC |
| Xem scope test | "review scope QC cho change này" | `/review-code` | Phạm vi cần test + điểm rủi ro |
| Impact checkout | "đổi X ảnh hưởng checkout không?" | `/magento-checkout-impact` | Tác động checkout/payment/shipping/order |
| Phân tích ticket | "phân tích ticket SEC-42" | `/task` | AC + affected areas để lên test plan |
| Xem quyết định chờ | "phải approve gì" | `/decisions` | Decision liên quan test/UAT |

## Typical workflow (business language)

**Discovery → Spec → Build → Review → Deploy**

Bạn vào ở phase **Review/Test**: nhận deployment notes từ Developer + spec/AC từ BA → sinh test case (`/testcase`) → test từng AC (happy/edge/negative) → regression trên affected area → QC report (pass/fail/blocked per AC) → bug report cho Developer nếu fail → signoff cho TL/PM.

## 🧭 Tình huống thực tế

| Tình huống | Bạn nói | AI làm gì | Kết quả mong đợi |
|---|---|---|---|
| Nhận spec mới | "sinh test case cho spec này" | Test case happy/edge/negative theo AC | Test case list + điểm rủi ro business rule |
| Change chạm checkout | "đổi X ảnh hưởng checkout không?" | Impact end-to-end | Kế hoạch regression checkout + payment |
| Test address dropdown VN | (NL) — "test BR-002" | Gợi ý walk full cascade | Steps: country→state→city→sub-city + admin CRUD + CSV import/export |
| Báo cáo QC | "tóm tắt kết quả test" | QC report per AC | Pass/fail/blocked + bug report structured |
| Hỗ trợ UAT | "chuẩn bị UAT guide" | Draft UAT guide | Steps cho client verify |

## Test focus (Magento + Hyvä — project-specific)

| Khu vực | Tại sao quan trọng | Cần verify |
|---|---|---|
| **Mageplaza One Step Checkout** (BR-004) | Thay checkout mặc định | End-to-end: cart → OSC → payment → order. ExtraFee + DeliveryTime (BR-006). |
| **Address dropdown VN** (BR-002) | Custom AJAX cascade | Country → state/province → city/district → sub-city/ward. Admin CRUD + CSV import/export. |
| **Payment** (BR-003) | Mollie active; VNPAY inactive | Mollie: place order + webhook. VNPAY: **chỉ test khi đã enable** — redirect + IPN + chữ ký. |
| **Song ngữ** (BR-001) | vi_VN primary + en_US | Switch locale; verify string render ở cả hai. |
| **Shipping TableRate** (BR-005) | Carrier `mptablerate`, dimensional | Enable carrier; verify dimensional (L/W/H, factor 5000). |

## Expected outputs
- Test case list (happy/edge/negative) theo AC.
- QC report: pass/fail/blocked per AC.
- Bug report structured: steps + actual vs expected + environment + evidence.
- Regression check result trên affected area.

## Common mistakes

| Mistake | Fix |
|---|---|
| Chỉ test happy path | Bắt buộc edge (empty/boundary) + negative + business rule interaction |
| Bỏ regression affected area | Mỗi change → regression trên area bị ảnh hưởng (checkout, address, payment) |
| Bug report thiếu steps | Structured: steps + actual/expected + environment + evidence |

## Best practices
- [ ] Mỗi AC có ít nhất 1 test case.
- [ ] Business rule interaction verify (không chỉ happy path).
- [ ] Edge case: empty/boundary/concurrent.
- [ ] Regression trên affected area sau mỗi change.
- [ ] Bug report structured (steps + actual/expected + environment).

> Phần kiến trúc nội bộ xử lý tự động — bạn tập trung test theo business rule (BR-001..006).
