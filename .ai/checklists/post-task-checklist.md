# Post-Task Checklist

Hook "wrap up a task" cho developer (trước khi request review). Confirm work đã hoàn thành, đã review bởi AI, và context đã capture. Pair với [definition-of-done.md](../core/definition-of-done.md).

## Build & Tests

- [ ] Code theo implementation plan (không có out-of-scope changes)
- [ ] Existing tests pass
- [ ] New tests viết cho logic mới (nếu có framework); bug fixes bao gồm reproducing test
- [ ] Local build/lint clean **trên affected scope** (Mode A/B: full; Mode C/D: touched files only)

## AI Pre-review

- [ ] Đã chạy [review-code](../skills-source/review-code/SKILL.md) — không có Critical findings (hoặc resolved)
- [ ] Nếu security-sensitive: đã chạy [security-review](../skills-source/security-review/SKILL.md)
- [ ] Regression risks đã identify và note cho PR

## Business Rules & Context

- [ ] Business rules trong project-context/02_BUSINESS_RULES.md được respect
- [ ] High-risk area check đã thực hiện so với AGENTS.md Section 12

## Context & Memory

- [ ] Nếu business rules/architecture/modules thay đổi: [update-memory](../skills-source/update-memory/SKILL.md) diff đã generate cho TL review
- [ ] Nếu session dài/đa bước: đã chạy [compact-context](../skills-source/compact-context/SKILL.md) — `CURRENT_STATE.md` / `NEXT_TASK.md` current
- [ ] Bất kỳ decision nào → append vào `DECISIONS.md`
- [ ] Actual effort đã log trong estimation-tracking.csv

## Ready for Review

- [ ] PR đã tạo với PR template (AI pre-review summary included)
- [ ] Tất cả above pass → request TL review
