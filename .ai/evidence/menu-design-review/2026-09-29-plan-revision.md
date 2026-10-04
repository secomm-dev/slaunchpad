# Menu design review — 2026-09-29

Scope: cập nhật kế hoạch theo page Figma mới, không implementation.

## Evidence đã đọc

- File `5MpBw9VaNgFdct3UDWABuV`, page `2949:93615`: metadata và screenshot tổng.
- `get_design_context` + screenshots của Desktop `2151:11236`, Mobile root `2115:5380`, level 1 `2949:77708`, level 2 `2949:93394`.
- Lần gọi design context ở canvas trả lỗi selection; đã khắc phục bằng metadata để lấy child IDs và đọc trực tiếp từng component. Không dùng lỗi này làm evidence thiết kế.
- Page metadata liệt kê ba Mobile variants 375×812; không liệt kê Tablet frame.
- Screenshot level 1 có back row, nhóm đóng và ảnh; level 2 có nhóm mở inline, leaf links và ảnh đẩy xuống.
- Root generated context có thêm Settings layers không thấy trong screenshot; scope các layer này được ghi là cần xác minh, không tự coi là yêu cầu mới.

## Diff summary

- Cập nhật `.ai/project/design/secomm-launchpad-header-menu-footer-analysis.md`: nguồn page, node inventory, hiện trạng Header/ThemeHelper, kế hoạch §7, AC và quyết định còn mở.
- Estimate Menu 26–33h thay baseline 22–29h; Header level-1 được tái sử dụng. Tổng historical scope cập nhật tương ứng, không gọi là effort còn lại.
- Memory chỉ ghi link tới revision; không đánh dấu implementation/approval hoàn tất.
- Không sửa application code, Figma, Admin content hoặc database; không chạy build/test ứng dụng cho thay đổi tài liệu.

## Verification

- Đối chiếu source IDs và dimensions với Figma responses ở trên.
- `git diff --check` trên các tài liệu thay đổi: pass.
- Kế hoạch giữ Proposed/non-executable; chưa có Full Spec VALID và không giả lập approval.

## Follow-up: Settings + reference components

- Re-read Figma `2115:5380` design context và screenshot sau thông báo cập nhật: root 375×1177, Language có English/Vietnamese và selected indicator, My Account có user icon/chevron; Currency không còn. Findings trước về Settings visibility được supersede.
- Đã đọc README và desktop/item source của `menu/C-vertical-dropdown-4-column`; tên open-with-block là preview của component này. Nested columns mở qua click; CMS block slot upstream nằm bên phải.
- Đã đọc README và mobile/item/languages source của `menu-mobile/A-scroll`: dialog/noscroll, recursive panels, back/focus/inert và native language details. Current language bị loại khỏi options ở upstream, cần adapt theo Figma.
- composer.lock: default-theme và theme-module 1.5.2; htmldialog template có trên filesystem; chưa tuyên bố browser runtime verification.
- Design analysis §7 chỉnh Settings/AC và §8 ghi mapping source → Snowdog, khác biệt với Figma, integration checks. My Account expanded behavior chưa có evidence; giữ open issue cụ thể.
- Chỉ thay đổi tài liệu/memory; diff check pass.
