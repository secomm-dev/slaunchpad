# Best Practices — Secomm Launchpad

<!-- Enablement artifact — output: .ai/learning/BEST_PRACTICES.md (VI). Project-specific best practices. -->

> **Purpose:** Thực hành tốt cho project này — để làm việc hiệu quả + ít bug.
> **Human Owner:** All roles · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** theo chủ đề · **Expected Reading Time:** 5 min
> **Current Status:** Generated · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## Điều hướng hàng ngày
- [ ] Mở `../runtime/PROJECT_NAVIGATOR.md` đầu mỗi ngày — không tự hỏi "tiếp theo làm gì".
- [ ] Khi lạc, nói *"tôi đang ở đâu"* thay vì đoán.
- [ ] Approve **decision** (DEC-ID), không approve cả document.
- [ ] Khi cần trì hoãn, dùng `/tbd` ("chưa quyết" / "để hỏi client") — đừng bỏ lơ.

## Workflow
- [ ] Natural language là first-class — không cần gõ command chính xác.
- [ ] AI pre-review (`/review-code`) **luôn** trước TL review — không skip.
- [ ] Khi AI dừng, đó là lúc **bạn** cần approve — đọc decision + impact rồi chọn.
- [ ] Giữ scope; mở scope phải approve, không tự ý.

## Magento + Hyvä
- [ ] **Tailwind v4 CSS-first**: config trong `@theme`/`@source` của `tailwind-source.css`; không tạo `tailwind.config.js`.
- [ ] **Hyvä patterns**: Alpine.js + Magewire; phtml-driven; không React/Vue.
- [ ] **Mageplaza = third-party**: extend bằng plugin/preference; không modify in-place; theo dõi version.
- [ ] **Vendor prefix**: `Secomm_` (project), `Vnpayment_` (payment).
- [ ] **PHP 8.2+**, `strict_types`, Magento coding standard (8.2–8.4 compatible).
- [ ] **Storefront string** → cả `vi_VN.csv` + `en_US.csv` (BR-001).
- [ ] **Address VN** (BR-002): validate cascade country→state→city→sub-city end-to-end + admin CRUD + CSV.
- [ ] **Checkout** (BR-004): end-to-end QC + test payment trên mỗi change.

## High-risk areas (luôn cẩn thận)
- [ ] **VNPAY** (payment/IPN/chữ ký): SA review (Tier 2) bắt buộc; test idempotency + chữ ký.
- [ ] **Mageplaza OSC**: regression checkout + payment.
- [ ] **Address dropdown VN**: data quality + GraphQL surface.
- [ ] **Search**: production phải cấu hình OpenSearch.
- [ ] **Production config**: đừng commit prod env.php / credentials.

## DevOps
- [ ] Production: Redis (cache+sessions) + Varnish (FPC) + OpenSearch — bắt buộc.
- [ ] Rollback: **trigger cụ thể** + post-verify.
- [ ] Smoke test critical flow: checkout/payment/search.
- [ ] Watch window sau deploy (cron throughput).
- [ ] Giữ Hyvä Packagist token hợp lệ (auth.json).

## QC
- [ ] Mỗi AC có test case; edge (empty/boundary) + negative + business rule.
- [ ] Regression trên affected area.
- [ ] Bug report structured.

## Communication
- [ ] Client comms = draft → PM/TL review trước khi gửi.
- [ ] Spec SA/TL review trước khi giao dev.
- [ ] Assumption / open question ghi riêng, không trộn vào requirement.
- [ ] `[TBD]` là `[TBD]` — đừng bịa; flag cho stakeholder.

## Documentation as contract
- [ ] Nếu toolkit regenerate (capability mới), enablement regenerate tự động — không hand-edit thêm ad-hoc.
- [ ] Chỉ document capability thực sự được chọn — không future roadmap.

## Khi không chắc
- [ ] Đọc `../guides/FAQ.md`.
- [ ] Mở `../runtime/DECISION_QUEUE.md` xem decision đang chờ.
- [ ] Nói *"tôi đang ở đâu"*.
