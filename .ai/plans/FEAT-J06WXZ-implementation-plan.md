# Implementation Plan: Secomm UI Widgets

## Metadata

| Field | Value |
|-------|-------|
| Ticket / Spec | FEAT-J06WXZ / SPEC-FEAT-J06WXZ |
| Specification | [SPEC-FEAT-J06WXZ-secomm-ui-widgets.md](../specs/SPEC-FEAT-J06WXZ-secomm-ui-widgets.md) — FULL, VALID |
| Solution Design | [FEAT-J06WXZ-solution-design.md](../specs/FEAT-J06WXZ-solution-design.md) |
| Component Matrix | [FEAT-J06WXZ-component-eligibility-matrix.md](../specs/FEAT-J06WXZ-component-eligibility-matrix.md) |
| Author | Tuấn Lê |
| Reviewer (TL) | Tuấn Lê — Approved 2026-08-24 |
| Workflow Mode | A |
| Date | 2026-08-24 |

## 1. Approach

Triển khai theo vertical-slice-first. Foundation chỉ tạo contract tối thiểu cần cho `Banner A`; dynamic Admin form và payload codec phải được proof qua ba editor surface trước khi nhân rộng. Batch 1 dùng content/manual collections; Batch 2 chỉ bắt đầu sau khi manual catalog provider và cache identities pass integration tests.

Plan tuân `DEC-FEATJ06WXZ-001`: explicit registry, Secomm-owned runtime templates, manual chooser, Hyvä-only, no DB/new runtime dependency baseline.

## 2. Planned files/areas

| Area | Change type | Purpose |
|---|---|---|
| `app/code/Secomm/UiWidget/registration.php` | new | Register module |
| `app/code/Secomm/UiWidget/etc/module.xml` | new | Module dependency sequencing |
| `app/code/Secomm/UiWidget/etc/widget.xml` | new | One `Secomm UI` widget type/common shell |
| `app/code/Secomm/UiWidget/etc/di.xml` | new | Registry/providers/contracts wiring |
| `app/code/Secomm/UiWidget/etc/component.xml` or equivalent | new | Explicit component definitions; exact format proofed in TASK-S6QPEY |
| `app/code/Secomm/UiWidget/Api/` | new | Stable internal contracts |
| `app/code/Secomm/UiWidget/Model/Component/` | new | Registry/schema/validation/template resolution |
| `app/code/Secomm/UiWidget/Model/Parameter/` | new | Versioned payload codec/normalizer |
| `app/code/Secomm/UiWidget/Model/DataProvider/` | new | Manual product/category providers |
| `app/code/Secomm/UiWidget/Block/Widget/` | new | Magento widget rendering block |
| `app/code/Secomm/UiWidget/Block/Adminhtml/Widget/` | new | Dynamic options renderer/choosers |
| `app/code/Secomm/UiWidget/Controller/Adminhtml/` | new if required | Authorized dynamic schema/chooser endpoint |
| `app/code/Secomm/UiWidget/view/adminhtml/` | new | Admin form templates/JS/layout |
| `app/code/Secomm/UiWidget/view/frontend/templates/components/` | new | Imported/adapted default component templates |
| `app/code/Secomm/UiWidget/view/frontend/web/tailwind/` | new if required | Component CSS/source integration |
| `app/code/Secomm/UiWidget/i18n/{vi_VN,en_US}.csv` | new | Required translations |
| `app/code/Secomm/UiWidget/Test/` | new | Unit/integration tests |
| `app/code/Secomm/UiWidget/{README,CHANGELOG}.md` | new | Module ownership/usage/upstream docs |
| `app/design/frontend/Secomm/<test-theme>/Secomm_UiWidget/` | new/modify in final compatibility task | Prove theme override contract only |
| `.ai/project-context/04`, `09`, project component index | modify at closure | Register new module/capability after implementation |

Không sửa `vendor/hyva-themes/hyva-ui` hoặc Magento core.

## 3. Tasks and sequence

1. **TASK-S6QPEY — Build module foundation and component contracts** — risk: medium.
   - Module scaffold, widget declaration, registry/schema/provenance/template resolver contracts.
   - Verify module enable/DI/XML and registry rejection paths.
2. **TASK-P0BP58 — Build dynamic Admin form and parameter codec** — risk: high; depends task 1.
   - Dynamic schema fields, media/repeater primitives, versioned payload/limits, editor round-trip proof.
3. **TASK-JN2SH6 — Deliver Banner A vertical slice** — risk: medium; depends tasks 1–2.
   - Port/adapt Banner A, remove demo data, Admin→storefront→theme override proof.
   - Gate: no batch expansion until accepted.
4. **TASK-ZQ9ZE1 — Deliver Batch 1 content components** — risk: medium; depends task 3.
   - Implement B1 matrix in small reviewable commits/slices using proven contracts.
   - Correction gate approved 2026-08-26: audit and restore Hyvä UI 2.8.0 visual parity for the five delivered components before `card_a` or any further B1 expansion.
   - Correction order: Banner A → Banner B → Banner C → Accordion A → Generic Content A → two-theme/regression QA.
5. **TASK-BE8X4X — Build manual catalog providers and cache contract** — risk: medium/high; depends task 1, can start after registry stable.
   - Product/category chooser, batch load, ordered output, store filters, identities.
   - **Deferred after Batch 1 by user phase decision; remains proposed follow-up.**
6. **TASK-TMRZT1 — Deliver Batch 2 context-backed components** — risk: high; depends tasks 4–5.
   - Implement only B2 variants whose context gates pass; defer unsafe product review/accorditabs cleanly.
   - **Deferred after Batch 1 by user phase decision; remains proposed follow-up.**
7. **TASK-XY9RZF — Validate Batch 1 two-theme compatibility and close Phase 1 documentation** — risk: medium; depends tasks 1–4. Tasks 5–6 apply only when Batch 2 resumes.
   - Production Tailwind build, multi-instance Alpine, security/accessibility/cache regression, docs/context/QC handoff.

## 4. Regression risks

| Risk | Severity | Mitigation |
|---|---|---|
| Widget popup/PageBuilder corrupts repeated payload | high | codec limits + three-surface round-trip fixtures before Batch 1 |
| Arbitrary template/XSS via tampered directive | high | explicit registry, server validation, context escaping, negative tests |
| Tailwind classes missing in production | high | module source registration + `build-prod` + visual regression |
| Product/category N+1 or stale output | high | batch collections, ordered map, entity identities/store-aware cache keys |
| Theme override schema drift | high | schema remains module-owned; two-theme compatibility fixtures |
| Alpine collisions with multiple widgets | medium | instance IDs/state isolation + multi-instance browser tests |
| Component scope expands to system replacements | medium | matrix is scope gate; excluded list requires new spec/decision |
| Upstream update overwrites local code | medium | provenance/manual import; no vendor runtime/template include |
| Local adaptation drifts from Hyvä UI layout/style | high | preserve upstream layout/classes/behaviour; whitelist only data/security/semantic differences; screenshot and computed-layout parity gate at 390/768/1440 |

## 5. Test approach

- Unit: registry merge/duplicate rejection, schema validation, codec limits/versioning, template resolver, ID reordering.
- Magento integration: widget declaration/filter rendering, CMS directive round-trip, Admin persistence, product/category filtering/order/cache identities.
- Frontend/QC: CMS Page/Block/PageBuilder, two same widgets, responsive/a11y, two Hyvä themes, FPC refresh.
- Visual parity: render identical fixtures against the pinned Hyvä UI source and the Secomm template; compare element order, bounding boxes, spacing, typography, media crop, overlay and interaction states at 390px, 768px and 1440px.
- Security: tampered component/template/payload, XSS URLs/text/rich content, oversized/deep repeater payload, unauthorized Admin endpoint.
- Build: PHP lint/coding standard/XML validation/DI compile plus Tailwind `npm run build-prod` for affected themes.
- No L3 checkout/payment validation required unless implementation unexpectedly touches those areas; such discovery blocks and returns to spec.

## 6. Out of scope

- Luma/non-Hyvä.
- Conditions builder.
- Header/footer/minicart/gallery/filter/system replacement components.
- Runtime `Hyva_Widgets`/CMS Tailwind JIT dependency.
- Automatic upstream sync.
- DB schema, REST/GraphQL API.

## 7. Open questions / escalation

- Plan approved by TL Tuấn Lê on 2026-08-24.
- TASK-P0BP58 proof must report selected Admin renderer mechanism and codec limits; if DB/new dependency becomes necessary, stop for architecture/spec review.
- Product theme used for second-theme gate must be named before TASK-XY9RZF execution.
- Map/newsletter/product review components may require additional dependency/security review; failure to meet gates means defer, not workaround.
