---
id: FEAT-001
title: Dual-theme (Luma + Hyva) AddressDropdown — MODULE layer (SL-001)
mode: A                      # original ticket Mode A — Tier 2 GraphQL surface
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/addressdropdown-hyva.md
risk: high
status: done                 # customer form + cart + cleanup done; AC-011 (resolver) deferred
created: 2026-07-16
updated: 2026-07-21
ticket_ref: SL-001           # legacy ticket — consolidated here (read-only)
decisions:                   # LINK to decision store — do not restate (legacy ADRs live in DECISIONS.md)
  - DEC-7
  - DEC-8
  - DEC-9
decision_assessment: material
# Knowledge-consolidation contract (RM-07)
components:
  - CMP-ADDR                 # placeholder stable ID (COMPONENT_INDEX lands in Phase 1c)
source_areas:
  - app/code/Secomm/AddressDropdown/view/frontend/templates/address/edit.phtml
  - app/code/Secomm/AddressDropdown/view/frontend/templates/hyva/address/edit.phtml
  - app/code/Secomm/AddressDropdown/view/frontend/layout/customer_address_form.xml
  - app/code/Secomm/AddressDropdown/view/frontend/layout/hyva_customer_address_form.xml
  - app/code/Secomm/AddressDropdown/view/frontend/layout/checkout_cart_index.xml
  - app/code/Secomm/AddressDropdown/view/frontend/requirejs-config.js
  - app/code/Secomm/AddressDropdown/view/frontend/web/js/address-dropdown.js
  - app/code/Secomm/AddressDropdown/view/frontend/web/js/view/cart/shipping-estimation-mixin.js
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: true   # AC-011 deferred (Q1)
verified_against_commit: 1afdfc8   # repo HEAD; note: module source partially untracked (see Implementation Notes)
last_verified: 2026-07-21
supersedes: []               # consolidates SL-001 legacy artifacts (ticket/spec/plan/testcase)
---

# Feature Record: Dual-theme (Luma + Hyva) AddressDropdown — MODULE layer

<!-- CANONICAL RECORD (Phase 1a) — consolidates legacy SL-001 ticket + spec + plan + testcase. -->
<!-- Legacy sources below are read-only / superseded; this record is the single source of truth for SL-001. -->
<!-- Raw evidence → .ai/runtime/evidence/FEAT-001/ (Phase 1a permissive; runtime carve-out is Phase 1d). -->

## Context

Migrate the **generic frontend surfaces** of `Secomm_AddressDropdown` (customer address form + cart shipping estimation) to be **dual-theme** (Luma native jQuery/Knockout + Hyva native Alpine/Magewire). MODULE layer only — reusable, theme-agnostic, **no OSC coupling** (OSC integration → SL-002 / Launchpad). Backend (`Api/`, `Model`, `Setup`, import, admin UI) untouched. Satisfies BR-002 (module portion) + BR-001 (i18n vi/en) on both themes.

**Scope boundary (DEC-8):** module owns generic Magento-native surfaces; OSC coupling forbidden in module → belongs to Launchpad (SL-002). Dead `Secomm_Ahamave` ref removed (AC-010).

**Risk:** Tier 2 (`Secomm_AddressDropdown` GraphQL surface — §12) → SA/TL escalation.

Legacy sources consolidated (read-only): [tickets/SL-001](../../tickets/SL-001-apply-hyva-theme-addressdropdown.md) · [specs/addressdropdown-hyva](../../specs/addressdropdown-hyva.md) · [plans/SL-001](../../plans/SL-001-implementation-plan.md) · [testcases SL-001 portion](../../testcases/SL-001-SL-002-testcases.md).

## Requirements

Acceptance criteria (from spec, the authoritative/newer source — dual-theme). QC verifies BR-002 (cascade) + BR-001 (i18n) on **both Luma + Hyva**.

- **AC-001** (Customer form — dual-theme routing): theme=Hyva → `hyva/address/edit.phtml` (Alpine + GraphQL); theme=Luma → `edit.phtml` (jQuery + requirejs `directoryAddressDropdownUpdater`). Switch via `hyva_customer_address_form` handle (DEC-9). (BR-002)
- **AC-001a** (Cascade — both themes): Country=VN → region[tỉnh] → city[phường/xã] cascade on both themes; sub_city hidden (3-tier VN); save persists `city`/`sub_city`. (BR-002)
- **AC-005** (Cart estimation — dual-theme): Luma = Knockout mixin (`cart/shipping-estimation-mixin.js`); Hyva = native `php-cart/shipping.phtml` (region-based). Estimate updates on both.
- **AC-006** (No leak on Hyva): on Hyva storefront, module's Luma files (requirejs/Knockout) do NOT load (inert); on Luma they are active (intentional). Customer form on Hyva = pure Alpine `hyva/address/edit.phtml`.
- **AC-007** (i18n — BR-001, both themes): both templates use the same generic `__()` keys → `Secomm_VietNamAddress` dict translates consistently (Province/City/Ward/Commune · Tỉnh/Thành phố/Phường/Xã).
- **AC-008** (Tailwind v4 — Hyva only): `hyva/address/edit.phtml` uses Tailwind CSS-first `@theme`/`@source`; no `tailwind.config.js`. (Luma uses classic `styles.css`.)
- **AC-009** (Regression — admin/backend): admin CRUD City/Region/SubCity/Country + CSV import/export intact.
- **AC-010** (Ahamave cleanup): dead `Secomm_Ahamave/...` ref removed from `requirejs-config.js` (DEC-8) — both themes.
- **AC-011** (Performance — no N+1 on cascade): ⛔ **DEFERRED (Q1)** — resolver hardening (Tier 2 SA). See Known Limitations.
- **AC-012** (Default-checkout — Luma path): Luma `checkout_index_index.xml` (Knockout) active on Luma default-checkout; inert on Hyva/OSC.
- **AC-013** (Module-enable gating): `ifconfig="address/general/enable"` — when disabled, both themes use default Magento form (no VN dropdown).

## Approach & Decisions

**DEC-7 — Strategy B (Hyvä-native)** *(refined by DEC-9)*: Alpine.js + `.phtml` + Tailwind v4 for Hyva surface. Original plan (2026-07-16) read this as Hyva-only.

**DEC-8 — Reusability boundary**: module owns generic Magento-native surfaces; OSC coupling forbidden in module → Launchpad (SL-002).

**DEC-9 — Dual-theme** (accepted 2026-07-17, supersedes the Hyva-only reading of DEC-7): module supports BOTH Luma (native jQuery/Knockout) AND Hyva (native Alpine). Theme switch via `hyva_` layout handle — **zero PHP** (no `Block/Customer/Address/Edit.php`, no `Helper/Theme.php`, no `di.xml` preference). Layout controls template selection; both templates gated by `ifconfig`.

> **Drift reconciliation (why this record supersedes the plan):** the legacy **plan** (2026-07-16, pre-DEC-9) is Hyva-only and says *"delete Luma JS"*. The **spec** (2026-07-17, post-DEC-9) is dual-theme and restores Luma files (active on Luma, inert on Hyva) + adds AC-001a / AC-013. **Spec supersedes plan.** This record codifies the dual-theme outcome: Luma files are **restored, not deleted**; both AC sets are merged (ticket AC-001..012 ∪ spec AC-001a/013).

**Architecture choices:** dual template in module (`edit.phtml` Luma + `hyva/address/edit.phtml` Hyva); layout-handle routing (`hyva_` handle wins on Hyva); data layer reused (GraphQL `GetListCity`/`GetListSubCity` + customer-data `city-data` + core Magento GraphQL Country/Region); cart = Knockout mixin (Luma) / native php-cart (Hyva). Block = core `Magento\Customer\Block\Address\Edit` (no preference, no `getTemplate` override).

## Implementation Notes

**Done** (per spec status 2026-07-17): customer form (both templates) + cart estimation (both) + Ahamave cleanup.

**Files (summary — see `source_areas` frontmatter for the canonical list):**
- Customer form: `templates/address/edit.phtml` (Luma) + `templates/hyva/address/edit.phtml` (Hyva); layouts `customer_address_form.xml` + `hyva_customer_address_form.xml` (both `ifconfig`).
- Cart: `layout/checkout_cart_index.xml` (Luma Knockout jsLayout) + `web/js/view/cart/shipping-estimation-mixin.js`; Hyva uses native `Magento_Checkout::php-cart/shipping.phtml` (no module template).
- requirejs: `view/frontend/requirejs-config.js` (Luma map + mixins; **Ahamave ref removed**).
- Luma JS restored: `web/js/address-dropdown.js` + cart mixin (from lsoul sibling / dangling blobs).
- i18n: `Secomm_VietNamAddress/i18n/{vi,en}*.csv` + generic `Secomm_AddressDropdown/i18n/en_US.csv` (admin only — no storefront label keys, no conflict).
- **Removed (DEC-9):** `Block/Customer/Address/Edit.php` + `Helper/Theme.php` + `di.xml` preference (no PHP theme detection).
- **NOT affected:** `view/adminhtml/*`, `Api/` / `Model` / `Setup` / import, default-checkout code, OSC.

**Steps (from plan, reconciled to dual-theme):** (1) resolver hardening — ⛔ deferred Q1; (2) Magewire/Alpine component for Hyva form; (3) customer form rewrite (both templates + layout routing); (4) cart rewrite (Luma mixin + Hyva native); (5) cleanup (Ahamave ref + restore Luma files); (6) i18n + Tailwind v4.

> ⚠️ **Provenance:** the module source is partially **untracked in git** (per spec). Luma files were restored from an lsoul sibling / dangling blobs (`git cat-file -p <sha>`). `verified_against_commit` reflects repo HEAD, not a module-only commit.

## Test Summary

**TC-001..TC-016** (from `testcases/SL-001-SL-002-testcases.md`, SL-001 portion). Common preconditions: theme active, VN 3-tier data via `InstallVietNamAddressPatch`/`VN_Address_2Level.csv`, locales vi_VN + en_US.

- **A. Customer-form cascade:** TC-001 new address cascade · TC-002 edit pre-fill · TC-003 save persist DB (Tier 2) · TC-004 region-change reset · TC-005 GraphQL fail-graceful — **PASS** (both themes)
- **B. i18n:** TC-006 vi labels · TC-007 en labels · TC-008 generic CSV no storefront labels · TC-009 vi placeholder bug-fix — **PASS**
- **C. Structure/cleanup:** TC-010 no RequireJS leak (Hyva) · TC-011 Ahamave gone · TC-012 default-checkout dormant · TC-013 Tailwind no purge — **PASS**
- **D. Admin regression:** TC-014 CRUD + CSV import/export — **PASS**
- **E. ⛔ BLOCKED:** TC-015 cart AC-005 step 4 (done in impl, TC closed) · TC-016 resolver AC-011 — **DEFERRED (Q1)**

> Raw test output → `.ai/runtime/evidence/FEAT-001/` (runtime; Phase 1d carve-out pending).

## Compatibility Conclusions

- **Themes:** Luma ✅ / Hyvä ✅ (dual-theme is the point of DEC-9)
- **Browsers:** not browser-specific (server-rendered templates + Alpine/jQuery) — verified on project default browsers
- **Modules affected:** `Secomm_AddressDropdown` (frontend surfaces only); `Secomm_VietNamAddress` (data, unchanged); `Secomm_Ahamave` (dead ref removed)
- **API contracts:** GraphQL `GetListCity`/`GetListSubCity` reused as-is (deprecated, `@cache(false)` — Q1); no contract change
- **Upgrade notes:** none blocking; if Hyvä major-version upgrade → re-verify `hyva_` handle load-order (hyva_ wins over base)

## Known Limitations

- **AC-011 / Q1 deferred:** resolver `GetListCity`/`GetListSubCity` are `@deprecated` + `@cache(false)` → potential N+1 on cascade. Hardening (undeprecate + cache) is Tier 2 SA work, deferred out of SL-001.
- **Luma path untestable on Hyva-only project** — QC needs a Luma theme/client to verify AC-001a/AC-005/AC-012 Luma side.
- **Module partially untracked in git** — provenance via lsoul sibling / dangling blobs.

## References

- Legacy sources (read-only): [tickets/SL-001](../../tickets/SL-001-apply-hyva-theme-addressdropdown.md) · [specs/addressdropdown-hyva](../../specs/addressdropdown-hyva.md) · [plans/SL-001](../../plans/SL-001-implementation-plan.md) · [testcases (SL-001 portion)](../../testcases/SL-001-SL-002-testcases.md)
- Decisions: DEC-7, DEC-8, DEC-9 — in `project-context/memory/DECISIONS.md` (ADR store; canonical decision files land in `.ai/records/decisions/` for new decisions per Phase 1a)
- Business rules: BR-001 (i18n), BR-002 (address cascade) — `project-context/02_BUSINESS_RULES.md`
- Related record: SL-002 (Launchpad OSC integration) — pending consolidation (spec `_TBD_`)
- Toolkit version: v4.0 · Generator ref: Phase 1a pass 2026-07-21
