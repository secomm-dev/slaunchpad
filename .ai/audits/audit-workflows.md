# Audit Workflows

Các audit có cấu trúc chạy bởi [Project Auditor](../role-guides/auditor.md) (và SA/TL khi cần) để assess một project cross-cutting — không phải per-PR, mà pattern, drift, compliance, và readiness. Mỗi audit objective, evidence-based, và produce severity-ranked finding với recommended next action.

Audit **reference** existing skill và checklist thay vì duplicate chúng: một audit hỏi "discipline có được follow không, và systemic state là gì?", trong khi skill/checklist execute per-item check.

---

## How to Run an Audit

### Severity Level

| Level | Meaning | Action |
|-------|---------|--------|
| **S0 — Critical** | Risk production / revenue / security / data-integrity; hoặc một Hard Gate đang bị violate | Stop / block; fix trước khi proceed; escalate Tier 2 |
| **S1 — High** | Có khả năng incident hoặc significant delivery risk nếu không address | Fix sprint này; TL own |
| **S2 — Medium** | Risk quality/maintainability; tích lũy theo thời gian | Plan vào 1–2 sprint kế tiếp |
| **S3 — Low / Info** | Improvement opportunity; không có near-term risk | Track trong backlog / retro |

### Standard Output Format

Mọi audit produce một report theo shape này:

```markdown
## {Audit Name} Audit — {project} — {date}

### Verdict
{HEALTHY / ATTENTION REQUIRED / AT RISK / NOT READY} — một dòng

### Findings
- [S0] {finding} — evidence: {file/PR/data} — owner: {role} — action: {next step}
- [S1] {finding} — evidence: … — owner: … — action: …
- [S2] …

### What's working
- {positive pattern để giữ}

### Recommended next action (ranked)
1. {action priority cao nhất}
2. …

### Artifact reviewed
- {file/PR/data source}
```

### Running Rule

- Evidence-based: mọi finding cite một file, PR, hoặc data point. Không opinion không evidence.
- Severity trước volume: focus S0/S1 + systemic pattern, không phải nitpick list.
- Không duplicate QC/TL per-item review — audit là cross-cutting.
- AI draft report; Auditor (human) confirm severity weighting và verdict.

---

## 1. Architecture Audit

**Objective**: Detect architecture drift, integration risk, module coupling, và tech debt so với design đã document.

**Input**: `AGENTS.md`; `project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md`, `04_CUSTOM_MODULES_AND_CODE_AREAS.md`, `05_API_CONTRACTS.md`; `DECISIONS.md`; source code.

**Check (cross-ref)**:
- Code có match `03`/`04`, hoặc đã drift? ([headless-api-contract-review](../skills-source/headless-api-contract-review/SKILL.md) cho contract drift)
- Integration contract trong `05` còn accurate? Có integration nào undocumented?
- Module/app coupling: cyclic dependency, god-class, cross-cutting change cần cho simple feature
- Architecture decision có record trong `DECISIONS.md`, hoặc make ad-hoc?
- Tech debt: deprecated API, unmaintained dependency (cross-ref `project-context/06`)

**Severity guidance**: S0 = architecture flaw data-integrity/security; S1 = integration có khả năng break; S2 = coupling slow delivery.

**Next action**: file decision as ADR; schedule refactor; update `03`/`04`/`06`.

---

## 2. Code Quality Audit

**Objective**: Assess maintainability, standard adherence, và recurring defect pattern across codebase (không phải một PR).

**Input**: PR gần đây; `project-context/CODING_RULES.md`; **`.ai/project-context/engineering-standards/`**; `AGENTS.md` §7.2; bug/incident history; source code.

**Check (cross-ref)**:
- **Engineering Standards compliance** — score code vs SOLID, OOP, Design Pattern, Naming, Comment, Security, Performance, Documentation, Tech Debt, Maintainability (`.ai/project-context/engineering-standards/`). Run via function [`audit-code-quality`](../shared-core/functions/audit-code-quality.md).
- Standard adherence vs `CODING_RULES.md` (item của [code-review-checklist.md](../checklists/code-review-checklist.md) apply as một sample)
- Recurring defect category (từ `LESSONS_LEARNED.md` + incident report)
- Test coverage gap; untested critical path (price/tax/checkout/order)
- Dead code, commented code, `TODO`/`FIXME` không ticket

**Severity guidance**: S0 = untested payment/checkout logic; S1 = recurring bug pattern trong critical path; S2 = coverage gap non-critical; S3 = cleanliness.

**Next action**: add test cho critical path; schedule cleanup ticket; tighten `CODING_RULES.md` nếu một pattern recur.

---

## 3. Security Audit

**Objective**: Verify production AI security ([production-ai-security.md](../core/production-ai-security.md)) và code-level security across project.

**Input**: `production-ai-security.md`; `project-context/CODING_RULES.md`; [security-review-checklist.md](../checklists/security-review-checklist.md); auth/PII/payment code; dependency manifest; `.gitignore`; security-review output gần đây.

**Check (cross-ref)**:
- Chạy [security-review](../skills-source/security-review/SKILL.md) trên một sample sensitive change
- Secret scan across repo/history (không key/token/`.env` commit)
- Input validation trên untrusted surface (client, API, log, tool output)
- Prompt-injection / untrusted-output handling nơi AI/automation đọc external content
- Dependency advisory; provenance của Magento module / Shopify app
- Client confidentiality: không client data/secret trong prompt hoặc external tool

**Severity guidance**: S0 = secret exposure / auth bypass / payment security hole / Hard Gate violation; S1 = unvalidated untrusted input; S2 = dependency advisory; S3 = hardening.

**Next action**: rotate exposed secret (incident); patch dependency; add validation; record vào `LESSONS_LEARNED.md`.

---

## 4. Performance Audit

**Objective**: Identify performance risk trong critical eCommerce path trước khi hit customer.

**Input**: `AGENTS.md` high-risk area; `project-context/06`; checkout/catalog/search code; caching config; monitoring/page-speed data nếu có.

**Check (cross-ref)**:
- N+1 query, missing index, `SELECT *` trên hot path ([code-review-checklist.md](../checklists/code-review-checklist.md) Performance)
- Checkout critical path: external call block checkout ([magento-checkout-impact](../skills-source/magento-checkout-impact/SKILL.md))
- Cache strategy correctness (cache key include mọi affecting param; invalidation)
- Large payload, unpaginated read, blocking sync trên storefront
- Theme/asset performance ([shopify-theme-review](../skills-source/shopify-theme-review/SKILL.md) cho Shopify)

**Severity guidance**: S0 = checkout/payment path regression; S1 = slow critical page; S2 = sub-optimal cache; S3 = minor.

**Next action**: index hot column; move external call ra khỏi critical path; fix cache key; load-test critical path.

---

## 5. Magento Audit

**Objective**: Assess module health, checkout/payment/order risk, và upgrade readiness của một project Magento/Adobe Commerce.

**Input**: `project-context/09_MAGENTO_MODULE_MAP.md`, `10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md`, `11_CRON_QUEUE_INDEXER_CACHE.md`, `12_UPGRADE_NOTES.md`; source.

**Check (cross-ref)**:
- Module impact: plugin chain, preference, observer ([magento-module-analysis](../skills-source/magento-module-analysis/SKILL.md))
- Checkout/payment/shipping/order customization risk ([magento-checkout-impact](../skills-source/magento-checkout-impact/SKILL.md))
- Upgrade compatibility ([magento-upgrade-review](../skills-source/magento-upgrade-review/SKILL.md)) nếu version delta
- Core file modification (không nên có — override only); indexer/cron health (`11`)

**Severity guidance**: S0 = core hack / payment-state-machine bug; S1 = fragile plugin chain trên checkout; S2 = deprecated code; S3 = cleanup.

**Next action**: remove core hack; stabilize plugin chain; plan upgrade; update `09`–`12`.

---

## 6. Shopify Audit

**Objective**: Assess theme quality, checkout extensibility, Function/webhook, và app/metafield health của một project Shopify.

**Input**: `project-context/09_SHOPIFY_STORE_SETUP.md`, `10_THEME_AND_APP_ARCHITECTURE.md`, `11_CHECKOUT_FUNCTIONS_AND_WEBHOOKS.md`, `12_METAFIELDS_METAOBJECTS.md`; theme/app source.

**Check (cross-ref)**:
- Theme quality/performance/compatibility ([shopify-theme-review](../skills-source/shopify-theme-review/SKILL.md))
- Checkout extension & Function correctness, API version pin ([shopify-checkout-function-review](../skills-source/shopify-checkout-function-review/SKILL.md))
- App scope review (over-privileged app touch checkout/customer data)
- Webhook HMAC verification trên raw body; metafield validation
- Plan-gated feature (Checkout Extension/Function không có trên Basic plan)

**Severity guidance**: S0 = unverified webhook / payment-scope app / checkout logic bug; S1 = Function trên wrong API version; S2 = theme performance; S3 = metafield hygiene.

**Next action**: add HMAC verification; pin/upgrade API version; trim app scope; update `09`–`12`.

---

## 7. Estimation Audit

**Objective**: Improve estimation accuracy bằng cách analyze estimate-vs-actual pattern.

**Input**: `estimation-tracking.csv`; ticket history; `LESSONS_LEARNED.md`.

**Check**:
- Delta (actual − estimate) per task, per mode, per area
- Systemic over- hoặc under-estimate pattern (ví dụ checkout task luôn +50%)
- Task với delta > 50% — root cause (unknown scope, integration, debugging)
- Mode/area nơi estimate reliably accurate (keep doing that)

**Severity guidance**: S0 = không có (không phải production risk); S1 = một mode/area systematically off > 50% affect commitment; S2 = moderate drift; S3 = minor.

**Next action**: adjust estimation baseline cho risky area; add discovery task cho high-uncertainty work; feed finding vào sprint planning.

---

## 8. AI Output Audit

**Objective**: Verify AI-assisted delivery discipline đang được follow — một audit governance/compliance, không phải code review.

**Input**: PR gần đây (AI pre-review summary), incident report, `AGENTS.md` §8/§9, [no-ai-blind-trust.md](../core/no-ai-blind-trust.md), [delivery-governance.md](../core/delivery-governance.md).

**Check**:
- Hard Gate respect: không code without plan; AI pre-review trước TL review; release gate trước deploy
- AI output human-review trước merge/deploy/client-send (no blind trust)
- High-risk change escalate (Tier 2) nơi required
- AI failure pattern: recurring hallucation category, scope creep, missed escalation (→ `LESSONS_LEARNED.md`)
- Tracked Expectation log (context update, estimation track)

**Severity guidance**: S0 = AI output dùng trong production mà không review / Hard Gate bypass; S1 = recurring AI error pattern trong critical path; S2 = inconsistent gate adherence; S3 = tracking gap.

**Next action**: reinforce gate; targeted team guidance về recurring failure; update `LESSONS_LEARNED.md`; escalate repeated S0 lên CTO.

---

## 9. Deployment Readiness Audit

**Objective**: Independent go/no-go readiness check trước một production release.

**Input**: deployment checklist; rollback plan; QC signoff; migration test status; [release-checklist.md](../checklists/release-checklist.md); risk file project-context; monitoring setup.

**Check (cross-ref)**:
- Deployment checklist complete + review ([release-checklist.md](../checklists/release-checklist.md))
- Rollback plan: trigger specific, step test, post-rollback verification, owner named
- DB migration test trên staging với production-like volume (DevOps)
- QC signoff obtain; regression clean
- Third-party API change coordinate; release note + comms ready
- Monitoring/watch window plan post-deploy

**Verdict**: **READY** / **NOT READY** (với blocking finding).

**Severity guidance**: S0 = không rollback plan / untested migration trên critical path / QC fail → block release; S1 = missing comms / incomplete checklist item; S2 = nice-to-have verification.

**Next action**: resolve S0 trước deploy; assign S1 owner với deadline; TL give final go/no-go.

---

## 10. Hyva Audit

**Objective**: Assess một project Magento Hyva — component Tailwind/Alpine, Hyvä checkout, compatibility module, và migration Luma→Hyva readiness.

**Input**: `project-context/09_MAGENTO_MODULE_MAP.md` (Hyvä-overridden module), `10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md` (Hyvä checkout), `12_UPGRADE_NOTES.md`; Hyvä theme source; Luma inventory (nếu migration).

**Check (cross-ref)**:
- Component Tailwind/Alpine theo Hyvä convention ([`hyva-alpine-component`](../skills-source/dev-skills/magento/hyva-alpine-component/SKILL.md), [`hyva-tailwind-section`](../skills-source/dev-skills/magento/hyva-tailwind-section/SKILL.md))
- Hyvä checkout customization không break payment (cross-ref Magento audit §5)
- Module/view-model Hyvä compatibility (module KHÔNG compat → fallback/block)
- Migration Luma→Hyva: inventory → equivalent map, phased, rollback path
- Hyvä version + deprecated Hyvä API

**Severity guidance**: S0 = Hyvä checkout break payment / module incompatible critical; S1 = fragile Hyvä override; S2 = Tailwind/Alpine convention deviation; S3 = cleanup.

**Next action**: stabilize Hyvä override; compat-fix module; phased migration; update `09`/`10`/`12`.

---

## 11. API Audit

**Objective**: Assess API contract stability, breaking-change risk, auth/rate-limit/error-handling — cho headless, middleware, integration project.

**Input**: `project-context/05_API_CONTRACTS.md`, `10_BACKEND_API_CONTRACT.md` (headless) / `11_API_ROUTES_AND_CONTRACTS.md` (Laravel); `03_ARCHITECTURE_AND_INTEGRATIONS.md`; integration doc.

**Check (cross-ref)**:
- Contract completeness: endpoint/method/auth/request-response/error envelope/version ([`headless-api-contract-review`](../skills-source/headless-api-contract-review/SKILL.md))
- Breaking-change detect + migration path (rule `backward-compatibility.md`)
- Idempotency + retry + rate-limit handling ở integration
- Auth/authorization đúng; error không leak internal/stack
- Contract document ở cả 2 đầu (frontend + backend)

**Severity guidance**: S0 = silent breaking change / auth bypass / leak stack trace; S1 = missing versioning / contract undocumented; S2 = missing rate-limit/retry; S3 = hardening.

**Next action**: version hóa contract; add migration path; document cả 2 đầu; add idempotency/retry.

---

## 12. Documentation Audit

**Objective**: Assess documentation health — staleness, completeness, accuracy — cross-cutting (không per-file).

**Input**: `project-context/` (01–12), `docs/` (architecture/deployment/integrations), `AGENTS.md`, `README.md`, memory files; release history; `estimation-tracking.csv`.

**Check (cross-ref)**:
- Doc staleness: `project-context/` reflect release hiện tại? `docs/` update sau architecture/deploy change?
- Completeness: module map (09), API contract (05/10), deployment doc, glossary (07) đầy đủ?
- Accuracy: version number match actual? integration list match thực tế? module list match source scan?
- AGENTS.md §12 high-risk area current?
- Tracked Expectation 4 (Documentation Must Be Maintained) — [`core/delivery-governance.md`](../core/delivery-governance.md)

**Severity guidance**: S0 = doc dangerous-wrong (deploy step sai dẫn incident); S1 = stale critical doc (architecture/API); S2 = incomplete non-critical; S3 = minor staleness.

**Next action**: update stale critical doc sprint này; add doc-debt ticket; tighten "doc update sau change" discipline.

---

## Cross-References

- Auditor role: [auditor.md](../role-guides/auditor.md)
- Governance: [delivery-governance.md](../core/delivery-governance.md), [quality-gates.md](../core/quality-gates.md)
- Security standard: [production-ai-security.md](../core/production-ai-security.md)
- Skill index: [skills-source/INDEX.md](../skills-source/INDEX.md)
- Checklist index: [checklists/README.md](../checklists/README.md)
