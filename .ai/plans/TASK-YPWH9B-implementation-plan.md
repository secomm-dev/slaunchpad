# Implementation Plan: COD Blacklist & Basic Risk Control (TASK-YPWH9B / LC-26)

## Metadata

| Field | Value |
|-------|-------|
| Ticket / Spec | TASK-YPWH9B / SPEC-TASK-YPWH9B |
| Specification | Full Spec — `.ai/specs/SPEC-TASK-YPWH9B-cod-risk-control.md` |
| Author | Claude (AI) |
| Reviewer (TL) | (user acting as SA/TL in chat — decisions confirmed 2026-09-21) |
| Workflow Mode | A |
| Date | 2026-09-21 |

## 1. Approach

MỘT module mới `Secomm_CodRisk`, additive thuần — không sửa file nào của module có sẵn *(cập nhật 2026-09-21: gộp plugin availability vào CodRisk, bỏ skeleton `Secomm_Cod` theo quyết định chủ dự án)*:

- **`Secomm_CodRisk`** — toàn bộ risk domain: contracts (Api/), phone normalizer, rule engine (RulePool qua DI virtual types, sortOrder cố định 10/20/30/40), persistence 5 bảng, audit writer, admin surface (menu, 3 grid + inspector, order-view section, config, ACL), i18n, **+ after-plugin trên `Magento\OfflinePayments\Model\CashOnDelivery::isAvailable()`**: enabled + phone valid + decision BLOCK → `false`; WARNING/ALLOW → result gốc. Kill-switch = config `enabled=No`.

Lựa chọn đã xét (đã chốt trong decisions D-01…D-14 + S-01…S-03): hook = plugin isAvailable (không preference), Spam đếm trên event store (không scan sales_order), reasons = config flags (không bảng riêng), override = order-level có bảng riêng, ACL = 1 resource, evaluation log chỉ WARNING/BLOCK, Order View evaluate live.

## 2. Files affected

| File | Change | Lý do |
|------|--------|-------|
| `app/code/Secomm/CodRisk/registration.php`, `etc/module.xml` | new | module skeleton |
| `app/code/Secomm/CodRisk/Api/*.php`, `Api/Data/*.php` | new | public contracts (spec §5.4) |
| `app/code/Secomm/CodRisk/Model/Phone/PhoneNormalizer.php` | new | E.164 VN (CR: identity) |
| `app/code/Secomm/CodRisk/Model/Config.php` | new | đọc config + validate threshold |
| `app/code/Secomm/CodRisk/etc/db_schema.xml`, `etc/db_schema_whitelist.json` | new | 4 bảng (spec §5.3) |
| `app/code/Secomm/CodRisk/Model/{CodRiskList,CodRiskEvent,CodRiskEvaluation,CodRiskOverride}.php` + `ResourceModel/*` + `Collection` | new | persistence |
| `app/code/Secomm/CodRisk/Model/Audit/AuditWriter.php` | new | audit CR (spec §16) |
| `app/code/Secomm/CodRisk/Model/Evaluation/{CodRiskEvaluator,RulePool,RuleResult}*.php` + `Rule/*.php` | new | rule engine + 4 rules |
| `app/code/Secomm/CodRisk/Model/RiskEventRecorder.php` | new | implement `RiskEventRecorderInterface` |
| `app/code/Secomm/CodRisk/etc/di.xml` | new | RulePool registration + preferences |
| `app/code/Secomm/CodRisk/etc/acl.xml`, `etc/adminhtml/menu.xml` | new | ACL 1 resource + menu Sales→COD Risk |
| `app/code/Secomm/CodRisk/etc/adminhtml/system.xml`, `etc/config.xml` | new | config groups + defaults |
| `app/code/Secomm/CodRisk/Controller/Adminhtml/Codrisk/*` | new | index/inspector/new/edit/save/deactivate/record/override |
| `app/code/Secomm/CodRisk/etc/adminhtml/routes.xml` | new | admin router |
| `app/code/Secomm/CodRisk/view/adminhtml/layout/*.xml`, `Ui/Component/*`, `view/adminhtml/ui_component/*.xml` | new | 3 grids + inspector + forms |
| `app/code/Secomm/CodRisk/view/adminhtml/layout/sales_order_view.xml` + `Block/Adminhtml/OrderView/*.php` + `ViewModel/*` + `templates/*.phtml` | new | section COD Risk (D-06 A) |
| `app/code/Secomm/CodRisk/i18n/en_US.csv`, `vi_VN.csv` | new | BR-001 |
| `app/code/Secomm/CodRisk/{README,CHANGELOG}.md` | new | project rule §7.2 |
| `app/code/Secomm/CodRisk/Plugin/CashOnDeliveryAvailabilityPlugin.php` | new | availability hook (S-01) — nằm trong CodRisk (gộp 1 module 2026-09-21) |
| `Test/Unit/*` (CodRisk) | new | unit normalizer + rules |

Không modify: `Magento_OfflinePayments`, Mageplaza OSC, module Secomm có sẵn, `app/etc/config.php` (enable module qua CLI như mọi module khác).

## 3. Steps (độc lập reviewable, theo thứ tự)

1. **Skeleton + contracts + normalizer** — risk: low — deps: none
   - registration/module.xml cả 2 module; Api contracts; DTO Decision/RuleResult/Context; PhoneNormalizer (bảng test spec nguồn §5.2)
   - verify: `php -l` toàn bộ; unit test normalizer chạy xanh
2. **DB + persistence + audit** — risk: medium (DB) — deps: 1
   - db_schema 5 bảng (list/event/evaluation/override + audit) + whitelist; models/resources/collections; AuditWriter
   - verify: `bin/magento setup:upgrade` không lỗi; bảng xuất hiện; whitelist khớp
3. **Rule engine + 4 rules + recorder** — risk: medium — deps: 2
   - di.xml RulePool (sortOrder 10/20/30/40); Blacklist/Spam/Allowlist/Historical; RiskEventRecorder (snapshot include)
   - verify: unit test precedence + từng rule (CR-001…CR-006, CR-008…CR-011)
4. **Availability plugin (CodRisk) + config + i18n** — risk: high (payment path) — deps: 3
   - after-plugin `isAvailable` có kill-switch; system.xml + config.xml defaults (validate block>warning); i18n 2 file
   - verify: `bin/magento cache:flush`; checkout COD ẩn/hiện theo data; enabled=No → hành vi gốc
5. **Admin surface** — risk: low — deps: 4
   - acl.xml (1 resource), menu.xml, routes, 3 grids (Risk Lists / Risk Events / Evaluations) + inspector page + form pages (add/edit list, record event, override)
   - verify: `php -l`; grid load, form save, ACL ẩn menu khi không cấp quyền
6. **Order View section + actions** — risk: medium (layout anchor) — deps: 5
   - `sales_order_view.xml` chèn section (anchor dưới payment, fallback cuối card Information — ghi kết quả vào ticket); ViewModel live-evaluate + override resolve; nút action theo bối cảnh
   - verify: visual admin; badge đúng 3 trạng thái; override ghi audit
7. **Tests + docs + QC handoff** — risk: low — deps: 6
   - unit tests (normalizer, rules, precedence); README/CHANGELOG 2 module; cập nhật project-context memory + estimation-tracking
   - verify: `vendor/bin/phpunit` scope CodRisk xanh; AI pre-review checklist §8.3

## 4. Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| COD ẩn nhầm khi plugin lỗi (checkout Tier-2) | high | try/catch quanh evaluate → fail-open (COD vẫn hiện) + log critical; kill-switch config; QC checkout end-to-end |
| OSC render payment lệch | medium | không sửa OSC; QC 1 checkout đầy đủ sau step 4 |
| setup:upgrade trên DB dev chung | medium | schema additive-only; ghi rollback (drop 4 bảng) vào CHANGELOG |
| Admin grid đụng hiệu năng order view | low | evaluate live là 4 query indexed theo phone; đo khi QC |

## 5. Test approach

- Unit: normalizer (format + invalid), 4 rules, precedence, override resolver, reason snapshot — PHPUnit theo Arrange-Act-Assert.
- Integration / QC (L3 bắt buộc — payment + DB): kịch bản Customer A→F (spec §9/DoD nguồn), guest=registered, prepaid regression, ACL, config validation.
- High-risk validation: checkout COD + Mollie/VNPay/MoMo/VietQR sau step 4 (Tier-2).

## 6. Out of scope

Xem spec §3 Out of Scope (bulk tools, carrier automation, quote override, OSC UI change, Orders grid column, auto-block 1 event).

## 7. Open questions / Escalation

- Anchor layout Order View (step 6) — nếu không đạt vị trí mockup thì fallback + note vào ticket (Tier-1 TL xem kết quả).
- Message customer-facing trên checkout (D-14 text đã có, chưa wire) — nếu business muốn hiện → ticket riêng chạm OSC (Tier-2).