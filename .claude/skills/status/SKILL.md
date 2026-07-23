# Summarize Project

## Purpose

Produce a shareable, accurate project status from the memory files + `project-context/` — for standups, client updates (draft only, human-reviewed), release readiness, or handoff. Distills the project's current truth into a concise, audience-appropriate summary.

## When to Use

- Standup / status reporting
- Pre-release readiness check
- Handoff between team members or to support/maintenance
- Monthly project health review
- Drafting a client status update (output is a **draft** — human reviews before sending)

## Prerequisites

- `AGENTS.md` — workflow mode, timeline
- `project-context/01_PROJECT_OVERVIEW.md` (objective, timeline, team)
- `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md`
- Memory files: `CURRENT_STATE.md`, `NEXT_TASK.md`, `DECISIONS.md`, `LESSONS_LEARNED.md`
- `estimation-tracking.csv` (if effort status is needed)

## Input

The audience (internal team / TL / PM / client) and the time window or scope to summarize. State whether output is for internal use or a client-facing draft.

## Steps

1. Read the standing context (`01_PROJECT_OVERVIEW.md`) and the memory files.
2. Pull effort status from `estimation-tracking.csv` if relevant.
3. Compose the summary at the right altitude for the audience:
   - **Internal**: includes risks, blockers, decisions, estimation delta, next tasks
   - **Client-facing draft**: outcomes, what's live, what's next, any decisions needing client input — **no internal-only risk/estimation detail unless the TL approves**
4. Flag anything uncertain or unverified rather than stating it as fact.
5. For client-facing output, mark it clearly as a **draft requiring PM/TL review** before sending (Hard Gate — see [no-ai-blind-trust.md](../../core/no-ai-blind-trust.md)).
6. Exclude secrets, PII, and other clients' information (see [production-ai-security.md](../../core/production-ai-security.md) §5).

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## Project Status: {project} — {date} — {audience}

### Objective
{1 line from 01_PROJECT_OVERVIEW.md}

### What's done / live
- {delivered item}

### In progress
- {task} — {status}

### Next
- {next milestone/task}

### Risks / blockers (internal only — omit for client unless approved)
- {risk} — owner: {name}

### Estimation snapshot (internal only)
- {tracked vs actual delta, if relevant}

### Decisions needing input
- {decision} — needs: {who}
```

## Quality Checklist

- [ ] Every claim traceable to a memory or `project-context/` file (no invented status)
- [ ] Audience-appropriate: client drafts exclude internal-only detail
- [ ] Uncertainties flagged, not stated as fact
- [ ] No secrets / PII / cross-client data
- [ ] Client-facing output marked as draft for PM/TL review

## Escalation Rules

- Client-facing draft must be reviewed by PM/TL before sending (Hard Gate — never send AI output to a client unreviewed)
- If status reveals a slipped timeline or new high-risk area → flag to TL/PM before circulating

## Example

**Input**: Summarize for internal TL standup; window = this week.

**Output**:
```markdown
## Project Status: Acme Store — 2026-07-07 — Internal TL

### Objective
Magento Hyvä B2B pricing + checkout hardening (Mode B).

### What's done / live
- B2B tiered pricing (Gold/Silver/Bronze) — staging verified

### In progress
- BUG-1234 coupon + tier regression test — pending test run

### Next
- Release tiered pricing to production (needs release checklist + rollback plan)

### Risks / blockers (internal)
- Category page price display may be stale under full-page cache — owner: dev

### Estimation snapshot
- BUG-1234: estimated 4–8h, actual 6h (within range)

### Decisions needing input
- Whether to ship pricing behind a customer-group flag or to all B2B at once — needs: TL
```
