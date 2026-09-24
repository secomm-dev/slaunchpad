---
id: TASK-ST61M9
type: task
title: Add Global Style Showcase
project_code: SLP
external_refs:
  xcorp: SLP-246
mode: B
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-TASK-ST61M9-global-style-showcase.md
risk: low
status: completed
created: 2026-09-24
updated: 2026-09-24
components:
  - CMP-THEME
source_areas:
  - app/code/Secomm/ThemeHelper/
  - app/design/frontend/Secomm/launchpad/Secomm_ThemeHelper/
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
---

# [SLP][TASK-ST61M9] Global Style Showcase

## Mục tiêu

Tạo một trang kỹ thuật để kiểm tra trực quan các foundation và primitive đã
được triển khai trong Global Style của theme đang active.

## Acceptance Criteria

- [x] Route chỉ truy cập được trong Magento developer mode.
- [x] Trang không render Header, Footer, Menu hoặc component cấp page.
- [x] Hiển thị semantic colors, typography, radius/shadow, Button, Form và icon.
- [x] Các control tương tác dùng markup native và Global Style API thật.
- [x] Tailwind build, PHP lint và XML validation thành công.

## Ngoài phạm vi

- Header, Footer, Menu và component/page showcase.
- Trình quản trị nội dung hoặc chỉnh token trực tiếp trên showcase.
