# TASK-3R6X8E Implementation Plan — Scaffold Secomm_Promotion + Secomm_PromotionMaxDiscount

| Field | Value |
|---|---|
| Specification | tickets/TASK-3R6X8E-scaffold-promotion-modules.md (`## Mini Spec`, embedded — ID TASK-3R6X8E) · canonical parent specs/SPEC-FEAT-JKZM68-promotion-max-discount.md (FULL, VALID) §3 |

> **Mode C** · Tier 1 · Status: **Retro-canonical** — distilled 2026-08-19 từ work đã Dev complete (evidence: [TASK-3R6X8E-evidence](../runtime/evidence/TASK-3R6X8E/TASK-3R6X8E-evidence.md)); mọi task đánh dấu theo trạng thái thực.
> Boundary nguồn: DEC-FEATJKZM68-001 §5 (module boundary) + FEAT-JKZM68 spec §3.

---

## PART 1 — ANALYSIS (pre-implementation)

### 1.1 Quyết định cần làm trước khi scaffold

| Câu hỏi | Kết luận | Nguồn |
|---|---|---|
| Base module chứa gì? | **Đúng 5 files, 0 PHP class** — anchor thuần; contracts chỉ rút lên khi có promotion module thứ 2 (YAGNI) | DEC-FEATJKZM68-001 §5 |
| Có composer.json không? | **Có** — 6/10 Secomm module hiện có đều có; format theo `secomm/module-addressdropdown` + `secomm/module-viet-nam-address` (nhất quán, mô tả dependency dù chạy qua `app/code`) | audit `app/code/Secomm/*/composer.json` |
| Format header/style? | Theo precedent mới nhất `Secomm_ShippingCore` (TASK-NDASAD): header `@author Secomm Team`, module.xml có comment tham chiếu DEC | `app/code/Secomm/ShippingCore/` |
| Sequence của feature module? | `Secomm_Promotion` + `Magento_SalesRule` + `Magento_Quote` + `Magento_Sales` (Magento_Sales cho downstream order/invoice/CM của TASK-4HYX6Y) | DEC-FEATJKZM68-001 §5, spec §3 |
| Dir tree trống track thế nào trong git? | `.gitkeep` trong `Model/`, `Plugin/`, `Test/` | convention thực dụng |

### 1.2 Rủi ro môi trường (đã xảy ra thật)

`module:enable`/`setup:di:compile` cần DB; MySQL trong WSL2 env down giữa chừng (SQLSTATE 2002, không mysqld/service/docker) → runtime verify tách phase, ghi blocker trung thực trong evidence, resume khi DB up. **Lesson:** check DB connectivity trước khi hứa runtime AC.

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | Base `Secomm_Promotion` tối giản | composer.json · registration.php · etc/module.xml (no sequence) · README.md · CHANGELOG.md | ✅ done |
| 2 | Skeleton `Secomm_PromotionMaxDiscount` | composer.json (5 requires) · registration.php · etc/module.xml (4 sequence) · README.md · CHANGELOG.md | ✅ done |
| 3 | i18n placeholders + dir tree | i18n/vi_VN.csv · i18n/en_US.csv · Model/.gitkeep · Plugin/.gitkeep · Test/.gitkeep | ✅ done |
| 4 | Static checks | `simplexml_load_file` × 2 · `php -l` × 2 · JSON parse × 2 | ✅ green |
| 5 | Runtime verify (AC-1/AC-5) | `module:enable` (config.php L452–453) · `setup:upgrade` "completed successfully" · `setup:di:compile` success 0 warn · `grep generated/metadata` = 0 ref | ✅ green (sau MySQL up lại) |
| 6 | Records | ticket AC tick + pre-review §8.3 · evidence file · plan này | ✅ done |

### Sequence & gating

```
Task 1–3 (scaffold)  →  Task 4 (static)  →  Task 5 (runtime; bị block MySQL ~nửa ngày, resume xanh)
                                                      ↓
                                        AI Pre-review §8.3  ✅  →  TL code review (Tier 1)  ⏳ HUMAN
                                                      ↓
                                        Release gate: không có thay đổi runtime →
                                        merge cùng nhánh feature FEAT-JKZM68 (không deploy riêng)
```

## Remaining steps (human / next tickets)

1. **TL code review (Tier 1)** — diff scope: 14 files mới (`app/code/Secomm/{Promotion,PromotionMaxDiscount}/`) + `app/etc/config.php` (enable) + `.ai/` records. Không file `vendor/`.
2. **TASK-33J3RP** (data model — Mode B, Tier-2 DB) kế tiếp pipeline; TASK-67GGPR/024 chạy song song sau TASK-5H8WKE.
3. Release checklist gộp ở cấp FEAT-JKZM68 (TASK-HPK1WZ), không riêng TASK-3R6X8E.

## Verification summary (đối chiếu Mini-Spec)

| Mini-Spec clause | Bằng chứng |
|---|---|
| Goal — foundation, zero runtime behavior | evidence "Runtime verification" + "No DI wiring" |
| Expected Behavior — enable/upgrade/compile xanh, totals không đổi | AC-1/AC-5 byline trong ticket |
| Constraints — không vendor change, precedent format, BR-001, README/CHANGELOG | pre-review checklist §8.3 |
| Out of Scope — không business logic/contracts | AC-2/AC-3 file listing |
