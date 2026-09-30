# Secomm Launchpad — Header, Menu and Footer Design Analysis

Status: `Header approved for implementation under TASK-DWW34M; Menu/Footer remain analysis and estimate only`

External reference: `SLP-246`

Related foundation: [Global Style input](secomm-launchpad-global-style-foundation-input.md)

Design file: `LAUNCHPAD-CORE` (`5MpBw9VaNgFdct3UDWABuV`)

This document records verified Figma structure, current Magento/Hyvä integration
surfaces, proposed component boundaries and implementation estimates. It does
not move Header, Menu or Footer into the Global Style scope and does not
authorize implementation.

## 1. Source nodes

### Header

| View | Canonical node | Verified structure |
|---|---|---|
| Desktop | `2151:9517` — Header component set | Two 1440×64 contextual variants; Figma models 400px side regions, but the approved responsive contract uses equal flexible regions around a centered 120×18 logo |
| Mobile/Tablet | `2115:5849` — Header component set | Mobile 375×64 with 8px outer padding; Tablet 640×64 with 32px outer padding; menu/logo left and Search/Wishlist/Cart right |

The component set models light/dark visual treatment, but it does not define the
runtime trigger between the two treatments. Tablet light coverage is not visibly
complete in the component-set overview.

### Menu

| View | Canonical node | Verified structure |
|---|---|---|
| Desktop trigger/navigation | Within `2151:9517` | `SHOP` plus three dropdown labels, 24px item gap, Label M typography |
| Desktop mega menu | `2151:11236` | 1440px full-width panel; 40/32px outer padding; feature image/copy plus three navigation regions separated by dividers |
| Mobile/Tablet mega menu | `2117:5971` | Drawer variants at 375px and 640px; logo/close header, hierarchical navigation, settings and expandable language section |

Desktop selected parent items use a semantic brand-soft surface. Mobile/Tablet
uses a full-height drawer and nested disclosure/navigation patterns rather than
the Desktop multi-column panel.

### Footer

| Region | Desktop | Mobile | Runtime decision |
|---|---|---|---|
| Newsletter | `2151:17272` | `2151:17058` | Visible on both; horizontal Desktop, stacked Mobile |
| Main links | `2151:17280` | `2151:17066` | Four Desktop columns; four-section Mobile accordion |
| Payment | `2174:34539` | none | Visible Desktop; hidden Mobile |
| Bottom | `2151:17305` | `2151:17095` | Horizontal Desktop; centered stack Mobile |

Verified Footer details:

- Desktop main links: four equal columns, 40px horizontal and 32px vertical
  padding, 24px heading-to-list gap and 12px link gap.
- Mobile main links: 24px horizontal/32px vertical padding, one representative
  section open, section dividers and 20px chevrons.
- Desktop Payment contains one accreditation image and eight 48×32/35px payment
  marks with 16px gaps.
- Bottom Footer uses Body 3; legal links have a 16px gap. Copyright year should be
  runtime-derived rather than copied as a fixed `2026` value.

## 2. Current implementation surface

- Theme `Secomm/launchpad` currently has no Header/Footer template override and
  inherits Hyvä default templates.
- `app/code/Secomm/ThemeHelper` does not currently exist and must be introduced
  when Header implementation starts.
- Hyvä already supplies Logo, Search, Customer, Wishlist, Minicart/Cart Drawer,
  store/language switchers, Newsletter and Footer block contracts. These should
  be composed or overridden, not reimplemented as static markup.
- `Snowdog_Menu` is installed and its `default_hyva.xml` replaces Hyvä's native
  desktop/mobile menu with `hyva-topmenu-desktop` and `hyva-topmenu-mobile`.
- Snowdog also exposes `hyva-menu-footer`; the shipped templates and Tailwind
  source are generic and require theme-local overrides for this design.
- Snowdog menu content can use Magento category nodes, so catalog URLs and labels
  remain runtime data rather than copied Figma text.
- Existing Snowdog templates already include Alpine focus trapping, Escape
  handling and body scroll locking. Adapt these behaviors rather than discarding
  them when applying the design.

## 3. Recommended boundaries

### Header

- One semantic `<header>` shell with responsive composition.
- Keep Magento/Hyvä child blocks for logo, search, customer/session, wishlist,
  minicart and language data.
- Homepage initially uses the dark/on-hero treatment. Other pages initially use
  the light treatment. On scroll, the Header becomes sticky like the approved
  POC; the Homepage transitions to the light sticky treatment.
- Sticky behavior is controlled by a Magento Admin Yes/No setting at Store View
  scope. The proposed path is **Stores → Configuration → Secomm → Theme → Header
  → Enable Sticky Header**, backed by `secomm_theme/header/sticky_enabled` and
  enabled by default to match the approved design.
- Magento-side theme configuration belongs to `Secomm_ThemeHelper`. The module
  may later host other reusable theme configuration/providers and PHP helpers,
  but must not own presentation markup or CSS. Theme templates consume a typed
  config provider/ViewModel; they must not call ObjectManager or config storage
  directly.
- Customer/account is rendered next to Wishlist even though the current design
  omitted it; Design will add the missing visual reference later.
- Header owns contextual visual treatment and sticky behavior; Menu owns menu
  hierarchy/panels/drawer.
- Use exact Figma SVG assets through the Hyvä custom-icon workflow. Do not draw
  or substitute glyphs based only on icon names.

### Menu

- Snowdog Admin remains the runtime authoring source.
- Magento catalog categories remain the source for category nodes and URLs.
- The feature image/copy and other mega-menu managed content are authored in
  Snowdog rather than hard-coded into the theme.
- Theme-local Snowdog templates render the Desktop mega menu and Mobile/Tablet
  drawer from the same managed hierarchy while allowing device-specific layout.
- Desktop uses the approved hybrid activation contract: hover is a pointer
  enhancement; click, Enter and Space provide deterministic toggle behavior.
  The panel closes on delayed mouseleave, outside click or Escape. Hover is not
  the only activation mechanism, and keyboard focus remains managed.

### Footer

- Magento Newsletter block owns form action, form key, validation and response.
- Footer content follows the approved POC architecture and is loaded from CMS
  blocks. Presentation and responsive behavior remain in theme templates; link
  labels and destinations are not hard-coded in PHTML.
- Payment/accreditation content should be a separately managed Footer region;
  render on Desktop only. Exact Figma assets must be exported and committed.
- Bottom copyright uses Magento runtime copyright/year; legal destinations are
  managed content, not hard-coded URLs.
- Use the column layout at `min-width: 768px`; below 768px use the accordion.

## 4. Design gaps and decisions before implementation

| ID | Decision/gap | Resolution / recommendation | Owner | Status |
|---|---|---|---|---|
| HMF-01 | What switches Header dark/light treatments and sticky state? | Homepage starts dark/on-hero; other pages start light. Scroll activates sticky Header like the POC; Homepage sticky treatment becomes light. | Designer/TL | `Resolved` |
| HMF-02 | Tablet light variant is not clearly represented | Apply the same semantic light/dark context contract at all responsive sizes; Design may add the missing visual reference later. | Designer | `Resolved with documented design gap` |
| HMF-03 | Header design omits a visible customer/account action | Render Customer/account next to Wishlist; Designer will update the source design later. | Designer/Product | `Resolved with documented design gap` |
| HMF-04 | What user action opens/closes the Desktop mega menu? | Approved hybrid contract: hover enhancement plus click/Enter/Space; close via delayed mouseleave, outside click or Escape. Hover is not the only trigger. | TL | `Resolved` |
| HMF-05 | Mapping between feature image/copy and menu data | Manage the feature content in Snowdog with the relevant menu hierarchy; do not hard-code Figma copy/assets in PHTML. | TL/Content | `Resolved` |
| HMF-06 | Footer link authoring source | Follow the POC: CMS blocks provide managed Footer content; theme templates own responsive presentation. | TL/Content | `Resolved` |
| HMF-07 | Breakpoint between Footer columns and accordion | Columns at 768px and above; accordion below 768px. | Designer/TL | `Resolved` |
| HMF-08 | Newsletter success/error/loading and invalid-email states are absent | Reuse Magento/Hyvä behavior and Global Style Form tokens; propose missing visuals under WCAG 2.2 AA. | Frontend Developer | `Open` |
| HMF-09 | Payment/accreditation alt text and destination behavior are absent | Classify decorative vs linked assets before export and implementation. | Content/Designer | `Open` |
| HMF-10 | How is sticky Header enabled or disabled? | Add Store View scoped Admin config `secomm_theme/header/sticky_enabled` in new reusable module `Secomm_ThemeHelper`; default enabled. | Tech Lead | `Resolved` |
| HMF-11 | Fixed 400px Desktop side regions do not scale with real menu/action content | Use `minmax(0,1fr) / 120px / minmax(0,1fr)` with 24px column gaps. Keep level 1 on one scrollable row with a hidden scrollbar; logo centering is invariant. | Frontend Developer/TL | `Approved refinement` |

## 5. Work-point estimate

Every point is independently loggable and is limited to 1–4 hours. Estimates
include implementation and point-level self-test, while the final QA points cover
cross-component/runtime validation.

### Header — 20–27 hours

| ID | Work point / deliverable | Estimate |
|---|---|---:|
| HD-01 | Map Hyvä layout blocks and create theme-local Header composition | 3h |
| HD-02 | Implement Desktop/Mobile/Tablet responsive shell and spacing | 3–4h |
| HD-03 | Export/integrate logo and Header custom icon assets | 2–3h |
| HD-04 | Integrate language, wishlist, cart/minicart and customer/session rules | 3–4h |
| HD-05 | Adapt Search trigger/overlay to the Header contract | 2–3h |
| HD-06 | Scaffold `Secomm_ThemeHelper`, add ACL/system/default config and typed sticky-config provider | 2–3h |
| HD-07 | Implement config-gated contextual/sticky treatment and transitions | 2–3h |
| HD-08 | Header responsive, config on/off, keyboard, screen-reader and locale QA | 3–4h |

### Menu — 22–29 hours

| ID | Work point / deliverable | Estimate |
|---|---|---:|
| MN-01 | Define Snowdog hierarchy/data contract using real catalog categories | 3–4h |
| MN-02 | Implement Desktop primary navigation trigger row | 2–3h |
| MN-03 | Implement Desktop full-width mega-menu visual composition | 4h |
| MN-04 | Implement approved hybrid Desktop activation, close and transition behavior | 2–3h |
| MN-05 | Implement Mobile/Tablet drawer shell and responsive sizing | 3–4h |
| MN-06 | Implement nested navigation, settings and language disclosure | 3–4h |
| MN-07 | Preserve focus trap, Escape, scroll lock and reduced motion | 2–3h |
| MN-08 | Validate category depths, empty/long labels, locales and devices | 3–4h |

### Footer — 18–25 hours

| ID | Work point / deliverable | Estimate |
|---|---|---:|
| FT-01 | Define CMS block identifiers/deployment contract and wire Footer regions | 2–3h |
| FT-02 | Implement Magento Newsletter Desktop/Mobile composition and states | 3–4h |
| FT-03 | Implement four-column Desktop CMS-managed link navigation | 2–3h |
| FT-04 | Implement Mobile accessible accordion using the same content source | 3–4h |
| FT-05 | Export/integrate Desktop accreditation and payment assets | 3–4h |
| FT-06 | Implement responsive copyright and managed legal links | 2–3h |
| FT-07 | Footer responsive, content, keyboard and locale QA | 3–4h |

### Estimate summary

| Scope | Estimate | Confidence |
|---|---:|---|
| Global Style Foundation | 43–61h | Medium until Select/Button gates close |
| Header | 20–27h | Medium |
| Menu | 22–29h | Medium |
| Footer | 18–25h | Medium |
| **Combined implementation** | **103–142h** | **Medium-low** |

The combined range excludes content entry/translation ownership, catalog setup,
backend feature changes and unrelated local-environment repair. Re-baseline after
HMF-08, HMF-09 and the remaining Global Style Button/Select gates are approved.

## 6. Validation contract

- Use the Global Style locale, browser and viewport matrix.
- Validate Header/Menu together because focus, stacking, body scroll and sticky
  behavior cross component boundaries.
- Validate sticky Header with Admin config enabled and disabled at Store View
  scope, including config inheritance/fallback and cache refresh behavior.
- Validate Footer at 320/375/768/1024/1280/1440/1920px, including long localized
  labels and empty/extra managed link groups.
- Exercise logged-in/logged-out, wishlist enabled/disabled, cart empty/non-empty,
  one/multiple store languages and menu trees with two/three levels.
- Record Figma comparison evidence separately for 375px and 1440px canonical
  frames; intermediate widths are responsive contract validation, not invented
  pixel-perfect source frames.
