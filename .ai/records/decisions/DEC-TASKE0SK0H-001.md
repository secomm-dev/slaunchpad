---
id: DEC-TASKE0SK0H-001
legacy_ids: [DEC-SL019-001]
title: 'Spec naming canonical — SPEC-<OWNER-ID>-<slug>.md (owner = FEATURE-ID hoặc TICKET-ID; Specification ID = SPEC-<OWNER-ID>, slug never identity); Mini-Spec identity = Ticket ID (MINI-{NNN} deprecated); one canonical rule in shared-core/rules/spec-first.md §Spec Naming propagated via generation + upgrade; validator filename↔ID consistency; legacy slug-only specs grandfathered (no bulk rename)'
status: accepted
owners: [sa, tl]
decision_type: process
approval_date: 2026-08-18
created: 2026-08-18
last_verified: 2026-08-18
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-E0SK0H]
---

# Decision Record: Spec Naming canonical + propagation

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-08-18 — naming proposal được SA/TL duyệt trong chat (proposal-only turn trước) rồi yêu cầu APPLY; canonical rule text = verbatim từ directive. -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->

## Context

Spec files hiện dùng slug-only naming (12 file trong slaunchpad) — nhìn tên không biết owner. Templates chứa placeholder identity system riêng (`SPEC-{NNN}` global, `MINI-{NNN}`) — chính là loại generic identity §5 của directive cấm. Cần một naming convention reuse 100% Feature/Ticket ID hiện có, canonical ở toolkit, tự propagate.

## Decision (accepted)

1. **Naming** — Full Spec thuộc Feature: `SPEC-<FEATURE-ID>-<slug>.md` (tối đa 1 canonical spec/feature); thuộc standalone ticket: `SPEC-<TICKET-ID>-<slug>.md`. **Specification ID = `SPEC-<OWNER-ID>`** — stable, độc lập slug (đổi slug không đổi ID). Machine regex: `^SPEC-(FEAT|BUG|REL|SL|TASK)-[0-9]{3,}-[a-z0-9]+(-[a-z0-9]+)*\.md$`.
2. **Mini-Spec** — embedded trong ticket, Specification ID = Ticket ID; KHÔNG file riêng, KHÔNG ID riêng; `MINI-{NNN}` deprecated khỏi template.
3. **Metadata header** Full Spec: `Specification ID` + `Feature ID (hoặc NONE)` + `Specification Level` khớp filename.
4. **Canonical propagation (một rule, không duplicate):** rule sống trong `shared-core/rules/spec-first.md` §Spec Naming (đã trong upgrade manifest `.ai/rules/spec-first.md` + generator references). Producers (spec skill, create-feature-spec, feature-spec/mini-spec templates) chỉ implement/pointer về canonical. AGENTS.base một câu pointer; output-file-rules pointer note. Codex/Copilot overlays đã delegate tới AGENTS/spec-first — không cần đổi.
5. **Validator:** `--check-specs` thêm filename↔header-ID consistency (mismatch → FAIL) — chỉ áp cho file có prefix `SPEC-<OWNER>-`; **legacy slug-only grandfathered**; KHÔNG bulk rename (rename-on-touch qua safe workflow).
6. **Không đổi** folder structure / workflow states / ticket-plan lifecycle / Spec-First enforcement behavior / artifact ownership.

## Alternatives considered

- `SPEC-{NNN}` global sequence (template cũ) — rejected: identity system thứ hai, generic, không trace owner.
- Rule riêng `artifact-naming.md` — rejected: competing rule; spec-first.md đã là canonical spec policy được propagate + validator-checked.
- Bulk rename 12 legacy specs — rejected (directive §8): grandfather + rename-on-touch.
- Copy spec naming spec dài vào từng generated file (AGENTS/CLAUDE/codex/commands) — rejected: duplicate-maintained rules; pointer pattern.

## Consequences

- (+) Nhìn filename biết ngay owner; cùng grammar `{KIND}-{OWNER}` với DEC-{CODE}-NNN (Entry h) — một quy luật toàn repo.
- (+) Fresh + existing projects nhận rule tự động (generation + upgrade); machine-checked.
- (−) Placeholder templates đổi → các project đang giữ template cũ cần upgrade để nhận (normal path).
- (−) 4 spec mới tạo hôm qua (TASK-NDASAD/016/017/002 backfill) vẫn slug-only — sẽ rename-on-touch khi spec được activate/sửa (không làm trong task này).

## Affected components

- Toolkit: `shared-core/rules/spec-first.md` (+§Spec Naming) · `templates/{feature-spec,mini-spec}-template.md` · `skills-source/spec/SKILL.md` · `shared-core/functions/create-feature-spec.md` · `generator-rules/output-file-rules.md` (pointer) · `stack-templates/_base/AGENTS.base.md` (một câu) · `bin/project-ai-validate` (naming check) · `bin/run-contract-tests` (R-series) · CHANGELOG entry (j).
- Project: nhận qua `project-ai-upgrade --apply` (rule + templates + validator).
