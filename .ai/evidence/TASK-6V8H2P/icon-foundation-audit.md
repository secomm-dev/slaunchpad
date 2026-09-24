# Launchpad Core — Hyvä Custom Icon Foundation Audit

**Task:** `TASK-6V8H2P`  
**Date:** 2026-09-23  
**Figma source:** `LAUNCHPAD-CORE`, node `5:28677`

## Kết quả

Icon foundation contract đã sẵn sàng để các component sử dụng. Global Style định
nghĩa workflow Figma-to-Hyvä dùng lại toàn site nhưng không bulk-export library.
CSS foundation hiện tại không consume glyph cụ thể, vì vậy phase này không thêm
SVG asset vào theme.

## Cấu trúc library đã xác minh

| Outer node | Inner collection | Production style |
|---|---|---|
| `2001:10642` (`Fill Icon Sets`) | `2001:10644` (`fill-icon`) | `fill` |
| `2001:13716` (`Fill Icon Sets`) | `2001:13718` (`outline-icon`) | `outline` |

Tên outer frame bị trùng là lỗi presentation trong Figma; tên inner collection
và semantic component suffix xác định rõ ownership.

Cả hai collection có variant 12, 16, 24, 32 và 40px. Directional families có
thêm Down, Right, Up và Left.

## So sánh vector đại diện

### Fill

Các node `academic-cap-fill` trong `2001:10652` đã được kiểm tra tại 12/24/40px.
Path coordinates scale tỷ lệ với viewBox. Representative này có thể lưu một lần
dưới dạng canonical SVG rồi truyền size khi render.

### Outline

Các node `user-circle-outline` trong `2001:16535` đã được kiểm tra đủ năm size:

| Size | Stroke width |
|---:|---:|
| 12 | 1px |
| 16 | 1.5px |
| 24 | 2px |
| 32 | 2px |
| 40 | 2.5px |

Outline set có optical size adjustments. Scale một source không thể tái tạo chính
xác mọi designed variant. Component work vì vậy export đúng outline size được
design và đặt tên, ví dụ `outline/user-circle-24.svg`. Một glyph chỉ được bỏ
suffix khi audit chính glyph đó chứng minh normalized geometry tương đương.

## Production contract

- Export on demand từ consuming component, không export từ toàn page.
- Lưu tại `Hyva_Theme/web/svg/{style}/{semantic-name}.svg`; chỉ thêm `-{size}`
  cho optical variant đã xác minh.
- Direction trở thành semantic filename segment, ví dụ
  `outline/arrow-right-24.svg`.
- Giữ path/viewBox/stroke semantics; normalize monochrome color thành
  `currentColor`; từ chối executable hoặc external content.
- Render qua `Hyva\\Theme\\ViewModel\\SvgIcons::renderHtml()` với width/height
  explicit; containing control sở hữu accessibility semantics.
- Chỉ reuse POC asset sau khi so sánh semantic name và vector glyph.

## Chuyển sang consuming component

Task Header, menu, footer và page/component phải tự inventory icon instances,
chỉ export subset style/size/direction cần thiết và đính kèm visual evidence cho
các state/breakpoint được cung cấp.
