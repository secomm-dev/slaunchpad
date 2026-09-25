# Form Foundation Validation — 2026-09-23

## Source evidence

- Final form documentation page: `2410:14322`.
- Input component set: `2410:14473`; Base Input: `2410:15699`.
- Textarea component set: `2410:14352`; Base Textarea: `2410:15674`.
- Button page/inventory: `2410:25885`.
- Checkbox component set: `2174:32588`; Radio component set: `2174:30745`;
  shared Base CheckRadio primitive: `2174:33021`.
- Legacy Button `2410:25890`, Legacy Icon Button `2410:25923` and Legacy Input
  component sets were excluded.
- Select has no approved standalone final master. This phase therefore applies
  only the shared native field foundation to select and does not invent a
  select-specific visual contract.

## Implemented contract

- Input/select field: 44px height, 14px inline and 10px block padding, 6px
  radius, Body 2 typography and semantic placeholder/text/border colors.
- Textarea: the shared field contract with a 160px minimum height.
- Label and supporting text: 6px spacing and exported semantic typography and
  colors.
- Field states: placeholder/default, hover, active, focus, filled, disabled,
  readonly and error; warning/success hooks are available through
  `data-feedback`.
- Focus: brand-primary border with the exported 4px primary focus ring.
- Checkbox/radio: M defaults to 20px with S/M/L helpers at 16/20/24px, plus
  hover, checked, focus and disabled semantic states.
- Magento checkbox controls with `role="switch"` retain the dedicated Hyvä
  switch geometry and are not overridden by the checkbox component dimensions.
- Input groups use the same 4px semantic focus ring.

## Automated validation

- `npm run build`: PASS after the final form changes.
- Token generation: PASS — 14 files, 1,100 records, 890 production records and
  zero unresolved production aliases.
- Tailwind CSS v4.3.2 compilation: PASS.
- `git diff --check`: PASS.

## Runtime validation

Validated on the local Magento customer account creation page:
`/customer/account/create/`.

- Visible input: 44px high; 14px × 10px padding; 6px radius; 16px/24px Body 2;
  gray-primary default border.
- Label: 14px/20px, weight 400, gray-quaternary and 6px bottom spacing.
- Keyboard/pointer focus: brand-primary border `rgb(41, 62, 45)` and 4px
  brand-secondary focus ring `rgb(207, 227, 209)`.
- Existing Magento switch controls remain 36px × 20px and render without layout
  regression.
- Browser screenshot inspection found no form overflow or surrounding layout
  regression at the tested desktop viewport.
- Final completion pass on `/contact/` verified textarea at 160px across
  320/375/768/1024/1280/1440/1600/1920px after correcting utility specificity.
- Native checkbox runtime on `/customer/account/create/` resolves to 20×20px.
- English store switch resolves to `lang=en`, loads `en_US` CSS and preserves
  the 160px textarea contract.

## Deferred validation

- Select-specific chrome awaits an approved final Select master node.
- Standalone native-radio visual/keyboard QA remains pending because current
  representative product radios are component-owned swatch/rating controls.
- Safari/Firefox/Edge/iOS/Android and forced-colors matrices remain external QA.

## 2026-09-24 control-state revision

Checkbox `2174:32588` and Radiobutton `2174:30745` were read again from Figma
and compared with the compiled native-control foundation. The revision aligns:

- S/M/L control dimensions at 16/20/24px and mark dimensions per size.
- 4px Checkbox radius and fully rounded Radio geometry.
- brand-400 focused border with the exported 4px primary focus ring.
- brand-600 checked background retained on hover.
- component-specific checked and disabled mark colors from the master assets.
- dedicated Hyvä `role="switch"` geometry remains separate.

Implementation and validation record: `TASK-4JY7CV`.
