---
id: SPEC-TASK-ST61M9
type: specification
title: Global Style Showcase
feature_id: NONE
specification_level: FULL
status: VALID
owner: TASK-ST61M9
external_ref: SLP-246
created: 2026-09-24
updated: 2026-09-24
---

# SPEC-TASK-ST61M9 — Global Style Showcase

## Goal

Cung cấp regression page tái sử dụng để Frontend Developer, Designer và Tech
Lead kiểm tra Global Style contract của Launchpad core và các theme con.

## Functional contract

- URL: `/themehelper/styleguide/`.
- Chỉ trả về showcase khi Magento chạy ở developer mode; mode khác trả về 404.
- Header và Footer không được render trên route này.
- Markup phải sử dụng semantic Tailwind utilities và component hooks production,
  không tạo một bộ style riêng chỉ dành cho showcase.
- Button, form controls và focus state phải tương tác được bằng bàn phím.
- Icon phải được render qua `Hyva\Theme\ViewModel\SvgIcons` từ theme assets.

## Nội dung phase đầu

- Semantic colors.
- Typography scale.
- Radius và shadow.
- Button variants, sizes, icon structures và disabled state.
- Input, Textarea, Select, Checkbox, Radio và Switch.
- Custom icons hiện đã được import vào theme.

## Scope boundary

Không bao gồm Header, Footer, Menu, modal, product cards hoặc page-specific
components. Các phần này thuộc Component Showcase trong một scope sau.
