# Secomm Launchpad — Form design gaps

Ngày đối chiếu: 2026-09-24
Nguồn: Figma LAUNCHPAD-CORE, page/node 2410:14322

## Đã đủ dữ liệu để triển khai

- Input master 2410:14473:
  - Type: Default, Leading dropdown, Trailing dropdown, Leading text.
  - State: Placeholder, Hover, Active, Filled, Focus, Disabled.
  - Feedback: None, Error, Warning, Success.
  - Options: Label, Leading icon, Trailing icon, Hint text.
- Base Input 2410:15699.
- Textarea master 2410:14352 và Base Textarea 2410:15674.
- Checkbox 2174:32588, Radio 2174:30745, Base CheckRadio 2174:33021.
- Button foundation thuộc page 2410:25885; Base Button hiện hành 2410:26973.

## Designer cần bổ sung hoặc xác nhận

1. **Standalone Select master**
   - Closed/open state, selected value, placeholder, disabled và focus.
   - Error/Warning/Success và hint/message.
   - Menu option states: default, hover, selected, disabled.
   - Long option, overflow, empty/no-results và loading nếu Select có search.

2. **Phân biệt Select với Input addon**
   - Leading dropdown và Trailing dropdown hiện là Input ghép dropdown.
   - Cần xác nhận dropdown addon dùng native Select hay custom popover/listbox,
     cùng hành vi keyboard và mobile.

3. **Icon contract của Input**
   - Semantic mapping cho leading/trailing icon theo use case.
   - Trạng thái clickable/non-clickable, accessible label và hit area.
   - Xác nhận feedback icon có bắt buộc ở Error/Warning/Success hay không.

4. **Readonly và browser-driven states**
   - Readonly chưa xuất hiện như một variant riêng.
   - Cần xác nhận autofill, validation pending/loading và password reveal nếu
     chúng thuộc Global Style.

5. **Textarea behavior**
   - Resize policy, character counter và max-length behavior.
   - Mobile height/min-height nếu khác desktop.

6. **Switch master**
   - Switch đang dùng Hyvä/global foundation hiện có nhưng chưa tìm thấy master
     cùng state matrix trong Form page đã cung cấp.

## Quy ước hiện tại

- Showcase chỉ coi các Input/Textarea/Checkbox/Radio đã liệt kê là
  design-backed.
- Native Select vẫn được hiển thị để regression-test shared form foundation,
  nhưng được ghi rõ là provisional và không đại diện cho một component đã được
  designer approve.
- Hover, Active và Focus được kiểm tra bằng tương tác thật; không thêm CSS chỉ
  để đóng băng state trong Showcase.
