# TASK-ST61M9 — Implementation Plan: Global Style Showcase

| Field | Value |
|---|---|
| Specification | [SPEC-TASK-ST61M9](../specs/SPEC-TASK-ST61M9-global-style-showcase.md) |
| External reference | `SLP-246` |
| Mode | B |
| Status | Completed |
| Date | 2026-09-24 |

## Ordered implementation

1. Thêm frontend route và controller developer-mode gate trong
   `Secomm_ThemeHelper`.
2. Tạo layout riêng, loại Header/Footer và compose các section template nhỏ.
3. Render foundation bằng semantic utilities/component hooks hiện có.
4. Bổ sung storefront translations và module documentation.
5. Chạy PHP/XML/Tailwind validation và QA route local.
6. Stage/commit Global Style và Showcase theo explicit file list; giữ Header
   ngoài staging area.
