# Role Checklist — Secomm Launchpad

<!-- Enablement artifact — output: .ai/learning/ROLE_CHECKLIST.md (VI). Per-role onboarding checklist. -->

> **Purpose:** Checklist onboarding theo role — tick từng mục để chắc chắn sẵn sàng.
> **Human Owner:** New team members · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** Role của bạn · **Expected Reading Time:** 5 min
> **Current Status:** Generated · **Next Step:** Hoàn thành → mở `../runtime/PROJECT_NAVIGATOR.md`

## Chung (mọi role)
- [ ] Đọc `../WELCOME.md`
- [ ] Đọc `../guides/QUICK_START.md`
- [ ] Đọc `../guides/PROJECT_OVERVIEW.md`
- [ ] Đọc role guide của bạn (`../guides/{ROLE}_GUIDE.md`)
- [ ] Đọc `../guides/COMMAND_REFERENCE.md` + `CHEATSHEET.md`
- [ ] Biết 4 entry point: WELCOME · Navigator · Decision Queue · Cheatsheet
- [ ] Biết supported tools (Claude Code / Codex / GitHub Copilot)
- [ ] Mở `../runtime/PROJECT_NAVIGATOR.md` + thử *"tôi đang ở đâu"*
- [ ] Hiểu workflow Mode A: Discovery → Spec → Build → Review → Deploy
- [ ] Hiểu AI chỉ dừng khi cần approve decision

## Project context (Magento + Hyvä)
- [ ] Biết Tailwind CSS v4 CSS-first — **không** tạo `tailwind.config.js`
- [ ] Biết Hyvä patterns (Alpine + Magewire, phtml-driven, không React/Vue)
- [ ] Biết Mageplaza = third-party — extend bằng plugin/preference, không modify in-place
- [ ] Biết vendor prefix: `Secomm_` (project)
- [ ] Biết storefront string → cả `vi_VN.csv` + `en_US.csv`
- [ ] Biết 6 business rules (BR-001..006)
- [ ] Biết 5 high-risk areas (VNPAY, OSC, address dropdown, prod infra, search)
- [ ] Biết VNPAY hiện **inactive**; Mollie mới active
- [ ] Biết repo chỉ giữ local-dev config (đừng commit prod env.php)

## BA
- [ ] Thử `/spec` (soạn 1 mini-spec)
- [ ] Thử `/task` (phân tích 1 ticket)
- [ ] Biết open questions: objective/KPI, multi-store, timeline
- [ ] Biết AC phải testable; assumption ghi riêng

## SA
- [ ] Hiểu DEC multi-store (BLOCKING)
- [ ] Hiểu VNPAY integration contract (IPN/chữ ký/idempotency)
- [ ] Biết Tier 2 authority: architecture/DB/integration
- [ ] Thử `/magento-module-analysis` + `/magento-checkout-impact`

## TL
- [ ] Biết gate checklist (plan/pre-review/high-risk/scope/rollback)
- [ ] Biết escalation Tier 1 (bạn) vs Tier 2 (SA)
- [ ] Biết 2 blocking decisions cần chốt
- [ ] Thử `/decisions` + đọc pre-review report

## Developer
- [ ] Thử `/task` + `/review-code`
- [ ] Biết coding conventions (Tailwind v4, Hyvä, vendor prefix, PHP 8.2+)
- [ ] Biết dev skills có sẵn (create-module, hyva-alpine-component, ...)
- [ ] Biết VNPAY/checkout change → escalate SA
- [ ] Thử `/update-memory`

## QC
- [ ] Thử `/testcase`
- [ ] Biết test focus: OSC checkout, address dropdown VN, payment, song ngữ, shipping
- [ ] Biết bug report structured (steps + actual/expected + env)
- [ ] Biết regression trên affected area sau mỗi change

## DevOps
- [ ] Thử `/deploy`
- [ ] Biết production readiness gap: OpenSearch, Redis, Varnish, CI/CD, staging
- [ ] Biết smoke test critical flow (checkout/payment/search)
- [ ] Biết rollback phải có trigger cụ thể + post-verify

> Khi tick hết → bạn sẵn sàng. Mở `../runtime/PROJECT_NAVIGATOR.md` và bắt đầu.
