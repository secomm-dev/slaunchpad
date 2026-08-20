---
id: DEC-TASKZ132WA-002
legacy_ids: [DEC-SL018-002]
title: 'Spec-First hardening (ticket activation contract): active ticket PHẢI có embedded Mini-Spec (Full-Spec reference của parent KHÔNG thay thế behavioral contract của slice) + plan artifact (## Approach section hoặc Plan: link resolve dưới .ai/plans/) — machine-enforced bởi --check-specs'
status: accepted
owners: [tl, sa]
decision_type: process
approval_date: 2026-08-19
created: 2026-08-19
last_verified: 2026-08-19
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-Z132WA, TASK-3R6X8E]   # primary = TASK-Z132WA (subject-owner: spec-first governance stream); trigger = TASK-3R6X8E
---

# Decision Record: Spec-First ticket activation hardening (extends DEC-TASKZ132WA-001)

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-08-19 — directed by user acting as TL (chat:
     "Audit lại cho tôi rule spec first vì sao không work, kiểm tra và apply lại cho secomm
     production ai toolkit và update ngược lại project tool này"). Naming: per-work-item
     DEC-TASKZ132WA-002 theo precedent DEC-TASKZ132WA-001/DEC-TASKE0SK0H-001 (user correction 2026-08-19 —
     process decisions có governance work-item anchor theo format per-work-item, KHÔNG global DEC-NNN).
     -->
<!-- Lịch sử tên: DEC-026 (global, pre 2026-08-19) → DEC-SL018-002 (per-work-item, 2026-08-19) →
     DEC-TASKZ132WA-002 (P2A re-identification, 2026-08-20 — DEC-027). KHÔNG nhầm với DEC-026 hiện tại
     (P2A identity model — id riêng). Precedent per-work-item: DEC-TASKZ132WA-001 / DEC-TASKE0SK0H-001. -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context (audit — vì sao gate không chặn TASK-3R6X8E)

Slaunchpad 2026-08-19: user yêu cầu "implement ticket TASK-3R6X8E". Ticket khi đó **chưa có spec
và plan riêng** (chỉ có dòng `**Specification:**` reference parent Full-Spec SPEC-FEAT-JKZM68 +
AC section) nhưng implementation vẫn tiến hành và gate không chặn. Audit 4-layer
SpecificationGuard (rule `spec-first.md` + `bin/project-ai-validate`) phát hiện 4 gap:

- **G1 — Reference-satisficing escape:** rule cho "Full Spec reference" **HOẶC** "Mini-Spec
  embedded" đều hợp lệ. Ticket-slice của feature reference parent Full Spec → gate coi như
  đã có "valid specification" dù bản thân ticket không có behavioral contract riêng. Mâu thuẫn
  với tinh thần DEC-TASKZ132WA-001 ("Ticket+AC never substitutes for specification"): AC-only thì
  không đủ, nhưng AC + parent-ref thì đủ mà không cần Mini-Spec.
- **G2 — Mini-Spec check chỉ chạy khi tồn tại:** validator chỉ validate 5 headers **NẾU**
  `'## Mini Spec' in ticket` — vắng mặt hoàn toàn thì không phát sinh sai nào (vắng mặt còn
  "an toàn" hơn có-mà-thiếu). Fixture #2 của `SPEC_FIRST_ENFORCEMENT_IMPLEMENTATION_PLAN.md`
  ("small task missing Mini-Spec → blocked") chưa được implement cho tầng ticket.
- **G3 — Plan/approach không được machine-check:** check `| Specification |` chỉ chạy trên
  file plan **tồn tại**; không gì yêu cầu ticket active phải có plan (Mode A/B) hay approach
  artifact (Mode C). Approach viết inline trong status line là "vô hình" với gate.
- **G4 — Prompt-side only tại thời điểm implement:** layer 3 do agent tự đánh giá; layer 4
  chỉ có giá trị khi được gọi (thường post-hoc). Không có bước chạy validator giữa lúc flip
  status → phát hiện muộn.

Ghi nhận thêm: TASK-YJENM2/TASK-BRKHN4 ở status "Ready" stale từ 2026-07-30 dù đã shipped trong
FEAT-AE761Z — legacy debt bị surfacing khi hardening (đã reconcile).

## Decision

1. **Ticket activation contract (mới trong `spec-first.md`)** — ticket chỉ được activate
   (`Ready|Planned|In Progress|Active`) khi thoả CẢ HAI:
   - **Embedded `## Mini Spec` đủ 5 sections** ngay trong ticket — kể cả khi ticket là slice
     của feature có Full Spec. Dòng `Specification:` tham chiếu Full Spec vẫn bắt buộc (trace)
     nhưng KHÔNG thay thế: Full Spec = behavioral contract của FEATURE; Mini-Spec = behavioral
     contract của SLICE.
   - **Plan artifact:** Mode A/B → dòng `Plan:` trỏ file `.ai/plans/*.md` tồn tại (file phải
     có `| Specification |` row); Mode C → section `## Approach` trong ticket. Approach inline
     trong status line KHÔNG tính.

2. **Validator hardening (`bin/project-ai-validate --check-specs`, ticket loop):**
   - Specification-reference check sửa từ "line rỗng mới sai" → "line vắng HOẶC rỗng đều sai".
   - Active ticket thiếu `## Mini Spec` ⇒ hard FAIL (non-active: im lặng — backlog Proposed
     không bị nhiễu).
   - Active ticket thiếu plan artifact (`## Approach` section hoặc `plans/*.md` link resolve)
     ⇒ hard FAIL.

3. **Áp dụng toolkit-first:** sửa canonical (`shared-core/rules/spec-first.md` +
   `bin/project-ai-validate` + contract tests Test Q mới cho tầng ticket) → chạy
   `run-contract-tests` (**62/62 PASS**, +4 test: parent-ref-only blocked / no-plan-artifact
   blocked / mini+approach allowed / mini+plan-link allowed) → backport identical-propagation
   về slaunchpad (`.ai/rules/spec-first.md` + `.ai/bin/project-ai-validate`).

4. **Migration:** không bulk-rewrite historic tickets (theo migration strategy sẵn có của
   toolkit). Legacy debt surfacing khi activate — đúng workflow "Legacy tickets". Slaunchpad:
   reconcile 2 ticket stale (TASK-YJENM2/009 "Ready" → "Dev complete" kèm byline, đã shipped trong
   FEAT-AE761Z). TASK-33J3RP..TASK-HPK1WZ đang Proposed — sẽ cần Mini-Spec + Plan/Approach khi activate.

## Alternatives considered

- **Hooks chặn file-write tại thời điểm code** — mạnh nhất nhưng 重 hạ tầng (hook per-tool,
  false positive với analysis-only reads); validator hardening + prompt rule là bước đúng
  tỷ lệ chi phí/lợi ích hiện tại. Hook để sau nếu thiếu sót tiếp diễn.
- **Yêu cầu plan file cho mọi mode (kể cả C)** — tăng document volume vi phạm nguyên tắc
  "Không over-document" của chính rule; `## Approach` section là artifact tối thiểu reviewable.
- **WARN-only rollout như migration strategy gốc** — case TASK-3R6X8E chứng minh satisficing
  escape phải đóng bằng hard block ngay ở tầng active ticket; WARN-only giữ cho non-active.

## Consequences

- Mọi ticket activate từ nay phải có Mini-Spec + plan artifact — gồm cả ticket do AI draft
  (spec/plan skill phải sinh cấu trúc này) và ticket người viết.
- Agent nhận chỉ thị trực tiếp ("implement X") không được flip status active trước khi đủ
  artifact — phải từ chối với IMPLEMENTATION BLOCKED và bổ sung spec/plan trước.
- Toolkit + generated projects đồng bộ qua identical-propagation (2 file); các project khác
  nhận rule khi chạy `project-ai-upgrade` lần sau.

## References

- Trigger audit: [TASK-3R6X8E](../../tickets/TASK-3R6X8E-scaffold-promotion-modules.md) (+ Mini-Spec/plan bổ sung post-hoc 2026-08-19)
- Extends: [DEC-TASKZ132WA-001](DEC-TASKZ132WA-001.md) (giữ nguyên, không supersede — hardening thêm tầng ticket)
- Toolkit change: `shared-core/rules/spec-first.md` §Ticket activation contract · `bin/project-ai-validate` SpecReadinessGuard · `bin/run-contract-tests` Test Q (ticket tier)
- Evidence: `run-contract-tests` 62/62 PASS (2026-08-19, toolkit @ /var/www/html/secomm-production-ai-toolkit) · slaunchpad `--check-specs --check-records` 0 FAIL/0 WARN post-backport
