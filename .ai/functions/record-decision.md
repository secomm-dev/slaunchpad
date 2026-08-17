# record-decision

> Function (VI guidance). Copy vào `.ai/functions/record-decision.md`.
> **Phase 1b:** invoked by `assess-decisions` for every Mode A work item; `proposed` status is the expected output of an assessment pending SA/TL acceptance (→ `accepted` / `superseded`).

## Mục đích
Ghi một decision bền vững: canonical `.ai/records/decisions/DEC-{CODE}-{NNN}.md` (full body — naming Entry h: per-work-item namespace) + một dòng index trong `memory/DECISIONS.md` (compatibility pointer; Navigator `adr_ref` vẫn resolve). Ngăn re-litigate decision đã chốt + chống trùng tên file khi nhiều staff làm song song trên nhiều ticket.

## Trigger
- Command: `/record-decision`
- Khi: một architecture/business/tech decision được make.

## Required inputs
- Decision (what), context (why), rationale (why this option), consequence
- **work_items** (≥1 work-item ID: `FEAT-`/`SL-`/`BUG-`/`REL-`) — bắt buộc khi reserve, là anchor gắn decision vào work-item (chống re-open cùng chủ đề dưới DEC-ID khác khi nhiều staff làm song song). `work_items: []` chỉ hợp lệ khi `decision_type ∈ {process, tooling, governance}` (decision dạng quy trình/tooling không có work-item).

## Required project files to read
- `DECISIONS.md` (next DEC/ADR number; tránh duplicate) + `.ai/records/decisions/` (canonical store)
- `records/features/`, `records/bugs/`, `records/releases/`, legacy `tickets/` — để resolve/confirm các ID trong `work_items` (link, KHÔNG restate)
- `templates/decision-record-template.md` (canonical) + `templates/decisions-template.md` (DECISIONS.md skeleton)

## Dependencies
- Agent: sa, tl (approve decision)
- Rule: `memory-update.md`, `no-duplicate-knowledge.md`
- Instinct: #10

## Execution steps
1. **Reserve DEC-ID (chống race khi nhiều staff làm song song):**
   - **Naming per-work-item (Entry h, 2026-08-17):** DEC-ID = `DEC-{CODE}-{NNN}` với `{CODE}` = primary work-item ID (entry đầu tiên của `work_items`) bỏ gạch nối, uppercase (`SL-015`→`SL015`, `FEAT-006`→`FEAT006`); `{NNN}` = suffix 3 chữ số.
   - Scan `.ai/records/decisions/DEC-{CODE}-*.md` (namespace của primary work-item), parse suffix, tính `next = max + 1` (append-only — KHÔNG tái dùng gap của DEC superseded/deleted). Hai staff trên hai ticket khác nhau không bao giờ đụng namespace → không còn trùng tên file toàn project.
   - **Exempt path:** decision_type ∈ {process, tooling, governance} (`work_items: []`) → giữ numbering legacy global: scan `DEC-<số>.md`, `next = max + 1`, file `DEC-{next}.md`.
   - Tạo ngay file chỉ chứa frontmatter (`id, title, status: draft, owners, decision_type, work_items: [...], created`). Sự tồn tại của file trên disk là lock; `work_items` là input bắt buộc lúc reserve. Nếu 2 agent race cùng namespace, agent thứ hai thấy placeholder (max đã tăng) → lấy số kế tiếp.
   - Confirm decision chưa tồn tại: check subject trùng trong `DECISIONS.md` + overlap `work_items` với DEC đã có (tránh re-litigate cùng chủ đề).
   - **Legacy:** các file `DEC-NNN.md` tạo trước 2026-08-17 giữ nguyên tên (append-only, KHÔNG rename) — validator chấp nhận cả hai format.
2. Draft canonical decision record per `templates/decision-record-template.md` (context/decision/alternatives/consequences/affected components/related records) → lưu body vào cùng file đã reserve. Frontmatter `work_items` là canonical (validator parse); entry đầu tiên phải khớp prefix tên file; body "Related records" chỉ thêm narrative, KHÔNG được contradict `work_items`.
3. Append **một dòng index** vào `memory/DECISIONS.md` (title + link → `records/decisions/DEC-XXX.md`); giữ anchor `ADR-XXXX` để Navigator `adr_ref` resolve. APPEND-ONLY — không xóa entry cũ; supersede bằng entry mới link cũ.
4. SA/TL approve.

## Expected output
Canonical `.ai/records/decisions/DEC-{CODE}-{NNN}.md` + một dòng index trong `memory/DECISIONS.md` (trỏ tới canonical file).

## Evidence required
Canonical DEC-{CODE}-{NNN}.md + index dòng trong DECISIONS.md + (nếu significant) SA/TL approval note.

## Memory files to update
- `.ai/records/decisions/DEC-{CODE}-{NNN}.md` (canonical store — primary)
- `DECISIONS.md` (append index dòng — compat pointer; dòng index nên mang `(status; work_items: …)`)
- **`project-state.yaml` `decisions[]`** — khi decision vào open queue (status `proposed`), entry phải mang `work_items` để `DECISION_QUEUE` render (`**Work items:**`).
- **Parent feature/bug record `decision_approval_summary` (B1)** — after creating OR changing any DEC status, recompute the summary from the DEC statuses (single source = `.ai/records/decisions/*.md` `status:`). Bucket map: `proposed→pending_approval`, `accepted→approved`, `rejected→rejected`, `superseded→superseded`; set `total`, `last_synced`, `verified_against_commit`. This is what makes approval-state a single-read (no per-DEC open).

## Failure handling
- Decision contradict entry cũ → không overwrite; thêm entry mới supersede (link cũ).
- Decision chưa approve → status: Proposed.

## When to improve/update
- Khi ADR field miss recurrent → update template; record.
