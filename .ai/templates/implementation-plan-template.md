# Implementation Plan: {Tên task/feature}

> Dùng cho Mode A/B. **Plan only — chưa viết code** (Hard Gate 3: No Code Without Plan). Output của `/plan` / Planning-First Sequence.

## Metadata

| Field | Value |
|-------|-------|
| Ticket / Spec | {TICKET-ID / SPEC-ID — P2A: TASK-XXXXXX… hoặc legacy SL-NNN; SPEC-TASK-XXXXXX-… hoặc SPEC-SL-NNN-…} |
| Specification | {REQUIRED: Full Spec path/section or `Embedded Mini-Spec — TICKET-ID`} |
| Author | |
| Reviewer (TL) | |
| Workflow Mode | A / B |
| Date | |

## 1. Approach

{Mô tả cách tiếp cận — vì sao chọn hướng này, các lựa chọn đã xét (nếu có), ADR liên quan.}

> Plan derives behavior from **Specification** above. If implementation analysis
> conflicts with it, stop and return to the specification stage; do not silently
> redefine requirements here.

## 2. Files affected

| File | Change type | Lý do |
|------|-------------|-------|
| {path/to/file} | new / modify / delete | {mapping tới AC nào} |
| ... | ... | ... |

## 3. Steps (độc lập reviewable, theo thứ tự)

1. {Step} — risk: {high/medium/low} — deps: {step#}
   - {thay đổi cụ thể, không chung chung}
   - verify: {cách kiểm tra step này}
2. ...

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| {vùng bị ảnh hưởng: checkout/payment/cache/...} | high/medium/low | {cách giảm thiểu + test} |

## 5. Test approach

- Unit: {scope}
- Integration / QC: {scope} — xem `testcase`
- High-risk validation (L3) nếu chạm payment/checkout/auth/secret/DB: {scope}

## 6. Out of scope

{Những gì KHÔNG làm trong task này — chống scope creep.}

## 7. Open questions / Escalation

- {câu hỏi cần TL/CTO — tier 1/2}
