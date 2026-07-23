# Nav — Show Evidence

## Purpose

The invokable form of the **Show Evidence** navigator intent. Resolves a decision/state to its supporting evidence and renders it. Thin adapter over [`shared-core/navigator/NAVIGATOR_ENGINE.md`](../../shared-core/navigator/NAVIGATOR_ENGINE.md) (Show Evidence) + the Evidence policy ([`shared-core/evidence/evidence-policy.md`](../../shared-core/evidence/evidence-policy.md)).

## When to Use

- The user says: "cho xem bằng chứng" / "có chứng cứ không" / "tại sao recommend vậy" / "show evidence" — or invokes `/evidence [DEC-ID]`.

## Prerequisites

- `.ai/runtime/project-state.yaml` (to resolve DEC-ID → `evidence_refs`)
- Evidence store / referenced files under `.ai/`

## Input

Optional DEC-ID. Absent → use the top open decision (and say which one you used).

## Steps

1. Resolve target: given DEC-ID, else top open decision in `project-state.yaml`.
2. Read its `evidence_refs` (+ option `evidence_ref` if an option is recommended).
3. Render each evidence item: file + section + the one-line claim it supports.
4. If no evidence recorded → say so explicitly (do not invent).
5. No state mutation.

## Output Format

> **Output language:** project `output_language` (default English); technical terms stay English.

```markdown
### Evidence for {DEC-ID} — {title}
- {evidence file} § {section} — supports: {claim}
- ...
*No evidence recorded yet* (only if true)
```

## Quality Checklist

- [ ] Every cited evidence file exists
- [ ] Each item links to a file section, not a bare filename
- [ ] Missing evidence stated explicitly, never fabricated
- [ ] No state mutation

## Escalation

- If a cited evidence file is missing → report the broken reference (this is a validator-relevant defect).

## Example

**Input:** "tại sao recommend vậy" → `/evidence DEC-001`
**Output:** the evidence list for DEC-001.
