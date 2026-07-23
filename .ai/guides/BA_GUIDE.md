# BA Guide — Secomm Launchpad

<!-- Enablement artifact — output: .ai/guides/BA_GUIDE.md (VI). Role: BA. -->

> **Purpose:** Hướng dẫn BA — requirement, discovery, spec, ticket, scope.
> **Human Owner:** BA · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "🧭 Tình huống thực tế" + "Open questions" · **Expected Reading Time:** 7 min
> **Current Status:** Generated · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## Role responsibilities

Bạn là **cầu nối giữa client requirement và team**: own phase requirement — discovery, spec, ticket với AC testable, scope management. Bạn decide requirement/scope/UAT; **không** quyết architecture/deploy. Client communication = **draft** → PM/TL review trước khi gửi. Không commit timeline/scope mà chưa resource confirm.

**Trong dự án vừa khởi tạo**, việc ưu tiên: chốt **objective + KPI** với stakeholder (hiện `[TBD]`), và làm rõ **multi-store intent** (single-store hiện tại, nhưng có dấu hiệu store thứ 2).

## Supported intents

| Intent | Bạn nói | Command | Bạn nhận được |
|---|---|---|---|
| Soạn spec | "soạn spec cho feature X" | `/spec` | Feature spec / mini-spec draft (objective, AC, out-of-scope) |
| Phân tích ticket | "phân tích ticket SEC-42" | `/task` | Risk + affected areas + effort + open question |
| Ước lượng | "ước lượng feature X" | `/estimate` | Effort estimate + estimation drivers |
| Tóm tắt status | "tóm tắt status dự án" | `/status` | Status summary shareable cho stakeholder |
| Xem quyết định chờ | "phải approve gì" | `/decisions` | Decision scope/objective đang chờ |
| Trì hoãn decision (hỏi client) | "để hỏi client" | `/tbd` | Decision trì hoãn, gắn cho stakeholder |

## Typical workflow (business language)

**Discovery → Spec → Build → Review → Deploy**

Bạn own phase **Discovery → Spec**: nhận client brief/contract → discovery (hỏi đúng phần thiếu) → soạn spec (`/spec`) với AC testable → tạo ticket (`/task`) → handoff cho Developer + TL. Spec phải SA/TL review trước khi giao dev. Sau build, UAT result từ QC → bạn tổng hợp → client signoff.

## 🧭 Tình huống thực tế

| Tình huống | Bạn nói | AI làm gì | Kết quả mong đợi |
|---|---|---|---|
| Client gửi requirement mới | "client vừa gửi requirement" | So sánh vs blueprint → hỏi chỉ phần thiếu | Spec draft + open question + update Navigator |
| Chuẩn bị discovery | "chuẩn bị discovery" | Agenda + câu hỏi theo priority + owner | Discovery plan + estimation drivers |
| Soạn spec feature | "soạn spec cho feature tìm kiếm" | Feature spec (objective, AC, out-of-scope) | Spec draft ready cho SA/TL review |
| Tạo ticket với AC | "phân tích ticket SEC-42" | AC testable + risk + affected areas | Ticket ready cho sprint |
| Cần hỏi client (chưa quyết) | "để hỏi client" | Trì hoãn decision | DEC gắn cho stakeholder với câu hỏi cụ thể |
| Tóm tắt cho stakeholder | "tóm tắt status dự án" | Status summary | Draft → PM/TL review trước khi gửi |

## Open questions (cần làm rõ với stakeholder)

| Câu hỏi | Priority | Blocking? |
|---|---|---|
| Client name + objective/KPI cụ thể? | high | no |
| Multi-store intent (general + fashion)? | high | **yes** |
| Timeline, team size, sprint cadence? | medium | no |

> Xem đầy đủ: `../runtime/DECISION_QUEUE.md`.

## Spec conventions (project-specific)
- **AC testable** — QC biết pass/fail.
- **Out-of-scope** ghi rõ.
- **Assumption / open question** ghi riêng (không trộn vào requirement).
- Trace requirement về source (client / meeting / contract).
- Storefront feature → nhắc song ngữ vi_VN/en_US (BR-001).

## Expected outputs
- Requirement document / user stories.
- Feature spec / mini-spec.
- Ticket với AC testable.
- UAT guide (draft), change request document.
- Meeting notes summary.

## Common mistakes

| Mistake | Fix |
|---|---|
| AC không testable | Mỗi AC phải QC biết pass/fail |
| Trộn assumption vào requirement | Ghi assumption/open question riêng |
| Gửi client communication chưa review | Draft → PM/TL review trước khi gửi |
| Commit scope/timeline thiếu resource confirm | Confirm resource trước |

## Best practices
- [ ] Requirement trace về source.
- [ ] AC testable (QC biết pass/fail).
- [ ] Out-of-scope document rõ.
- [ ] Assumption/open question ghi riêng.
- [ ] Spec SA/TL review trước khi giao dev.

> Phần kiến trúc nội bộ xử lý tự động — bạn làm requirement bằng business language.
