# Post-Mortem: {INC-ID}

> Bắt buộc cho severity S0/S1 incidents. Blameless — tập trung vào hệ thống/process, không đổ lỗi cho cá nhân. Tham chiếu `incident-report-template.md`.

## Metadata

| Field | Value |
|-------|-------|
| Incident | INC-{NNN} |
| Severity | S0 / S1 |
| Date of post-mortem | |
| Facilitator | |
| Attendees | |

## 1. Summary

{1–2 câu: chuyện gì xảy ra, tác động, đã được resolve khi nào.}

## 2. Timeline

| Time | Event |
|------|-------|
| {ISO8601} | {event} |

## 3. 5 Whys

1. Why did {symptom} happen? → {answer}
2. Why {answer 1}? → {answer}
3. Why {answer 2}? → {answer}
4. Why {answer 3}? → {answer}
5. Why {answer 4}? → {root cause}

## 4. Root cause(s)

{Gốc rễ hệ thống/process — không phải "lỗi con người".}

## 5. What went well / badly / lucky

- Went well: {detect nhanh, rollback sạch, ...}
- Went badly: {alert trễ, runbook thiếu, ...}
- Lucky: {may mắn避免了 worse impact}

## 6. Action items

| # | Action | Type (prevent / detect / mitigate) | Owner | Due | Tracking |
|---|--------|------------------------------------|-------|-----|----------|
| 1 | {alert / test / guard / runbook / config} | | {role} | {date} | {ticket} |

## 7. Lessons

- → `LESSONS_LEARNED` (retro) / risk → `project-context/06`
