# Feature Spec: COD Blacklist & Basic Risk Control (LC-26)

> Filename: `SPEC-TASK-YPWH9B-cod-risk-control.md` — canonical naming theo `rules/spec-first.md` §Spec Naming (P2A collision-safe ID mint 2026-09-21).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-YPWH9B |
| Feature ID | NONE (standalone task — external PM id: LC-26) |
| Specification Level | FULL |
| Author | Claude (AI) — theo spec nguồn `lc26_cod_risk_p1_p2_launchpad_spec.md` + `LC-26-open-decisions.md` |
| Status | Approved (user acting as SA/TL in chat 2026-09-21 — toàn bộ decisions D-01…D-14 đã confirm; precedent TASK-VG4T3N) |
| Date | 2026-09-21 |
| Related Ticket(s) | TASK-YPWH9B |
| Workflow Mode | A (chạm payment availability + DB schema → generic risk category) |

## 1. Objective

Giảm rủi ro **bom hàng COD** cho merchant bằng rule đơn giản, deterministic, dễ vận hành: chặn/blacklist SĐT, đếm historical risk event theo SĐT, phát hiện spam burst — kết quả chỉ ảnh hưởng **khả năng sử dụng COD**, không chạm payment khác hay checkout.

## 2. User Stories

### US-001: As a merchant, I want to blacklist a phone so that it cannot use COD

- [ ] Given phone có record Blacklist active, When customer checkout với phone đó, Then COD không hiển thị; các payment khác bình thường.

### US-002: As an admin/CS, I want to record a risk event by hand from Order View so that history hình thành mà không cần carrier automation

- [ ] Given order COD chưa có risk, When CS bấm Record Risk Event và chọn Reason, Then event được lưu (source ADMIN/CUSTOMER, snapshot include flag); nếu count chạm warning/block threshold thì các checkout sau bị WARNING/BLOCK tương ứng.

### US-003: As a CS, I want to look up a phone quickly so that tôi trả lời được khách "vì sao không thấy COD"

- [ ] Given phone bất kỳ định dạng, When CS nhập vào Phone Inspector, Then thấy decision hiện tại + lý do + historical count + list records + events + audit tại 1 màn hình.

### US-004: As an admin, I want to override the decision for ONE order so that false positive được cứu có audit trail

- [ ] Given order hiển thị BLOCK, When Admin (có quyền) override với reason bắt buộc, Then order được đánh dấu override (base BLOCK → ALLOW) có audit; checkout của phone đó KHÔNG đổi.

### US-005: As an admin, I want warning/block visible nội bộ so that CS dispatcher thấy khi xử lý đơn

- [ ] Given order COD với phone đang WARNING/BLOCK, When mở Order View, Then badge + section COD Risk hiển thị trạng thái hiện tại của phone (live evaluate), kèm nút action theo bối cảnh.

## System Behaviour

### Decision pipeline (deterministic, thứ tự business precedence cố định ở code/DI — không phải admin config)

```text
context: normalized shipping phone (+ E.164 VN, ví dụ +84901234567) + website
  10 BlacklistRule   — active list row type=BLOCK → terminal BLOCK
  20 SpamOrderRule   — bật qua config; số event customer-attributable trong window ≥ threshold → terminal BLOCK
  30 AllowlistRule   — active list row type=ALLOW → terminal ALLOW (chỉ bypass Historical)
  40 HistoricalRule  — count event include=Yes trong lookback+website:
                        < warning → ALLOW · [warning, block) → WARNING · ≥ block → BLOCK
base decision → order-level override (nếu order có override record) → effective decision
```

- Allowlist KHÔNG bypass Blacklist/Spam. Spam KHÔNG tự insert Blacklist.
- Invalid/missing phone → KHÔNG phải risk BLOCK (address validation concern); evaluator trả ALLOW với lý do `no_phone` (không log evaluation).
- Cùng context + cùng risk state → cùng decision (deterministic). Evaluation chỉ log WARNING/BLOCK.
- WARNING: COD vẫn available, chỉ admin thấy. BLOCK: COD unavailable, payment khác không đổi.

### Risk identity & phone normalization

- Primary identity = normalized shipping phone dạng `+84XXXXXXXXX` (E.164 VN). Không dùng customer_id/email/device/IP.
- Guest và registered cùng phone → cùng kết quả.
- Behavior reference: `Secomm_Tracking` `UserDataHasher::normalizePhone()` (E.164 VN); implementation viết mới trong CodRisk (module boundary).

### Reason catalog & include snapshot

10 reason mặc định (spec nguồn §10.2); flag `Include in Historical Count` config được theo website scope; giá trị được **snapshot** vào event lúc ghi — đổi config sau không đổi nghĩa event cũ. Customer-attributable mặc định: REFUSED_DELIVERY, UNREACHABLE_CUSTOMER = Yes; còn lại (CUSTOMER_CANCELLATION trước xác nhận, merchant/system reasons…) = No, admin đổi được.

### Spam Order (D-03 — giữ P1, config bật/tắt)

- Định nghĩa P1: số event **customer-attributable** (reason set config, default: REFUSED_DELIVERY, UNREACHABLE_CUSTOMER, CUSTOMER_CANCELLATION) của cùng phone+website trong window (default 2 days) ≥ threshold (default 10) → BLOCK.
- Đếm trên **event store đã index** (phone+website+date), KHÔNG full-scan `sales_order` (performance rule §19).
- COD confirmation point (đã chốt S-01): default COD không có bước confirm riêng → confirmation = thời điểm order placed.

### Order View integration (D-06 phương án A + D-07)

- Section "COD Risk" inline trong tab Information, dưới khu Payment; **luôn hiển thị trên mọi order COD** (kể cả ALLOW).
- Hiển thị: effective decision, matched source, historical count, spam match, list state, events liên quan.
- Nút theo bối cảnh: Record Risk Event (luôn), Add to Blacklist / Add to Allowlist (khi chưa có record active tương ứng), Deactivate Blacklist (khi đang active), Allow COD for this order = Override (chỉ khi BLOCK).
- Override chỉ order-level (D-04), bắt buộc chọn Reason, có audit (admin, timestamp, base→effective).

### Admin surface (D-10, D-13)

- Menu `Sales → COD Risk`: Phone Inspector · Risk Lists (1 grid chung type BLOCK/ALLOW) · Risk Events · Evaluations · Audit Log.
- ACL: **1 resource duy nhất** `Secomm_CodRisk::manage` full quyền (không phân role/action).
- Config `Stores → Config → Secomm → COD Risk`: enabled; lookback 180; warning 2; block 3 (validate block > warning ≥ 1, lookback > 0); spam enable/window/threshold/reasons; customer-facing message text (dự phòng — P1 chưa wire vào OSC).

## 3. Scope

### In Scope

- Module mới `Secomm_CodRisk`: contracts (Api), rule engine, normalizer, **availability plugin cho `Magento\OfflinePayments\Model\CashOnDelivery`** (gộp 1 module 2026-09-21), persistence (5 bảng), admin UI (menu/grid/form/inspector/order-view section), config, ACL, i18n (en_US + vi_VN), README/CHANGELOG.
- Risk event recording: nguồn ADMIN (record tay từ Order View) + CUSTOMER (self-identify qua form) + seam `RiskEventRecorderInterface` cho carrier tương lai.
- Audit ghi cho: list/event/override thay đổi + config threshold thay đổi.

### Out of Scope

- Bulk CSV import/export (P2). Mass action Orders grid (D-11 optional — KHÔNG làm trong P1 đầu).
- Carrier webhook/status mapping tự động (P2). Quote-level override (P2 — D-04).
- Đổi UI Mageplaza OSC / hiển thị message customer-facing trên checkout (Tier-2 checkout — tách ticket riêng nếu cần).
- Cột COD Risk trên Orders grid (D-09 — P2). Review queue, analytics, ML (P2).
- Auto-block từ 1 event duy nhất (D-08 — không làm).

## 4. Business Rules

| Rule ID | Rule | Test Approach |
|---------|------|---------------|
| CR-001 | Blacklist active → BLOCK; chỉ ảnh hưởng COD | Unit + QC checkout |
| CR-002 | Allowlist chỉ bypass Historical; không bypass Blacklist/Spam | Unit |
| CR-003 | Precedence Blacklist > Spam > Allowlist > Historical, cố định ở DI | Unit |
| CR-004 | Historical count: event include-snapshot=Yes, cùng phone+website, trong lookback | Unit |
| CR-005 | Warning [w, b) → COD vẫn available; ≥ b → COD unavailable | Unit + QC |
| CR-006 | Event ghi tay không set trực tiếp decision — chỉ cộng count (D-08) | Unit |
| CR-007 | Override chỉ order-level, có audit, không tự add Allowlist/xóa Blacklist (D-04) | Unit + QC |
| CR-008 | Invalid phone không tạo BLOCK (validation concern) | Unit |
| CR-009 | Guest = registered cùng phone → cùng decision | Unit |
| CR-010 | Evaluation chỉ log WARNING/BLOCK | Unit |
| CR-011 | Lookup indexed theo phone+website+date; không full-scan sales_order | Code review + EXPLAIN |
| CR-012 | ACL 1 resource `Secomm_CodRisk::manage` | QC admin |

## 5. Technical Approach

### 5.1 Architecture Impact

MỘT module mới (additive, không sửa module có sẵn) — *(cập nhật 2026-09-21 theo quyết định chủ dự án: gộp availability hook vào CodRisk, bỏ skeleton `Secomm_Cod`)*:
- `Secomm_CodRisk` — owns risk domain toàn bộ **+ plugin availability cho COD** (Magento default `CashOnDelivery`). Boundary vẫn giữ: risk logic không nằm trong payment module — chiều ngược lại, risk module plugin vào payment availability; core payment logic không đổi.

Flow: `CashOnDelivery::isAvailable()` → plugin `Secomm\CodRisk\Plugin\CashOnDeliveryAvailabilityPlugin` → `CodRiskEvaluatorInterface` → RulePool → base decision → (order-level override chỉ áp cho display/record ở admin, không áp cho checkout quote).

### 5.2 Implementation Notes

- RulePool đăng ký qua `di.xml` virtual types với `sortOrder` (10/20/30/40) — thêm rule mới không sửa evaluator (AD-03).
- PHP 8.2+ `strict_types`, constructor DI, không ObjectManager, không logic trong controller (delegate service) — theo `AGENTS.md §7.2`.
- Spam đếm trên `secomm_cod_risk_event` (index phone+website+created_at) — không scan sales_order.
- Order View section: ViewModel evaluate live (S-03 — ALLOW không log nên phải live) + hiển thị note thời điểm evaluate.
- Storefront strings vào CẢ `vi_VN.csv` và `en_US.csv` (BR-001).

### 5.3 Database Changes

5 bảng mới qua `db_schema.xml` (+ whitelist) — additive, không đụng bảng có sẵn *(cập nhật 2026-09-21 khi implement: thêm `secomm_cod_risk_audit` để phục vụ Audit Log — spec §In Scope đã yêu cầu audit cho list/event/override/config, bảng này là chỗ lưu; đã ghi trong plan step 2)*:

- `secomm_cod_risk_list` — entity_id, normalized_phone (unique theo phone+website+type-active semantic, index), list_type ALLOW|BLOCK, website_id, reason, note, source, effective_from, effective_to (nullable), is_active, created_by (admin username), created_at, updated_at.
- `secomm_cod_risk_event` — entity_id, normalized_phone, website_id, order_id?, quote_id?, customer_id?, reason_code, source CUSTOMER|ADMIN|SYSTEM|CARRIER|IMPORT, include_snapshot, note, created_at. Index (normalized_phone, website_id, created_at).
- `secomm_cod_risk_evaluation` — entity_id, normalized_phone, website_id, decision WARNING|BLOCK, matched_rule, historical_count, spam_matched, order_id?, quote_id?, created_at. Chỉ insert WARNING/BLOCK (CR-010).
- `secomm_cod_risk_override` — entity_id, order_id, normalized_phone, base_decision, override_decision, reason, note, admin_username, created_at.
- `secomm_cod_risk_audit` — entity_id, entity_type (list/event/override/config), entity_id_ref, action, old_value, new_value, admin_username, note, created_at.

### 5.4 API Changes

Public contracts (nội bộ project, PSR): `CodRiskEvaluatorInterface`, `RiskEventRecorderInterface`, `CodRiskRuleInterface`, `CodRiskContextInterface`, `CodRiskDecisionInterface`. Không GraphQL/REST public trong P1.

### 5.5 Integration Impact

- `Magento_OfflinePayments` — plugin `afterIsAvailable` (after-plugin, không đổi logic gốc; chỉ chặn khi enabled + BLOCK).
- Mageplaza OSC — không sửa; OSC render payment list qua availability chuẩn nên tự respect. Verify QC end-to-end checkout (Tier-2 note).

## 6. UI/UX

Mockup tham chiếu: `LC-26-admin-ux-mockup.html` (repo root — review-only, không import vào `.ai`). Admin forms implement bằng trang form chuẩn Magento (thay modal trong mockup — cùng UX, pattern chuẩn project). Order View section = phương án A inline (D-06).

## 7. Dependencies

- `Magento_OfflinePayments` (core, đã enabled) — dependency trực tiếp của `Secomm_CodRisk` kể từ khi plugin availability được gộp vào module (2026-09-21).
- `Secomm_Tracking` — chỉ là **behavior reference** cho normalizer, không dependency code.

## 8. Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Plugin availability đụng checkout flow (Tier-2 payment) | M | H | After-plugin thuần, kill-switch config enabled=No; QC end-to-end checkout COD + prepaid |
| Spam đếm event thay vì scan sales_order lệch kỳ vọng BA | M | M | Ghi rõ định nghĩa trong spec + admin help text; config window/threshold chỉnh được |
| Order View layout anchor lệch mockup ( Magento info.phtml render position) | M | L | Verify visual sau khi implement; fallback vị trí cuối card Information |
| DB schema mới trên env chia sẻ | L | M | Additive-only; whitelist đầy đủ; setup:upgrade có rollback (drop 4 bảng) |

## 9. Test Approach

- Unit: normalizer (format bảng spec §5.2), từng rule, precedence, override resolver, reason snapshot.
- Integration/QC (L3 — payment): checkout COD với từng trạng thái ALLOW/WARNING/BLOCK + prepaid regression (VietQR/VNPay/MoMo/Mollie); guest + registered cùng phone; DoD demo theo spec nguồn §27 (Customer A→F).
- QC admin: CRUD list, record event, inspector, override, ACL, config validation.

## 10. Assumptions

- [x] COD = Magento default `CashOnDelivery`; availability hook nằm trong `Secomm_CodRisk` (gộp 1 module theo quyết định chủ dự án 2026-09-21 — ban đầu dự kiến skeleton `Secomm_Cod`).
- [x] COD confirmation point = order placed (không có bước confirm riêng).

## 11. Open Questions

- [ ] Fallback anchor Order View (nếu không chèn được ngay dưới Payment) — quyết định tại implement, ghi vào ticket.

## 12. Estimation

| Task | Estimate |
|------|----------|
| Skeleton + contracts + normalizer | 4h |
| DB schema + models/resource + audit writer | 4h |
| Rule engine + 4 rules | 5h |
| Availability plugin (CodRisk) + config + i18n | 3h |
| Admin: menu/ACL/grids (3) + inspector | 8h |
| Order View section + actions (record/list/override) | 5h |
| Tests + QC hỗ trợ + docs | 2h |
| **Total** | **31h** (khớp budget nguồn — không thêm scope nào ngoài decisions đã chốt) |

## Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| SA/TL | (user acting in chat — D-01…D-14 confirm) | 2026-09-21 | Approved |
| PM | — | — | — |