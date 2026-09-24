# TASK-ST61M9 — Global Style Showcase Validation

Date: 2026-09-24

## Static validation

- Controller và năm PHTML templates: `php -l` PASS.
- Frontend route, layout và module XML: `xmllint --noout` PASS.
- Tailwind CSS v4.3.2 build: PASS.
- Token generation: 14 files, 1,100 records, 890 production tokens và không có
  unresolved production alias/invalid value.
- `git diff --check`: chạy tại bước pre-commit.

## Runtime validation

- Local URL: `/themehelper/styleguide/`.
- HTTP response trong developer mode: 200.
- Header, Footer và default page title được loại khỏi layout.
- Semantic color, typography, radius/shadow, Button, Form và custom icon
  sections đều render.
- Typography contract load đúng sau khi static asset version được refresh;
  Desktop Display computed size: 72px.
- Mobile 375×812: không có horizontal overflow.
- Checkbox computed sizes: 16/20/24px.
- Radio computed sizes: 16/20/24px.
- Sáu custom icons render qua `Hyva\Theme\ViewModel\SvgIcons`.
- Accessibility tree nhận đúng heading hierarchy, labelled fields, disabled
  controls, Checkbox, Radio, Switch và accessible names của icon-only Button.

## Scope check

- Showcase không thêm CSS riêng; toàn bộ presentation dùng production semantic
  Tailwind utilities và component hooks.
- Header implementation đang làm dở không thuộc validation/commit scope này.
- Mirasvit SEO Toolbar là local developer overlay và không thuộc showcase.
