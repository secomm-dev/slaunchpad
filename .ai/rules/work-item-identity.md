# Work-Item Identity Model — canonical naming & identity rules (P2A, 2026-08-20)

> **INVARIANT — bốn lớp phải tách rời, không được trộn lẫn:**
> (1) canonical identity của work item; (2) project namespace (Project Code);
> (3) quan hệ Feature → ticket (mutable metadata); (4) display name (derived,
> render lại được). Canonical ID không phụ thuộc Project Code, project name,
> parent, hay title — và không bao giờ được cấp phát bằng quét `max + 1`.

This file is the **single source of truth** cho identity model. Validator
(`bin/project-ai-validate --check-identity`), templates, functions, skills,
generator-rules chỉ **reference** file này — không restatement
(no-duplicate-knowledge).

---

## 1. Project Code — project namespace

**SSOT:** `.ai/toolkit/project.yaml` (machine-readable; created at initialization; engine chỉ ADD khi thiếu, không bao giờ UPDATE).

```yaml
schema_version: 1
project:
  name: Secomm Launchpad        # full human-readable name
  code: SLP                     # short stable identifier
code_history: []                # audit trail các lần đổi code
```

- Regex: `^[A-Z][A-Z0-9]{1,7}$` (2–8 ký tự, uppercase A-Z + số, không ký tự khác).
- Được hỏi/xác nhận **một lần lúc initialization** (gợi ý từ tên project — lấy ký tự
  đầu của các từ chính, ≤8; human xác nhận hoặc sửa). Sau khi persist → authoritative.
- **KHÔNG được re-infer** từ folder name / repo name / workspace / project name sau init.
- **Đổi Project Code** (workflow, không cần command mới):
  1. sửa `project.code` trong `.ai/toolkit/project.yaml` (append entry vào `code_history`);
  2. chạy `bin/project-ai-validate --check-identity` — audit mọi display title stale;
  3. re-render display title (H1) của các record new-format cho khớp (LLM follow-up);
  4. **KHÔNG** regenerate bất kỳ `FEAT-*/TASK-*/BUG-*/SPIKE-*/REL-*` ID nào;
  5. KHÔNG tự ý rewrite external PM IDs.
- Project Code **không phải** canonical work-item identity — nó chỉ là namespace
  hiển thị trong display title.

## 2. Canonical Work-Item ID

Format: `<TYPE>-<SUFFIX>` — ví dụ `TASK-4P8DX2`, `FEAT-7K3M9Q`.

| Thuộc tính | Giá trị |
|---|---|
| TYPE prefix | `FEAT \| TASK \| BUG \| SPIKE \| REL` |
| Alphabet suffix | Crockford Base32: `0123456789ABCDEFGHJKMNPQRSTVWXYZ` (loại I/L/O/U — ký tự dễ nhầm) |
| Độ dài suffix | 6 ký tự (32⁶ ≈ 1.07 tỷ tổ hợp) |
| Validation regex | `^(FEAT\|TASK\|BUG\|SPIKE\|REL)-[0-9ABCDEFGHJKMNPQRSTVWXYZ]{6}$` |
| Normalization | uppercase; input thường được upper trước khi so khớp |
| Nguồn sinh | `bin/project-ai-idgen <TYPE> [--project <dir>]` — 4 bytes `/dev/urandom` → base32 (2³² chia hết 2³⁰ ⇒ uniform, không bias) |

**Collision handling (3 lớp, không cần centralized allocation):**
1. In-invocation dedupe (`--count N` luôn trả N id distinct);
2. `--project` existence-check: suffix đã dùng trong `.ai/{records,tickets,specs,plans}` → re-mint (≤10 lần). Đây là *existence check*, không phải allocation;
3. Merge-time backstop: `--check-identity` FAIL khi phát hiện duplicate canonical ID trong một project → item mới hơn re-mint id mới + cập nhật references (ID được mint TRƯỚC khi tạo reference nên chi phí thấp).

**CẤM:** mọi workflow cấp phát canonical Feature/Task/Bug/Spike/Release ID bằng
`scan existing → max + 1` (hoặc counter tuần tự cục bộ kiểu `FEAT-001`) —
multi-agent/multi-branch sẽ đụng số. (Ngoại lệ có kiểm soát: per-work-item DEC
suffix `DEC-{CODE}-NNN` vẫn max+1 trong namespace của một work item + disk-lock —
xem §8.)

**ID stability:** ID không đổi khi project code đổi, project name đổi, re-parent
giữa Features, hay title đổi.

**Legacy format (pre-P2A, grandfathered):** `<ABBR>-<NNN+>` (vd `SL-019`,
`FEAT-006`) vẫn hợp lệ cho các item tạo TRƯỚC migration P2A — regex legacy:
`^[A-Z][A-Z0-9]{1,7}-[0-9]{3,}$`. Validator chấp nhận cả hai format; item mới
bắt buộc format mới.

## 3. Work-Item Types

```
Work Item
├── Feature  (FEAT-)  — capability; có thể chứa nhiều work item con
├── Task     (TASK-)  — implementation/delivery work; con của Feature HOẶC standalone
├── Bug      (BUG-)   — defect/fix work; con của Feature HOẶC standalone
├── Spike    (SPIKE-) — research/investigation; con của Feature HOẶC standalone
└── Release  (REL-)   — release/deployment-oriented
```

- **Feature = capability** (business/product/technical), KHÔNG định nghĩa là
  "Epic của tool X". Mapping sang PM tool là việc của adapter:
  `Feature → XCorp Epic / Jira Epic; Task → XCorp Ticket / Jira Story; Bug → Jira Bug`.
- Canonical homes (Phase 1a records model): `.ai/records/features/FEAT-*.md`,
  `.ai/records/tasks/TASK-*.md`, `.ai/records/bugs/BUG-*.md`,
  `.ai/records/spikes/SPIKE-*.md`, `.ai/records/releases/REL-*.md`.
  Filename = `<ID>.md`. `.ai/tickets/` là legacy read-only (grandfathered).

### Không auto-tạo Feature cho mọi ticket

```
Requirement có phải là một capability chứa NHIỀU work item
độc lập có thể deliver riêng không?

CÓ  → tạo/dùng Feature, ticket con mang parent metadata
KHÔNG → tạo standalone Task/Bug/Spike
```

Feature 1-ticket chỉ hợp lệ khi có lý do capability thực sự. Ví dụ hợp lệ:
"Express Delivery Eligibility" (nhiều task: calculate / find sources / GraphQL /
storefront). Standalone hợp lệ: "Upgrade Magefan Blog" (TASK), "Fix GHTK tracking
callback signature validation" (BUG).

## 4. Parent Relationship = mutable metadata

```yaml
# child task record (.ai/records/tasks/TASK-4P8DX2.md)
id: TASK-4P8DX2
type: task
title: Calculate Express Delivery Eligibility
project_code: SLP
parent: {type: feature, id: FEAT-7K3M9Q}   # hoặc: parent: null (standalone)
```

- `parent` chỉ đến Feature record hiện hữu (validator resolve). Moving
  `FEAT-AAAAAA` → `FEAT-BBBBBB` chỉ đổi metadata + display title, **không đổi ID**.
- `ticket_ref` (FEAT template) chỉ dành cho liên kết legacy — không phải parent
  mechanism cho item mới.
- Frontmatter `project_code` (nếu có) phải khớp `.ai/toolkit/project.yaml` —
  metadata là authoritative cho display check.

## 5. Display Name — derived, không phải identity

`title:` frontmatter = **bare name** (Verb + Object, xem §6). Display title được
render:

| Kind | Format | Ví dụ |
|---|---|---|
| Feature | `[<CODE>][<FEAT-ID>] <Name>` | `[SLP][FEAT-7K3M9Q] Express Delivery` |
| Child task/bug/spike | `[<CODE>][<FEAT-ID>][<ITEM-ID>] <Name>` | `[SLP][FEAT-7K3M9Q][TASK-4P8DX2] Calculate Express Delivery Eligibility` |
| Standalone task/bug/spike | `[<CODE>][<ITEM-ID>] <Name>` | `[SLP][TASK-5HJ9WX] Upgrade Magefan Blog` |
| Release | `[<CODE>][<REL-ID>] <Name>` | `[SLP][REL-6W2K8P] Release 2026.09` |

- Record **new-format** đặt display title tại H1 của file; validator recomputes
  từ metadata (project code + parent + id + title) và FAIL khi mismatch (phát
  hiện: sai project code, thiếu/thừa parent prefix, sai id, stale parent sau
  re-parenting). **Metadata authoritative; display derived.**
- Record legacy (pre-P2A): display check bỏ qua (WARN-info một lần).
- Display title dùng cho PM tool title / index / nav rendering — KHÔNG dùng làm
  identity, KHÔNG parse ID ngược từ display string.

## 6. Human-Readable Naming

- Ticket/task/bug/spike: **Verb + Object/Outcome** — "Calculate Express Delivery
  Eligibility", "Validate GHTK Callback Signature", "Upgrade Magefan Blog".
  Tránh: "Shipping Update", "Fix Stuff", "API Changes".
- Feature: tên **capability**, không phải action — "Express Delivery",
  "SPX Shipping Integration", "Promotion Maximum Discount".
- Tên phải hiểu được mà không cần mở spec.

## 7. Spec Identity Mapping

- Spec ID = `SPEC-<OWNER-ID>`; filename = `SPEC-<OWNER-ID>-<slug>.md`
  (quy tắc canonical: `shared-core/rules/spec-first.md` §Spec Naming).
- OWNER-ID = Feature ID (spec của feature) hoặc ID của standalone Task/Bug/Spike/Release.
- Chấp nhận cả owner legacy (`FEAT-006`, `SL-019`) lẫn new-format (`TASK-4P8DX2`).
- Slug chỉ mang tính mô tả — KHÔNG phải một phần của spec identity
  (đổi slug không đổi Specification ID).

## 8. Decision Records — tích hợp với ID mới

- Quy tắc Entry h giữ nguyên: `DEC-{CODE}-{NNN}`, `{CODE}` = work-item ID bỏ hyphen,
  uppercase — new-format work item cho ra ví dụ `DEC-TASK4P8DX2-001`.
- Validator suy expected prefix từ `work_items[0]` (không regex cứng hai lần).
- Per-work-item suffix `NNN` vẫn max+1 + disk-lock placeholder (namespace của một
  work item đã được serialize bởi lock; đây không phải allocation toàn cục).

## 9. External PM IDs & Legacy Aliases

```yaml
id: TASK-4P8DX2                 # canonical — toolkit-owned
external_refs:                  # PM tool ids — separate, never canonical
  xcorp: SL-583
legacy_ids:                     # historical aliases (pre-P2A identity)
  - SL-019
```

- External PM ID (XCorp/Jira/Linear) KHÔNG bao giờ làm canonical identity; adapter
  mapping (Feature→Epic, Task→Ticket/Story, Bug→Jira Bug) không đổi core identity.
- `legacy_ids`: mỗi legacy ID chỉ được claim bởi MỘT record (validator FAIL khi
  trùng); resolution order khi tra cứu: exact canonical ID → `legacy_ids` scan →
  legacy filename (`tickets/SL-019-*.md`).

## 10. Migration & Backward-Compat Policy (P2A)

Phân loại item cũ TRƯỚC khi chạm, không blind rename:

- **A — external/history-bound** (đã được PM tool / commits / specs / DEC /
  release notes / docs tham chiếu): giữ nguyên legacy ID làm identity của item
  (grandfathered), KHÔNG re-mint. Ghi vào `legacy_ids` của record mới nếu item
  đó sau này được consolidate.
- **B — internal-only** (không reference ngoài): CÓ THỂ migrate hẹp — chỉ khi
  references cập nhật được an toàn và có lý do; default vẫn là giữ nguyên.
- **C — item mới sau P2A:** bắt buộc new-format ID + parent metadata + display
  title renderer.

Không tồn tại hai canonical ID cho cùng một item: mỗi item có đúng MỘT identity
(legacy hoặc new), alias chỉ là historical.

## 11. Validation Summary (`--check-identity`)

Project code regex + non-PENDING · ID dual-format regex per type · duplicate
canonical ID FAIL · `legacy_ids` uniqueness + không đè canonical ID · parent
resolve · `project_code` frontmatter khớp project.yaml · display H1 == render(metadata)
(new-format only) · work_items chấp nhận dual format.

## 12. Training Example

```text
Project:  Secomm Launchpad   Code: SLP

Feature:  [SLP][FEAT-7K3M9Q] Express Delivery
Tasks:    [SLP][FEAT-7K3M9Q][TASK-4P8DX2] Calculate Express Delivery Eligibility
          [SLP][FEAT-7K3M9Q][TASK-X82KDP] Retrieve Eligible Fulfillment Sources
          [SLP][FEAT-7K3M9Q][TASK-7MC2QA] Expose Delivery Eligibility via GraphQL
Standalone: [SLP][TASK-5HJ9WX] Upgrade Magefan Blog
```

```text
PROJECT CODE  ≠ canonical work-item identity   (namespace, hiển thị + config)
FEATURE ID    = stable feature identity
TASK ID       = stable ticket identity
parent        = mutable relationship (metadata, đổi được)
display title = derived human-readable representation (render từ metadata)
```
