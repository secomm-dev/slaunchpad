# Incident Report: {INC-ID — tiêu đề ngắn}

> Bắt buộc cho MỌI severity production incident (Mode D / `incident-workflow`). Dùng kèm `incident-analysis` skill.

## 1. Incident info

| Field | Value |
|-------|-------|
| Incident ID | INC-{NNN} |
| Severity | S0 / S1 / S2 / S3 |
| Detected at | {ISO8601} |
| Detected by | {monitor / user / on-call} |
| Affected | {users % / region / feature} |
| Owner | {role} |

## 2. Timeline (UTC + local)

| Time | Event |
|------|-------|
| {ISO8601} | {detect / confirm / mitigate / resolve} |

## 3. Impact

{Business impact: đơn hàng/giỏ hàng bị ảnh hưởng, doanh thu ước tính, dữ liệu, SLA.}

## 4. Root cause

{Technical root cause — dựa trên evidence, không đoán. 5 Whys nếu cần (xem post-mortem cho S0/S1).}

## 5. Fix / Mitigation

- Immediate mitigation: {hành động dừng chảy máu}
- Permanent fix: {PR / change + file:line}
- Rollback: {có/không + lý do}

## 6. Evidence

- {log / metric screenshot / query / deploy diff}

## 7. Prevention

| Action | Owner | Due |
|--------|-------|-----|
| {alert / test / runbook / guard} | {role} | {date} |

## 8. Follow-up

- Post-mortem required? {yes cho S0/S1 → `post-mortem-template.md`}
- Lesson → `LESSONS_LEARNED` / risk → `project-context/06`
