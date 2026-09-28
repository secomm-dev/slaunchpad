---
id: DEC-TASKDFGFZ9-001
title: 'COD payment identification ownership → Secomm_Cod (module mới nhỏ; ShippingCore decouple hoàn toàn; clean removal không adapter; config path move + copy-only dest-wins migration)'
status: accepted             # user acting as SA/TL — plan approval 2026-09-23 (2 câu hỏi AskUserQuestion: tạo Secomm_Cod + xoá sạch interface cũ)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-09-23
created: 2026-09-23
last_verified: 2026-09-23
verified_against_commit:
supersedes:
  - 'address-shipping.md Revision v4 §4.1 (mệnh đề ownership COD identification thuộc Secomm_ShippingCore) — CHỈ mệnh đề ownership; các fence khác của §4.1 (identification only, không framework) giữ nguyên'
  - 'address-shipping.md Rev v10 §22 forbidden edges Secomm_Ghn -X-> Secomm_Cod / Secomm_Ghtk -X-> Secomm_Cod (thay bằng required edge Ghtk → Secomm_Cod)'
superseded_by:
  - 'DEC-TASKDFGFZ9-002 (amount decision ownership — đảo mệnh đề "amount conversion carrier-owned")'
  - 'DEC-TASKDFGFZ9-003 (items 3/5/6 — config surface + DataPatch removed; GHN consume Secomm_Cod)'
work_items: [TASK-DFGFZ9]
---

# Decision Record: COD Payment Identification Ownership → Secomm_Cod

## Status

Accepted (2026-09-23 — user acting as SA/TL, plan approval sau audit). Tier-2 review toàn bộ
change set trước merge. Plan: `/home/secomm/.claude/plans/h-y-audit-v-x-polished-pixel.md`.

## Decision Type

Architecture (amendment address-shipping.md Rev v10 → v11)

## Decisions

1. **Owner duy nhất mới: `Secomm_Cod`** — module nhỏ `app/code/Secomm/Cod/` sở hữu COD payment
   identification: contract `Secomm\Cod\Api\CodPaymentMethodResolverInterface::isCod(string): bool`
   + `Model\ConfiguredCodPaymentMethodResolver` + config. Evidence consumer thật (audit
   2026-09-23): đúng 1 call-site production `Secomm_Ghtk\Model\OrderSubmit\DefaultCodAmountResolver:41`
   (shipment-submit → `pick_money`); ShippingCore KHÔNG tự consume; `CODRisk` là consumer tương lai
   (chưa tồn tại — khi tạo phải dùng resolver chung, không tự đọc list riêng). Đây là đảo ngược có
   chủ đích của Rev v10 §4.1/§28 ("KHÔNG có Secomm_Cod").

2. **ShippingCore decouple hoàn toàn** — xoá `Api/Cod/`, `Model/Cod/`, `Test/Unit/Model/Cod/`,
   preference di.xml, group `cod` (system.xml), default `cod` (config.xml). `Secomm_ShippingCore`
   KHÔNG khai báo dependency `Secomm_Cod` (orchestration của nó không cần resolver — verify grep).
   Forbidden edges mới: `Secomm_ShippingCore -X-> Secomm_Cod`, `Secomm_Cod -X-> carriers`.
   KHÔNG dùng ObjectManager/dependency ngầm để né khai báo.

3. **Carrier dependency tường minh** — carrier trực tiếp cần nhận diện COD khai báo dependency
   `Secomm_Cod` (module.xml sequence) và gọi contract. Hiện tại: chỉ `Secomm_Ghtk`. GHN giữ
   deliberately-no-COD (không thêm edge). `Secomm_GiaoHangNhanh` legacy (disabled) ngoài scope.

4. **Clean removal, KHÔNG adapter** — interface cũ `Secomm\ShippingCore\Api\Cod\CodPaymentMethodResolverInterface`
   xoá trong cùng change. Lý do: consumer duy nhất đổi typehint cùng PR; TASK-STC3NB chưa qua TL
   review (interface chưa từng là API ổn định); ShippingCore/Ghtk không có composer.json (không có
   consumer ngoài repo); adapter buộc implicit coupling hoặc zombie interface. Điểm loại bỏ: n/a
   (xoá ngay).

5. **Config path + hiển thị** — `secomm_shippingcore/cod/payment_methods` →
   `secomm_cod/payment_identification/payment_methods`; section `secomm_cod` "COD Settings" trên
   tab `sales` (sortOrder 75, global-only showInDefault), group `payment_identification` "Payment
   Identification", field `payment_methods` (canRestore=1). ACL section-level `Secomm_Cod::config`
   nest `Magento_Config::config` (tiền lệ `Secomm_ShippingCore::config`/`Secomm_AiCommerce::config`;
   tự-consistent, không reproduce mismatch ShippingCore). ShippingCore giữ group `physical`, section
   vẫn tab `sales`.

6. **Migration copy-only dest-wins** — DataPatch `MigrateLegacyCodPaymentMethodConfig`
   (non-revertable có chủ đích): copy mọi row path cũ → mới preserve `(scope, scope_id)`; skip khi
   source trim-rỗng; skip khi dest đã có row (KHÔNG BAO GIỜ ghi đè lựa chọn đã cấu hình ở dest, kể
   cả khi source rỗng); KHÔNG xoá legacy rows (rollback safety — inert vì không còn reader); cache
   clean `config` chỉ khi có copy.

## Consequences

- Amount conversion (collect) giữ carrier-owned (DEC-SL016-001 §4-5) — chỉ ownership identification
  chuyển; ranh giới carrier-không-quyết-COD-policy giữ nguyên.
- Row config cũ tồn tại vĩnh viễn (không cleanup patch) — inert, ghi trong USER_GUIDE/CHANGELOG.
- Future CODRisk/Ahamove-COD/GHN cod_amount phải inject `Secomm\Cod\Api\CodPaymentMethodResolverInterface`,
  không tự đọc config list.
- Arch doc v11: §4.1 owner mới; §8/§21/§22/§28/§29 edges + checklist cập nhật.
