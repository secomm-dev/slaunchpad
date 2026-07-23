# record-risk

> Function (VI guidance). Copy vào `.ai/functions/record-risk.md`.

## Mục đích
Add một risk vào `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md` (single source — KHÔNG tạo `KNOWN_RISKS.md` data store riêng).

## Trigger
- Command: `/record-risk`
- Khi: risk mới phát hiện hoặc resolved.

## Required inputs
- Risk (description), area, severity, owner, mitigation

## Required project files to read
- `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md` (tránh duplicate)

## Dependencies
- Agent: tl (own 06)
- Skill: `update-memory` (diff)
- Rule: `memory-update.md`, `no-duplicate-knowledge.md`

## Execution steps
1. Check `06` (risk đã có chưa).
2. Draft risk entry (risk/area/severity/owner/mitigation).
3. Generate diff cho `06` (human review trước commit).
4. Nếu resolved → mark resolved (không xóa).

## Expected output
Risk diff cho `project-context/06` (human review + commit).

## Evidence required
`06` diff commit.

## Memory files to update
- `project-context/06` (primary; single source)
- `KNOWN_RISKS.md` (v4 pointer — không data)

## Failure handling
- Risk là S0 (production/data/security) → escalate Tier 2 ngay, không chỉ record.
- Duplicate risk → merge, không tạo entry thứ hai.

## When to improve/update
- Khi risk category miss → update `06` structure; record.
