# PROJECT_AI_BLUEPRINT — Secomm Launchpad (Magento 2.4.8 Hyvä, Fashion / Vietnam)

> **Single source of input** for generating this project's AI toolkit.
> Detected facts are marked `confirmed` (evidence in repo). Gaps the audit could not read from the
> repo are marked `[ASSUMPTION: ...]` or `[TBD]` and surfaced in §13/§14 — **do not invent beyond these**.
> SA/TL must review and approve (`document_status: approved`) before toolkit generation runs.

---

## Section 0: Document Metadata

```yaml
document_status: approved     # draft | reviewed | approved  ← APPROVED by stakeholder 2026-07-14
confidence_level: medium      # tech stack = high confidence; business/delivery = assumptions (flagged in §13)
created_by: agent-generated   # source-audit agent + chat
created_date: 2026-07-14
last_updated: 2026-07-14
reviewed_by: "stakeholder (interactive approval)"
toolkit_version: 4.0
```

---

## Section 1: Project Summary

```yaml
project_name: "Secomm Launchpad"
client_name: "[TBD — Secomm internal build or VN fashion client]"
project_type: "new-build"
brief_description: >
  New Magento 2.4.8-p5 storefront for a fashion/apparel retailer targeting the Vietnam market,
  built on the Hyvä 3.x theme with Tailwind CSS v4 (CSS-first config) and Magewire. The repo is
  freshly initialized (single commit) with two custom Hyvä child themes — Secomm/launchpad (primary)
  and Secomm/launchpad_fashion (variant scaffold) — bilingual vi_VN/en_US localization, VNPAY + Mollie
  payments, Mageplaza commerce suite (One Step Checkout, TableRate shipping, SocialLogin, SMTP,
  AbandonedCart, ExtraFee, DeliveryTime, Lookbook), and custom Secomm modules for hierarchical
  Vietnam address dropdowns.
business_objective: "[ASSUMPTION: Launch a fast, fully-localized (vi_VN) fashion eCommerce storefront on Magento 2.4.8 + Hyvä with Vietnam-native payment (VNPAY) and address support — confirm with stakeholder]"
timeline: "[TBD]"
team_size: "[TBD]"
workflow_mode: "A"
output_language: "vi"
```

---

## Section 2: Platform & Tech Stack

```yaml
platform: "magento"
stack_variant: "magento-hyva"
current_version: "2.4.8-p5"
target_version: "TBD"                 # new-build, not an upgrade
php_version: "8.2"                    # confirmed: platform supports ~8.2.0 || ~8.3.0 || ~8.4.0
database: "mysql 8.0"                 # local db `fashion_launchpad` @ 127.0.0.1; prod engine TBD
search_engine: "[ASSUMPTION: opensearch 2.x in production — ES8 + OpenSearch client libs present in vendor, but no engine configured in local env.php]"
frontend_framework: "hyva"            # Hyvä 3.x (default theme 1.5.2)
css_framework: "tailwind"             # Tailwind CSS v4 (^4.3.1), CSS-first config (no tailwind.config.js)
hosting: "[TBD — none configured; Bitbucket repo secomm-vn/slaunchpad]"
ci_cd: "[TBD — no pipeline committed; Bitbucket Pipelines is the natural fit]"
additional_services: "[ASSUMPTION: redis + varnish + opensearch in production; Magewire present; RabbitMQ NOT configured]"
```

---

## Section 3: Repository & Project Structure

```yaml
repo_url: "git@bitbucket.org:secomm-vn/slaunchpad.git"
branch_strategy: "development (active), master, development_fashion (remote-only)"
monorepo: false
key_directories:
  - "app/code/Secomm/        — Secomm_Base, AddressDropdown, VietNamAddress"
  - "app/code/Mageplaza/     — 16 Mageplaza modules (source-committed)"
  - "app/code/Vnpayment/     — Vnpayment_VNPAY payment gateway"
  - "app/design/frontend/Secomm/launchpad/          — primary Hyvä child theme (web/tailwind toolchain)"
  - "app/design/frontend/Secomm/launchpad_fashion/  — fashion variant (scaffolded)"
  - "app/etc/                — env.php (local dev), config.php, hyva-themes.json"
local_dev_setup: "local LAMP-style checkout (MAGE_MODE=developer, MySQL 127.0.0.1, file cache + file sessions). [TBD: no docker-compose committed]"
test_commands: "vendor/bin/phpunit"
```

---

## Section 4: Business Rules

```yaml
business_rules:
  # Localization
  - id: "BR-001"
    domain: "localization"
    description: "Storefront is bilingual vi_VN (primary) + en_US. vi_VN.csv + en_US.csv translation files present in Secomm modules."
    affected_areas: "all storefront templates, theme i18n"
    confidence: "confirmed"

  # Vietnam address entry
  - id: "BR-002"
    domain: "checkout"
    description: "Address fields are converted to AJAX-driven hierarchical dropdowns (country → state/province → city/district → sub-city/ward) via Secomm_AddressDropdown + Secomm_VietNamAddress data set (VN_Address.csv)."
    affected_areas: "checkout, customer address, admin address CRUD + import/export"
    confidence: "confirmed"

  # Payments
  - id: "BR-003"
    domain: "payment"
    description: "Active payment: Mollie (composer module, Hyvä compat bundle). VNPAY (custom Vnpayment_VNPAY, default active=0) + Braintree/PayPal (bundled) available."
    affected_areas: "checkout payment step, order placement, IPN/webhook (VNPAY Controller/Order/Ipn.php)"
    confidence: "confirmed"

  # Checkout
  - id: "BR-004"
    domain: "checkout"
    description: "Mageplaza One Step Checkout (Osc + OscPro + OscUltimate) replaces the default Magento checkout."
    affected_areas: "checkout layout/flow, cart"
    confidence: "confirmed"

  # Shipping
  - id: "BR-005"
    domain: "shipping"
    description: "Mageplaza TableRateShipping carrier (code 'mptablerate', default inactive) — volume/weight-based with dimensional attributes (length/width/height, shipping factor 5000)."
    affected_areas: "shipping calculation, cart, product attributes"
    confidence: "confirmed"

  # Fees / delivery
  - id: "BR-006"
    domain: "checkout"
    description: "Mageplaza ExtraFee (surcharge) + Mageplaza DeliveryTime (checkout delivery-time selection) are installed."
    affected_areas: "checkout totals, cart"
    confidence: "confirmed"

  # Business rules that need stakeholder confirmation
  - id: "BR-TBD-001"
    domain: "multi-store"
    description: "[ASSUMPTION: currently single-store (scope is DB-only, not exported to config.php). The launchpad_fashion theme + development_fashion branch suggest a possible second store/website — confirm multi-store intent.]"
    affected_areas: "store/website scope, theme assignment, catalog"
    confidence: "assumed"
```

---

## Section 5: Integrations

```yaml
integrations:
  - name: "Mollie Payments"
    type: "payment"
    direction: "bidirectional"
    protocol: "REST/HTTP"
    criticality: "high"
    authentication: "API key + webhook"
    rate_limits: "Per Mollie account"
    notes: "Composer-installed mollie/magento2 3.1.1 + Hyvä compat bundle. Active payment method."

  - name: "VNPAY"
    type: "payment"
    direction: "bidirectional"
    protocol: "HTTP (query redirect + IPN)"
    criticality: "high"
    authentication: "TmnCode + hash secret"
    rate_limits: "Not documented"
    notes: "Custom Vnpayment_VNPAY module (app/code). Controllers: Order/Pay, Order/Info, Order/Ipn. Default active=0 — enable + configure for VN market."

  - name: "Mageplaza TableRate Shipping"
    type: "shipping"
    direction: "outbound"
    protocol: "internal carrier"
    criticality: "medium"
    authentication: "n/a"
    notes: "Carrier code 'mptablerate'. Dimensional shipping (L/W/H, factor 5000). Default inactive."

  - name: "Mageplaza SMTP"
    type: "email"
    direction: "outbound"
    protocol: "SMTP"
    criticality: "medium"
    notes: "Transactional email relay. Daily log-clear cron."

  - name: "Mageplaza SocialLogin"
    type: "auth"
    direction: "bidirectional"
    protocol: "OAuth (provider-dependent)"
    criticality: "low"
    notes: "Social sign-in (SocialLogin + SocialLoginPro). Provider config TBD."

  - name: "Mageplaza AbandonedCart"
    type: "marketing"
    direction: "outbound"
    protocol: "internal + email"
    criticality: "low"
    notes: "Abandoned-cart recovery. Runs every minute (cron)."

  # NOT present (confirmed by audit — do not assume)
  # - No ERP/SAP/Odoo
  # - No Klaviyo/Mailchimp/Dotdigital
  # - No ElasticSuite/Smile (core Magento search only)
  # - No Amasty/Mirasvit/Wyomind
```

---

## Section 6: Architecture Notes

```yaml
architecture:
  description: >
    Monolithic Magento 2.4.8-p5 storefront with a Hyvä 3.x frontend (Tailwind CSS v4 CSS-first config,
    Alpine.js, Magewire 1.13). Two custom Hyvä child themes — Secomm/launchpad (primary, child of
    Hyva/default) and Secomm/launchpad_fashion (grandchild of launchpad, scaffolded). Custom code is
    concentrated in: 3 Secomm modules (base admin shell, Vietnam hierarchical address dropdown +
    data), Vnpayment_VNPAY gateway, and a committed Mageplaza commerce suite (checkout, shipping,
    marketing). Targets VN fashion retail, bilingual vi_VN/en_US.

  key_decisions:
    - "Hyvä 3.x instead of Luma for frontend performance (Tailwind + Alpine, minimal JS)"
    - "Tailwind CSS v4 with CSS-first config (@theme/@source in tailwind-source.css — no tailwind.config.js)"
    - "Mageplaza One Step Checkout (Osc/OscPro/OscUltimate) for the checkout flow"
    - "Mollie + VNPAY dual payment for international + Vietnam-native checkout"
    - "Magewire 1.13 for interactive server-driven components"
    - "Custom Secomm_AddressDropdown + VietNamAddress for accurate VN address capture"

  constraints:
    - "Local-dev checkout only: no Redis, Varnish, OpenSearch, RabbitMQ, or CI/CD configured in committed config (env.php = MAGE_MODE developer, file cache + file sessions)"
    - "Search engine not configured in code (ES8/OpenSearch client libs installed but inactive) — production search must be set up"
    - "Freshly initialized repo (single 'Initial commit') — no deploy/CI history, no production config templates"

  performance_requirements: "[TBD — confirm targets with stakeholder]"

  scalability_requirements: "[TBD — confirm peak load / multi-store intent with stakeholder]"
```

---

## Section 7A: Magento Context

```yaml
stack_context:
  type: "magento"

  custom_modules:
    - name: "Secomm_Base"
      purpose: "Admin menu shell ('Secomm' → 'CORE') + 'Secomm Extensions' system config tab; helper + plugins"
      risk_level: "low"
    - name: "Secomm_AddressDropdown"
      purpose: "Converts address text fields to AJAX hierarchical dropdowns (country/state/city/sub-city); admin CRUD + import/export; GraphQL schema"
      risk_level: "medium"
    - name: "Secomm_VietNamAddress"
      purpose: "Vietnam address data set (VN_Address.csv, VN_Address_2Level.csv) for AddressDropdown"
      risk_level: "low"
    - name: "Vnpayment_VNPAY"
      purpose: "VNPAY payment gateway — Pay/Info/IPN controllers, payment.xml/config.xml (default inactive)"
      risk_level: "high"
    - name: "Mageplaza_* (16 modules)"
      purpose: "Core, Osc/OscPro/OscUltimate (checkout), SocialLogin/Pro, Smtp, GeoIP, ExtraFee, DeliveryTime, TableRateShipping, QuickCart, Lookbook, AbandonedCart, ThankYouPage, BackendReindex"
      risk_level: "medium"

  theme_customization: >
    Hyvä child theme Secomm/launchpad (Tailwind v4, oklch design tokens primary/secondary, Hyva 3.x
    toolchain). Grandchild launchpad_fashion scaffolded but not yet overridden.

  overrides:
    - "Checkout fully replaced by Mageplaza One Step Checkout (Osc family)"
    - "Address entry overridden by Secomm_AddressDropdown (frontend + admin + GraphQL)"
    - "Hyvä theme-fallback + base-layout-reset + compat-module-fallback in place (Hyva compatibility layer)"

  checkout_customization: >
    Mageplaza OSC replaces default checkout. Adds ExtraFee (surcharge) + DeliveryTime selection.
    VN address dropdowns (BR-002). Payments: Mollie + VNPAY.

  multi_store: false
  stores:
    - "[ASSUMPTION: single store currently — scope is DB-only. launchpad_fashion theme + development_fashion branch may indicate a planned 2nd store; confirm with stakeholder]"

  cron_jobs:
    - "mageplaza_abandonedcart_cron — every minute"
    - "mageplaza_abandonedcart_report_indexer_cron — every minute"
    - "mageplaza_smtp_clear_log — daily 00:00"
    - "mageplaza_core_get_update — weekly (Sun 02:00)"
    - "mageplaza_core_process_feed — hourly"

  constraints:
    - "File cache + file sessions in local dev — production MUST configure Redis (cache + sessions) + Varnish (FPC)"
    - "Search engine not configured — production MUST configure OpenSearch (Magento 2.4.8 requires it)"
    - "Mageplaza AbandonedCart cron runs every minute — monitor cron throughput in production"

  composer_dependencies:
    - "magento/product-community-edition — 2.4.8-p5"
    - "hyva-themes/magento2-default-theme — 1.5.2 (Hyvä 3.x)"
    - "hyva-themes/magento2-theme-module — 1.5.2"
    - "hyva-themes/magento2-theme-fallback — 1.0.4"
    - "hyva-themes/magento2-luma-checkout — 1.1.7"
    - "hyva-themes/magento2-base-layout-reset — 2.0.5"
    - "mollie/magento2 — 3.1.1 (+ mollie/magento2-hyva-compatibility v3.1.0)"
    - "magewirephp/magewire — 1.13.3 (+ magewirephp/validation 1.0.1)"
    - "Mageplaza_* — 16 modules committed as source under app/code/Mageplaza (not composer)"
    - "tailwindcss — ^4.3.1 + @tailwindcss/cli ^4.3.1 (theme web/tailwind)"
```

---

## Section 8: Delivery Workflow

```yaml
delivery:
  workflow_mode: "A"                      # new-build, >16h, architecture impact
  sprint_cadence: "[TBD]"
  communication_channels:
    - "[TBD — Bitbucket (secomm-vn/slaunchpad) + internal]"
  deployment_frequency: "[TBD]"
  release_process: "[TBD — no CI/CD committed yet]"
```

---

## Section 9: AI Usage Rules

```yaml
ai_usage:
  allowed_tools:
    - "Claude Code"
    - "GitHub Copilot"
    - "Codex"                 # small tasks only
  escalation_areas:
    - "Payment logic (VNPAY custom gateway, Mollie)"
    - "Checkout customization (Mageplaza OSC, ExtraFee, DeliveryTime)"
    - "Order management"
    - "Address logic (Secomm_AddressDropdown + VN data)"
    - "Database schema changes"
    - "Security-sensitive code (payment secrets, IPN validation)"
  review_requirements:
    - "AI pre-review required before TL review"
    - "TL must review all AI-generated code"
    - "SA review required for payment/checkout/address changes"
  client_restrictions:
    - "[TBD]"
```

---

## Section 10: Testing & Quality

```yaml
testing:
  strategy: "mixed"
  test_frameworks:
    - "PHPUnit (dev dep present)"
    - "Magento Integration Tests (dev deps present)"
    - "Hyvä theme build check (tailwind build / build-prod)"
  qc_process: "[TBD — define QC on staging]"
  uat_process: "[TBD — client UAT on staging]"
  performance_testing: "[TBD]"
```

---

## Section 11: Deployment & Release

```yaml
deployment:
  environments:
    - "local (MAGE_MODE=developer, MySQL 127.0.0.1, file cache/sessions)"
    - "staging [TBD]"
    - "production [TBD]"
  deployment_method: "[TBD — Bitbucket Pipelines likely; not yet committed]"
  rollback_procedure: "[TBD]"
  pre_deployment_checklist:
    - "[TBD — define once CI/CD is set]"
  post_deployment_verification:
    - "[TBD]"
```

---

## Section 12: Known Issues & Risks

```yaml
known_issues:
  bugs:
    - "[none confirmed yet — freshly initialized repo]"

  high_risk_areas:
    - "Vnpayment_VNPAY — custom payment gateway (IPN validation, signature, default inactive) — needs enablement + security review before going live"
    - "Mageplaza One Step Checkout — complex flow replacing default checkout; risk on payment/address interaction"
    - "Secomm_AddressDropdown + VietNamAddress — custom address capture + data import; risk on data quality + GraphQL surface"
    - "Production infrastructure undefined — no Redis/Varnish/OpenSearch/CI configured in repo"

  legacy_code:
    - "[none — new build]"

  dependency_risks:
    - "Search engine not configured — Magento 2.4.8 requires OpenSearch/Elasticsearch; must be set before production"
    - "File cache + file sessions in committed env.php — acceptable for local dev only"
    - "Mageplaza modules committed as source (not composer) — version drift / update path to manage"
    - "Hyvä private Packagist (auth.json) — repo access depends on token validity"
```

---

## Section 13: Assumptions

```yaml
assumptions:
  - description: "Business objective = launch a fast, localized (vi_VN) fashion storefront with VN payment/address support"
    confidence: "assumed"
  - description: "Production stack includes Redis (cache+sessions), Varnish (FPC), OpenSearch — inferred from Magento 2.4.8 requirements; not in committed config"
    confidence: "assumed"
  - description: "CI/CD will be Bitbucket Pipelines (repo host is Bitbucket); not yet committed"
    confidence: "assumed"
  - description: "Currently single-store; launchpad_fashion + development_fashion suggest a planned 2nd store"
    confidence: "uncertain"
  - description: "PHP runtime = 8.2 (platform supports 8.2–8.4); exact runtime TBD"
    confidence: "assumed"
```

---

## Section 14: Open Questions

```yaml
open_questions:
  - question: "What is the client name and the precise business objective / KPIs for this storefront?"
    priority: "high"
    who_can_answer: "Stakeholder / TL"
    blocking: false
  - question: "Is multi-store intended (general store + fashion store), or is launchpad_fashion just a theme variant of one store?"
    priority: "high"
    who_can_answer: "Stakeholder / SA"
    blocking: true
  - question: "What is the production infrastructure (Redis, Varnish, OpenSearch, hosting provider)?"
    priority: "high"
    who_can_answer: "DevOps / SA"
    blocking: true
  - question: "What CI/CD + deployment/release process will be used (Bitbucket Pipelines?)?"
    priority: "medium"
    who_can_answer: "DevOps / TL"
    blocking: false
  - question: "Timeline, team size, sprint cadence?"
    priority: "medium"
    who_can_answer: "TL / PM"
    blocking: false
```

---

## Section 15: Generation Instructions

```yaml
generation:
  skills_to_include: "auto + magento-checkout-impact + magento-module-analysis + security-review"
  dev_skills_to_include: "auto"          # Magento (create-module/plugin/observer/db-schema) + Hyvä (alpine-component, tailwind-section)
  coding_rules_override:
    - "Tailwind CSS v4 — CSS-first config via @theme/@source in tailwind-source.css; DO NOT create a tailwind.config.js"
    - "Frontend components use Hyvä patterns: Alpine.js + Magewire 1.13; phtml-driven, no React/Vue"
    - "Custom module vendor prefixes: Secomm_ (project), Vnpayment_ (payment); Mageplaza_* are third-party (do not modify in place — extend via plugin/preference)"
    - "All new PHP targets PHP 8.2+ (8.2–8.4 compatible); strict_types + Magento coding standard"
    - "Storefront strings must be added to both vi_VN.csv and en_US.csv translation dictionaries"
  additional_project_context_sections: []
  custom_rules:
    - "Any change to Vnpayment_VNPAY (payment/IPN/signature) requires SA review (Tier 2 escalation)"
    - "Any change to Mageplaza OSC checkout flow requires end-to-end checkout QC + payment test"
    - "Address-related changes must validate the VN hierarchical dropdown (country→state→city→sub-city) end-to-end"
    - "Do NOT commit production env.php / Redis / OpenSearch credentials — local-dev config only in repo"
  skip_sections: ["7B", "7C", "7D"]      # not Shopify / Headless / Laravel
```

---

> **Validation:** Validated against `blueprint/blueprint-validation-checklist.md` at generation time.
> **Status:** This document is `approved` (stakeholder approval 2026-07-14). Toolkit generation proceeded on this approval. Open items remain tracked in §13/§14 and surface as decisions in `.ai/runtime/DECISION_QUEUE.md`.
