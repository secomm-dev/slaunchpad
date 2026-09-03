---
id: DEC-FEATYA2C0W-002
title: 'Swap model: DB chứa MỘT scheme VN tại một thời điểm (purge + re-import khi đổi scheme) — supersede D-R2/D-R4 của DEC-FEATYA2C0W-001'
status: accepted             # approved via user acting as SA/TL authority (chat 2026-08-26, chọn rõ trong AskUserQuestion)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-26
created: 2026-08-26
last_verified: 2026-08-26
verified_against_commit:
supersedes: []
superseded_by: DEC-FEATYA2C0W-003   # partial — NAMING only (vn_current/vn_legacy, VNC/VNL, profile codes); swap model retained
work_items: [FEAT-YA2C0W, TASK-ADT94K, TASK-X0XKH4, TASK-AP6YXP]
---

# Decision Record: Swap model cho 2 scheme VN (một scheme/lần)

> **AMENDED 2026-08-27 — DEC-FEATYA2C0W-003**: scheme NAMING đổi sang versioned identities `VN_ADMIN_2025`/`VN_ADMIN_PRE_2025` (profiles `vn_admin_2025`/`vn_admin_pre_2025`, unit codes `VNA25-`/`VNAP25-`); phần còn lại của decision này (swap model: DB một scheme/lần, purge+import+config, membership reseed) GIỮ NGUYÊN và được DEC-003 tái khẳng định.

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-08-26 (user acting as SA/TL). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Supersede CỤ BỘ DEC-FEATYA2C0W-001: các điểm D-R2 (prefix L-), D-R4 (cùng tồn tại +11.4k rows), point 2/3 (membership tách 2 profile VN). Các điểm còn lại của DEC-001 (profiles XML, relation model, resolver contract, D-R1, D-R3) KHÔNG đổi. -->

## Context

Khi triển khai TASK-ADT94K (import 2 dataset VN_CURRENT/VN_LEGACY), user (SA/TL) chọn cơ chế lưu trữ/lựa chọn scheme sau khi đã được trình bày 2 lựa chọn đầy đủ (coexist + flip config vs swap toàn bộ): **DB chỉ chứa MỘT scheme VN tại một thời điểm**. Đổi scheme = purge data VN → import dataset scheme mới → config `address/profiles/mapping` trỏ profile mới (CLI tự động hóa cả 3 bước).

Lý do chọn: mô hình đơn giản hơn về khối lượng data và trạng thái hệ thống; pre-launch nên hiệu ứng reset địa chỉ đã lưu là chấp nhận được.

## Decision

1. **Swap model**: một scheme/lần. `secomm:vietnam-address:import --scheme <X> [--swap]` — import cùng scheme = upsert idempotent theo code (bảo toàn id); import khác scheme bắt buộc `--swap` (purge toàn bộ data VN trước khi import).
2. **Prefix `L-` bỏ** (supersede D-R2): không coexist → không collision; file legacy giữ code chính thức `01…96` nguyên bản.
3. **Membership không tách 2 profile VN** (supersede point 2/3 + D-R4 của DEC-001): chỉ profile active claim toàn bộ region VN; import/swap luôn reseed membership + set config `VN → profile active`.
4. **Cùng tồn tại vẫn đúng ở tầng DATASET FILE**: 2 file `_import.csv` checked-in với codes ổn định vĩnh viễn (`VNC-`/`VNL-`); identity = code, display name chỉ là hiển thị.
5. **Mapping/resolver tương lai (TASK-X0XKH4/AP6YXP) chuyển hướng code-based**: relation table sẽ tham chiếu code (không phải id tồn tại đồng thời); khi kích hoạt, 2 task phải thiết kế lại input assumption này.

## Alternatives

- **Coexist (DEC-001 gốc: prefix L- + membership tách 2 profile + flip config)**: rejected bởi user 2026-08-26 — phức tạp hơn mức cần cho Launchpad; hệ lụy chấp nhận: mapping/resolver tương lai thiết kế lại theo code-based.
- **Config quyết định data (mỗi scheme một bảng riêng)**: rejected — phá kiến trúc generic một bảng recursive của DEC-FEAT2PZQKJ-001.

## Consequences

- (+) DB luôn nhất quán 1 scheme; không region-leak ở dropdown (bỏ luôn nhu cầu region-filter đã từng cân nhắc).
- (+) Đổi scheme chỉ là 1 lệnh CLI (purge + import + config + cache), có `--dry-run` và guard `--swap` chống xóa nhầm.
- (−) Mỗi lần swap = reset địa chỉ đã lưu (tên/id cũ mất match validator) — pre-launch chấp nhận; document trong README/CHANGELOG.
- (−) Carrier cần đọc ĐỒNG THỜI 2 scheme (so sánh current vs legacy) phải swap qua lại hoặc thiết kế code-based — TASK-X0XKH4/AP6YXP ghi nhận input assumption thay đổi.
- (−) D-R4 (chấp nhận +11.4k rows cùng tồn tại) không còn ý nghĩa; dung lượng DB = scheme active duy nhất.

## Verification

- TASK-ADT94K verification: swap current↔legacy↔current trên dev DB, counts + membership + config đúng từng bước (evidence `.ai/runtime/evidence/TASK-ADT94K/`).
