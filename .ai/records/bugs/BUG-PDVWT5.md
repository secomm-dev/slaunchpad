---
id: BUG-PDVWT5
type: bug
title: "[Category page][Filter] Swatch preview tooltip hiển thị sai vị trí khi hover vào swatch màu"
project_code: SLP
parent:
external_refs:
  ticket: SLP-134
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: done
created: 2026-09-07
updated: 2026-09-07
ticket_ref:
affects_version: "Magento 2.4.8-p5 + Hyvä 1.5.2 + Hyva_SmileElasticsuite 1.2.8"
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite
source_areas:
  - catalog-filter
  - hyva-theme
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: ""
last_verified: 2026-09-07
supersedes: []
---

# [SLP][BUG-PDVWT5] [Category page][Filter] Swatch preview tooltip hiển thị sai vị trí khi hover vào swatch màu

<!-- External ticket: SLP-134 [Category page][Filter] Điều chỉnh UI filer -->

## Summary

Khi người dùng rê chuột (hover) vào các tùy chọn màu sắc (color swatch) trong bộ lọc Layered Navigation ở trang danh mục sản phẩm (Category Page / Search Result Page), popup tooltip xem trước (preview ảnh/màu và tên màu) hiển thị ở góc trên bên trái màn hình hoặc vị trí rất xa so với ô swatch đang tương tác.

## Root Cause Analysis

1. **Xung đột hệ quy chiếu định vị (CSS `position: fixed` vs JavaScript `offsetTop`/`offsetLeft`):**
   - Template hiển thị tooltip của Hyvä (`vendor/hyva-themes/magento2-default-theme/Magento_Swatches/templates/product/tooltip.phtml`) định nghĩa phần tử tooltip dùng class Tailwind `fixed` (`position: fixed`). Tọa độ của một phần tử `fixed` được tính tương đối theo **Viewport (màn hình hiển thị)** của trình duyệt.
   - Tuy nhiên, trong template của module tương thích bộ lọc tìm kiếm `Hyva_SmileElasticsuite` (`vendor/hyva-themes/magento2-smile-elasticsuite/src/view/frontend/templates/swatch/product/layered/renderer.phtml`), hàm `getTooltipPosition()` lại tính toán vị trí theo:
     ```javascript
     getTooltipPosition() {
         return this.tooltipPositionElement ?
             (
                 `top: ${this.tooltipPositionElement.offsetTop}px;` +
                 `left: ${this.tooltipPositionElement.offsetLeft}px;`
             ) : ''
     }
     ```
   - Các thuộc tính `offsetTop` và `offsetLeft` chỉ trả về tọa độ của ô swatch tương đối so với **phần tử cha định vị gần nhất (`offsetParent`)** (ví dụ sidebar/filter container), không phải so với Viewport.
2. **Hậu quả hiển thị:**
   - Giá trị ví dụ `top: 131px; left: 24px;` được gán trực tiếp vào phần tử `position: fixed`, khiến tooltip bị ghim cố định cách mép trên trình duyệt 131px và mép trái trình duyệt 24px, hoàn toàn lệch khỏi vị trí thực của ô swatch trên trang.
   - Khi người dùng cuộn chuột (scroll), tooltip `fixed` không di chuyển theo swatch mà vẫn đứng im tại góc màn hình.
   - Mũi tên đáy của tooltip (`left-1/2 -translate-x-1/2`) không chỉ vào ô swatch do không có logic căn giữa (`translateX(-50%)`).
3. **Lý do trang demo Hyvä không bị lỗi:**
   - Trang demo chính thức của Hyvä dùng bộ template core mặc định (`Magento_Swatches/templates/product/js/layered-swatch.phtml`), ở đó đã được viết chuẩn bằng `getBoundingClientRect()`.
   - Lỗi này xuất phát từ việc module tích hợp bên thứ ba `hyva-themes/magento2-smile-elasticsuite` (bản 1.2.8) chưa cập nhật đồng bộ với core Hyvä 1.5.2.

---

## Mini Spec

### Goal

- Tooltip preview khi hover vào color swatch trên Layered Navigation phải luôn xuất hiện ngay phía trên ô swatch tương ứng và căn giữa theo chiều ngang của swatch, kể cả khi trang đã cuộn (scroll).
- Không sửa trực tiếp vào thư mục `vendor/`, thực hiện override template đúng chuẩn Magento 2 / Hyvä theme.

### Expected Behavior

- Khi hover chuột vào swatch màu hoặc ảnh trên sidebar bộ lọc:
  - Tooltip xuất hiện ngay phía trên đỉnh của ô swatch, cách một khoảng đệm hợp lý (`mb-4`).
  - Mũi tên đáy ở giữa tooltip chĩa thẳng vào tâm của ô swatch.
  - Khi cuộn trang (scroll) hoặc resize cửa sổ, tọa độ hiển thị được tính toán chính xác theo Viewport.
- Khi rê chuột ra ngoài swatch (`mouseleave`), tooltip ẩn đi ngay lập tức.

### Constraints / Rules

- Tuân thủ quy chuẩn Hyvä Themes (Alpine.js + Tailwind CSS v4, không dùng jQuery/RequireJS).
- Không chỉnh sửa file gốc trong `vendor/hyva-themes/magento2-smile-elasticsuite/`.
- Thực hiện override qua theme `app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite/templates/swatch/product/layered/renderer.phtml`.

### Out of Scope

- Không thay đổi cấu trúc lọc facet hoặc logic truy vấn Elasticsearch của Smile ElasticSuite.
- Không thay đổi giao diện tooltip trên trang chi tiết sản phẩm (PDP) hay danh sách sản phẩm (PLP product card).

### Acceptance Criteria

- **AC-001**: Khi hover vào bất kỳ swatch màu/ảnh nào trong bộ lọc Category Page, tooltip preview hiển thị chính xác ngay phía trên swatch, căn giữa swatch.
- **AC-002**: Tooltip hiển thị đúng thông tin: swatch preview (màu/ảnh) và nhãn tên màu (`getTooltipLabel`).
- **AC-003**: Khi cuộn trang và hover swatch, vị trí tooltip vẫn chuẩn xác (không bị ghim ở góc trên màn hình).
- **AC-004**: Không có lỗi JavaScript / Alpine.js warning trong Console trình duyệt.
- **AC-005**: File override nằm đúng đường dẫn theme `app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite/` và không đụng vào `vendor/`.

---

## Steps to Reproduce

1. Truy cập trang danh mục sản phẩm (Category Page) bất kỳ có bộ lọc thuộc tính Color (ví dụ trang quần áo/thời trang).
2. Tìm đến block bộ lọc bên trái (Layered Navigation) tại mục Color.
3. Rê chuột vào một ô màu bất kỳ (ví dụ: White, Black...).
4. Quan sát: Tooltip hiển thị tít ở góc trên màn hình (`top: 131px; left: 24px;`) thay vì nằm ngay trên ô màu.

---

## Implementation Approach

1. Tạo thư mục override trong theme:
   `app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite/templates/swatch/product/layered/`
2. Sao chép file `vendor/hyva-themes/magento2-smile-elasticsuite/src/view/frontend/templates/swatch/product/layered/renderer.phtml` sang thư mục trên.
3. Cập nhật phương thức `getTooltipPosition()` sang chuẩn Hyvä:
   ```javascript
   getTooltipPosition() {
       if (!this.tooltipPositionElement) return null;
       const el = this.tooltipPositionElement;
       const elRect = el.getBoundingClientRect();
       const left = elRect.left + elRect.width / 2;
       return {
           bottom: `${window.innerHeight - elRect.top}px`,
           left: `${left}px`,
           transform: 'translateX(-50%)'
       };
   },
   ```
4. Xóa cache Magento (`bin/magento cache:clean`).
5. Kiểm thử giao diện trên trình duyệt (Desktop hover, scroll test).

---

## Affected Files

- [NEW] `app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite/templates/swatch/product/layered/renderer.phtml`

---

## Verification Plan

### Automated / Syntax Check
- Kiểm tra cú pháp PHP: `php -l app/design/frontend/Secomm/launchpad/Hyva_SmileElasticsuite/templates/swatch/product/layered/renderer.phtml`
- Kiểm tra tính hợp lệ của AI record: `.ai/bin/project-ai-validate --check-records`

### Manual Verification
- Mở Category Page trên môi trường local, hover vào color swatch.
- Xác nhận tooltip xuất hiện chuẩn trên đầu nút swatch, mũi tên nhọn trỏ vào tâm swatch.
- Cuộn trang lên xuống và hover lại để xác nhận tọa độ Viewport luôn chính xác.
