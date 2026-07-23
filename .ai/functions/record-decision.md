# record-decision

> Function (VI guidance). Copy vào `.ai/functions/record-decision.md`.

## Mục đích
Append một Architecture Decision Record (ADR) vào `DECISIONS.md` — context/decision/rationale/consequence/status. Ngăn re-litigate decision đã chốt.

## Trigger
- Command: `/record-decision`
- Khi: một architecture/business/tech decision được make.

## Required inputs
- Decision (what), context (why), rationale (why this option), consequence

## Required project files to read
- `DECISIONS.md` (next ADR number; tránh duplicate)
- `templates/decisions-template.md`

## Dependencies
- Agent: sa, tl (approve decision)
- Rule: `memory-update.md`, `no-duplicate-knowledge.md`
- Instinct: #10

## Execution steps
1. Check `DECISIONS.md` (decision đã có chưa; next ADR-NNNN).
2. Draft ADR per template (context/decision/rationale/consequence/status/date/deciders).
3. Append (APPEND-ONLY — không xóa entry cũ; supersede bằng entry mới link cũ).
4. SA/TL approve.

## Expected output
ADR entry append vào `DECISIONS.md`.

## Evidence required
ADR entry + (nếu significant) SA/TL approval note.

## Memory files to update
- `DECISIONS.md` (append — primary)

## Failure handling
- Decision contradict entry cũ → không overwrite; thêm entry mới supersede (link cũ).
- Decision chưa approve → status: Proposed.

## When to improve/update
- Khi ADR field miss recurrent → update template; record.
