---
id: DEC-TASKFMAN1B-001
title: OSC address integration tách sang module project layer Launchpad_Osc; Secomm_AddressDropdown giữ default checkout
status: accepted             # approved via user acting as SA/TL authority (chat 2026-08-28)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-28
created: 2026-08-28
last_verified: 2026-08-28
verified_against_commit:
supersedes: []
superseded_by: []
work_items: [TASK-FMAN1B]
---

# Decision Record: Launchpad_Osc — OSC address integration module riêng

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-08-28 (user acting as SA/TL; formal SA/TL name [TBD]). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Thực thi "project integration module" option của TASK-FMAN1B AC-O1 trên nền DEC-8. -->

## Context

QC OSC (2026-08-28) cho thấy cascade địa chỉ VN trong Mageplaza OSC chạy qua các mixin/component sống trong `Secomm_AddressDropdown` nhưng viết sặc sụa chống lại DOM của OSC (`#co-shipping-form`, grid classes `col-mp mp-6`, hành vi hash của OSC) — audit + AC-O1 đã flag đây là boundary violation: module generic (tái dụng, DEC-8) không được chứa coupling với một extension thương mại. Các quick-fix (round 1–2: schema labels, sort Đ==D, field order; round 3 pending: native city field) đang dồn về đúng chỗ module generic này, làm vi phạm ngày càng nặng.

User chốt 2026-08-28 (chat): *"Secomm module thì sẽ không phụ thuộc bất kỳ extension nào — đề xuất viết module mới… phụ thuộc vào OSC có enable hay không. Với Secomm_AddressDropdown thì sẽ handle cho checkout default của Magento."*

## Decision

1. **Module mới `Launchpad_Osc`** (`app/code/Launchpad/Osc/`) — project layer của package Launchpad — sở hữu TOÀN BỘ coupling OSC của address cascade. Không bao giờ edit `app/code/Mageplaza/*` in-place (AC-O2 giữ nguyên).
2. **`Secomm_AddressDropdown` = generic + default Magento checkout**: giữ injection `checkout_index_index.xml` (component của nó), mixins trên `Magento_Checkout/*` (submit path, sub_city sync — contract của core checkout), data engine, GraphQL, customer/admin forms. Không thêm bất kỳ reference OSC nào.
3. **Cơ chế injection OSC**: `Launchpad_Osc/view/frontend/layout/onestepcheckout_index_index.xml` ghi đè CÙNG các key jsLayout (`address_dropdown`, `address_dropdown_billing`) bằng bản copy OSC-tuned của 2 component. Trang OSC kế thừa `checkout_index_index` (update handle) nên AddressDropdown inject trước, Launchpad_Osc merge sau (thứ tự module trong `app/etc/config.php`: Mageplaza_Osc 413 < Secomm_AddressDropdown 416 < Launchpad_Osc 440 — deterministic qua sequence) → **đúng 1 instance mỗi form**, không double-run.
4. **Soft coupling (OSC enable/disable)** — Magento Open Source 2.4.8 KHÔNG có `soft="true"` trong `module.xsd` (Adobe-only), thay bằng:
   - `<sequence>` chỉ xếp thứ tự KHI module hiện diện, không phải hard requirement;
   - mọi frontend behaviour scope theo handle `onestepcheckout_index_index` → OSC tắt ⇒ handle không render ⇒ module không đóng góp gì;
   - composer chỉ `suggest` Mageplaza OSC;
   - PHP integration tương lai (Option A plugin/layout processor) BẮT BUỘC runtime-gate qua `ModuleManager::isEnabled/isOutputEnabled('Mageplaza_Osc')`.
5. **Bản copy trong Launchpad_Osc là TẠM THỜI theo design**: mang theo quick-fix round 1–3, sẽ bị thay bằng Alpine schema cascade (Option A — TASK-FMAN1B) rồi remove cùng legacy mixins (TASK-K09G8Y).
6. Round-3 quick fix (áp trong bản Launchpad_Osc): VN → luôn ẩn native City field (gỡ `required`/`aria-required` khi ẩn để tránh Chrome *"not focusable"* block submit; restore khi non-VN) + hiện ward dropdown ngay cả khi chưa chọn region → thứ tự nhìn thấy luôn là Quốc gia → Tỉnh/Thành phố → Phường/Xã, hết bubble "Please fill out this field." trên field ảo.

## Alternatives

- **Theme override Launchpad làm vehicle duy nhất (Option A như kế hoạch cũ):** không loại bỏ — Option A vẫn là đích; nhưng quick-fix jQuery cần một "nhà" đúng boundary ngay trong khi QC đang chạy, và module chứa được cả Option A sau này (layout processor + plugin có runtime gate), không phụ thuộc theme.
- **Move-only (bỏ hẳn injection trong AddressDropdown):** rejected — deployment generic không có OSC sẽ mất cascade checkout, phá story tái dụng của DEC-8.
- **Một file duy nhất + argument `oscMode` branch:** rejected — OSC-tuned code vẫn nằm trong module generic, đúng thứ cần loại bỏ.
- **Duplicate vĩnh viễn:** rejected — chỉ trong cửa sổ đến Option A; sau đó bản Launchpad_Osc chết theo TASK-K09G8Y.

## Consequences

- (+) Boundary sạch: module generic không phụ thuộc extension nào; coupling OSC tập trung 1 chỗ có thể bật/tắt theo OSC.
- (+) OSC disabled ⇒ Launchpad_Osc trơ inert (handle scope), không lỗi, không asset thừa.
- (−) Trùng lặp tạm thời 2 file ~600 dòng giữa AddressDropdown (default checkout) và Launchpad_Osc (OSC) — chấp nhận trong cửa sổ Option A; fix chung (nếu có) phải áp 2 phía đến khi Option A landing.
- (−) Merge override dựa trên thứ tự module (sequence) — đã verify trong `config.php`; note: nếu thêm module mới sort giữa Secomm_AddressDropdown và Launchpad_Osc phải rà lại.
- Follow-up: Option A (Alpine cascade) implement TRONG Launchpad_Osc; TASK-K09G8Y remove bản copy + legacy mixins ở cả 2 module.

## Affected components

Mới: `Launchpad_Osc` (`app/code/Launchpad/Osc/`). Impact: `CMP-ADDR` (Secomm_AddressDropdown — không đổi code, chỉ đứng sau Launchpad_Osc trong merge order). Source: `app/code/Launchpad/Osc/`.

## Related records

> `work_items` (frontmatter) là canonical. Mục này chỉ narrative.

- Ticket: TASK-FMAN1B (AC-O1 boundary; Option A kế tiếp trong module này)
- Nền tảng: DEC-8 (module/Launchpad boundary), DEC-FEAT2PZQKJ-001 (schema renderer — nguồn label/profile)
- Follow-up: TASK-K09G8Y (removal)
- DECISIONS.md index: DEC-TASKFMAN1B-001
