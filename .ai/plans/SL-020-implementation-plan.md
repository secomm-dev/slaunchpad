# Implementation Plan: SL-020 — Distill Pancake POS contracts vào project-context

| Field | Value |
|---|---|
| Specification | Embedded Mini-Spec — SL-020 |
| Author | AI draft |
| Reviewer (TL) | approved — user acting as TL, 2026-09-07 |
| Workflow Mode | C (approach note) |
| Date | 2026-08-19 |

> **Status:** Plan approved 2026-09-07 (Gate 3 pass — user acting as TL). Delivered; chờ human commit. Mini-Spec + AC xem [ticket SL-020](../tickets/SL-020-distill-pancake-pos-contracts.md). Canonical record: [FEAT-008](../records/features/FEAT-008.md).

## 1. Approach

Chiết xuất theo chiều "một chỗ canonical" (no-duplicate-knowledge): **05 sở hữu contract**, 03 chỉ đăng ký integration, research/checklist chỉ giữ nguồn + gaps cho SA. Nguồn duy nhất = `.ai/research/api-1.json` (POS API; Chat API out-of-scope). Không chọn phương án copy contract ra cả 4 file vì sinh drift khi Pancake đổi API.

> Plan derives behavior from **Specification** above. Conflict ⇒ quay lại spec, không silently đổi.

## 2. Files affected

| File | Change type | Lý do |
|------|-------------|-------|
| `.ai/project-context/05_API_CONTRACTS.md` | modify (append section Pancake POS) | AC-1 — canonical contract |
| `.ai/project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md` | modify (đăng ký integration) | AC-2 |
| `.ai/research/RESEARCH_NOTES.md` | modify (append mục Pancake) | AC-3 |
| `.ai/research/vendor-doc-checklist.md` | modify (append mục Pancake + gaps) | AC-3 |

## 3. Steps (độc lập reviewable)

1. Đọc `api-1.json`, liệt kê endpoint + field dùng được — risk: low — deps: none
   - verify: checklist 4 endpoint + tracking fields khớp epic plan §1
2. Viết section Pancake POS vào 05 (schema theo integration Mollie/VNPAY hiện có) — risk: low — deps: 1
   - verify: AC-1 từng mục; placeholder `<api_key>` thay secret
3. Đăng ký integration vào 03 (type/direction/criticality/auth — không lặp endpoint) — risk: low — deps: 2
   - verify: AC-2; không trùng lặp nội dung 05
4. Append mục Pancake vào RESEARCH_NOTES + vendor-doc-checklist: nguồn, ngày 2026-08-19, gaps map Q1–Q5 epic — risk: low — deps: 2
   - verify: AC-3; gaps đủ 4 nhóm (webhook vs poll / status enum / increment_id field / VN address ids)
5. Self-review AC-4 (git diff chỉ 4 file) + đề xuất diff cho human review — risk: low — deps: 3, 4
   - verify: AC-4; `git status` confirm scope

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Duplicate contract nhiều file → drift | medium | Chỉ 05 giữ contract; file khác link |
| Lỡ sửa toolkit/generated file | low | AC-4 chặn bằng git diff review |
| Leak api_key thật | low | Chỉ placeholder; không paste giá trị từ env |

## 5. Test approach

- Unit: n/a (docs)
- QC: review AC-1..4; Mode C — TL spot-check diff
- High-risk validation: n/a (không chạm payment/checkout/auth/secret/DB)

## 6. Out of scope

Theo Mini-Spec SL-020: không implement module, không Chat API, không chốt Q1–Q5, không sửa AGENTS.md §6.

## 7. Open questions / Escalation

- Q1 (TL): cập nhật bảng integration AGENTS.md §6 ngay hay chờ regeneration?
- Q2 (SA): section 05 theo schema integration nào (Mollie/VNPAY) nếu có format chuẩn riêng?

## 8. Verification

- `bash .ai/bin/project-ai-validate --check-specs --check-records` → VALID sau khi ticket/plan/record tạo (đã chạy 2026-08-19: 0 FAIL / 0 WARN).
- Sau distill: self-review AC-1..4 + git diff scope (AC-4) → evidence diff cho TL.
