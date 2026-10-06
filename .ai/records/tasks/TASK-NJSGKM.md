---
id: TASK-NJSGKM
type: task
title: '[Checkout page] Điều chỉnh font-family trang checkout theo style guide (SLP-324)'
project_code: SLP
parent: null
external_refs:
  tickets: SLP-324
legacy_ids: []
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_review
created: 2026-10-06
updated: 2026-10-06
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: display-only CSS + static fonts trên OSC page (§12 checkout) — Tier-2 formality, không chạm flow/order/payment logic; follow precedent BUG-AMRBJR/TASK-8TXS2P/BUG-ER121M (display-only → Mode B/C + QC demo). Scope biên chốt theo directive "thực thi": trang checkout chính; success page + modal social login (TASK-NWV2MQ đang "giữ font Open Sans chờ design") để flag TL.
components:
  - app/code/Launchpad/Osc
source_areas:
  - mageplaza-osc-checkout
  - ll-0011-luma-scope
  - style-guide-inter-selfhosted
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: 0b91d801
last_verified: 2026-10-06
supersedes: []
---

# [SLP][TASK-NJSGKM] [Checkout page] Điều chỉnh font-family trang checkout theo style guide (SLP-324)

## Embedded Mini-Spec (Context + AC + Approach)

### Context

- Style guide chốt font storefront = **Inter self-hosted** (`hyva-global-style-foundation.yaml` `approved_modes.font: Inter`; theme `--font-sans: "Inter", ui-sans-serif, system-ui, sans-serif` tại `tailwind-source.css:47`; `/checkout/` nằm trong `validation_pages`).
- Trang `/onestepcheckout/` render **Magento/luma scope** (LL-0011, đo 453/453 assets): fallback chain luma không chứa `Secomm/launchpad` → `@font-face` Inter + `--font-sans` của theme **không load** trên trang này → checkout đang dùng font stack Luma, không phải Inter. Đây là gap thật của style guide coverage.
- Audit font sources trên route (06-10-06): `Mageplaza_Osc::css/style.css` chỉ có icon font `luma-icons`; OSC Design generator (`design.phtml:135-141`) inject `font-family` + Google Fonts link **chỉ khi** `osc/design_configuration/text_font` truthy — DB không có row (default `0`) nên inactive; `flatpickr.min.css` toàn `inherit`. → fix = module-layer CSS như precedents BUG-2MK37V/TASK-8TXS2P.
- KHÔNG dùng route admin config `text_font` (Google Fonts CDN — trái nguyên tắc self-hosted của style guide, và là external dependency).

### AC

- **AC-1**: Toàn bộ text trên `/onestepcheckout/` (body, headings, labels, price/totals, links) render computed `font-family` bắt đầu bằng `"Inter"`; form controls (`input`, `select`, `textarea`, `button`, `.action`) cũng Inter (UA stylesheet không inherit).
- **AC-2**: Inter load **self-hosted** (woff2 từ `Launchpad_Osc::fonts/`, pattern `font-display: swap` mirror theme) — zero request tới `fonts.googleapis.com`/`fonts.gstatic.com` trên trang checkout.
- **AC-3**: Icon glyphs không vỡ — `luma-icons` (và mọi icon font vendor) giữ computed `font-family` riêng; không universal selector trong CSS mới.
- **AC-4**: Không đụng trang khác — CSS mới chỉ load qua handle `onestepcheckout_index_index`.
- **AC-5**: Regression — flow checkout không đổi: chạy lại suites TASK-8TXS2P (30/30) + BUG-ER121M (0/0) + coupon flow; console không phát sinh error mới.

### Approach (đã duyệt — ticket analysis SLP-324, user "thực thi" 06-10-06)

1. Copy 8 woff2 (400/500/600/700 × normal/italic, ~26KB each, tổng 236K) + OFL.txt từ theme → `Launchpad_Osc/view/frontend/web/fonts/inter/`.
2. CSS mới `osc-fonts.css`: 8 `@font-face` (mirror theme pattern) + font stack scoped `body.checkout-index-index` (body class do OSC layout `onestepcheckout_index_index.xml:457` set) trên body + form controls + `.action`.
3. Include qua `Launchpad_Osc/view/frontend/layout/onestepcheckout_index_index.xml` (module-level, cùng cơ chế BUG-2MK37V).
4. Deploy local: `rm` materialized `pub/static` copies (LL-0015) + `cache:flush` as secomm; verify static URL serve được woff2 + css mới.
5. Verify Playwright: computed font assertions (AC-1..3), regression suites (AC-5); evidence `.ai/evidence/TASK-NJSGKM/`.

### Out of scope (flag TL)

- Trang success (`checkout_onepage_success` — cũng load `Mageplaza_Osc::css/style.css`).
- Modal social login trên checkout (TASK-NWV2MQ đang chốt "giữ font Open Sans chờ design" — SLP-324 là design decision tương ứng, cần TL confirm mở rộng).
- OSC Design config `text_font`: nếu admin sau này bật sẽ đè ngược fix (inline `<style>` sinh sau module CSS) — ghi nhận risk, không fix trước.

## Implementation Notes

- **Env blocker (06-10-06, phát hiện lúc probe baseline)**: toàn site local HTTP 500 — `Secomm_CurrencyPrecision` schema chưa đăng ký trong `setup_module` (module WIP mới commit, `setup:upgrade` chưa chạy). `setup:upgrade` tiếp tục vấp `Secomm_Ahamove`: DB đang có **plain index** `AHAMOVE_CITY_CITY_ID` (`Non_unique=1`) trong khi `db_schema.xml` (commit `ab51a598`) khai **UNIQUE constraint** → Magento sinh `ADD UNIQUE KEY` trùng tên → SQLSTATE 1061. Root cause = drift DB local so với schema đã commit; index hiện tại là dạng cũ. **Repair 2 bước (user chọn tự chạy)**:
  1. `ALTER TABLE ahamove_city DROP INDEX AHAMOVE_CITY_CITY_ID;` (bỏ plain index cũ — upgrade sẽ tự dựng lại đúng dạng UNIQUE);
  2. `sudo -u secomm php bin/magento setup:upgrade` (đăng ký `Secomm_CurrencyPrecision` 1.0.0 + dựng UNIQUE constraint).
  Chi tiết root cause + evidence query trong `ENV-BLOCKER.md`. **Kết quả thực tế 06-10: user chạy chưa đủ** — CurrencyPrecision đăng ký xong (site hết 500) nhưng Ahamove còn nguyên plain index + chưa có row `setup_module` (residual, pre-existing, ngoài scope — cần chạy lại đúng 2 lệnh).
- **Sweep mở rộng sau census**: vendor `Mageplaza_SocialLogin/css/style.css` (sheet load trên checkout) set `.social-btn .btn-social` = Roboto (0,2,0) + `@import` Google Fonts tại file-level (`:20`) — phát hiện qua CDP initiator probe (grep source ban đầu miss do cwd đã đổi sang evidence dir). Fix: rule (0,3,1) trong osc-fonts.css; residual `@import` css request không thể khử ở CSS layer → flag TL ticket riêng.

## Verification (2026-10-06, HEAD `0b91d801`)

Chi tiết đầy đủ: `.ai/evidence/TASK-NJSGKM/RESULTS.md`.

- **AC-1 PASS**: census toàn DOM sau fix = **0 element non-Inter trong body**; 11 element đại diện compute `Inter`.
- **AC-2 PASS (1 residual vendor)**: Inter self-host từ `Launchpad_Osc::fonts/` (5 woff2 requested); gstatic Roboto woff2 hết (không còn usage); còn 2 × `fonts.googleapis.com/css?family=Roboto` = `@import` tĩnh vendor SocialLogin (initiator xác thực CDP) — flag TL.
- **AC-3 PASS**: FontAwesome ×7 intact; Luma-Icons.woff2 vẫn request (pseudo); 0 universal selector.
- **AC-4 PASS**: sheet list xác nhận css chỉ load qua handle `onestepcheckout_index_index`.
- **AC-5 PASS**: TASK-8TXS2P suite 23/30 — **A/B test (disable css → rerun) cho cùng 7 FAIL cùng giá trị** ⇒ 0 regression từ font change (4 × S6 assertion stale vs TASK-NWV2MQ, P1/P5 env payment radio); BUG-ER121M probe dTop 0/dBottom 0 PASS; console sạch (ambient "Error fetching data" pre-existing).
- Inter weight 300: `.page-title` fw=300 khớp về 400 (theme cũng chỉ ship 400–700) — parity, không phải gap; flag design nếu cần Light thật.

**Chờ**: TL review (Mode B, checkout Tier-2 formality) → QC demo (visual font trên demo, store vi; en store local block pre-existing TASK-K14RVZ). Chưa commit.
