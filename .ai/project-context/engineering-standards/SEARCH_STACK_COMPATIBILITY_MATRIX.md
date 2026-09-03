# SEARCH_STACK_COMPATIBILITY_MATRIX

> Engineering standard — Search stack combinations for Launchpad. English.
> Tracks the verified search stack combinations allowed for use in Launchpad releases.
>
> **Core principle**: Every row represents a **complete stack combination** — not individual component pairings. The **Tested** status is awarded only when the exact combination has passed internal smoke tests on the Launchpad environment.

---

## Combination Status Labels

| Label | Meaning | Eligibility for Launchpad Reusable Release |
| :---: | :--- | :---: |
| **Supported** | All dependencies in the combination are officially declared compatible by their respective vendors. Has not passed internal smoke tests. | ❌ Not eligible |
| **Tested** | Smoke-tested internally on the Launchpad environment with this exact combination (no extrapolation from other combinations). | ✅ Eligible for Release |
| **Not Tested** | Unverified combination. Cannot be used. | ❌ Not eligible |
| **Deprecated** | Outdated combination. **Retained in the matrix for historical traceability**; removed from release eligibility. | ❌ Not eligible |

> ⚠️ **Only combinations in the Supported + Tested (Tested) status are allowed in Launchpad reusable releases.**

---

## Compatibility Matrix

| ID | Magento Version | OpenSearch Version | ElasticSuite Version | Hyvä Compat Module | Hyvä UI (Default Theme) | Status | Composer Constraints | Tracker / Evidence |
| :---: | :--- | :--- | :--- | :--- | :--- | :---: | :--- | :--- |
| **C-001** | `2.4.8-p5` | `3.6.0` (local) / `2.12.x` | `~2.12.0` (`smile/elasticsuite`) | `1.2.8.0` (`hyva-themes/magento2-smile-elasticsuite`) | `^1.5.2` (Hyvä 3.x) | **Tested** | `"smile/elasticsuite": "~2.12.0"`, `"hyva-themes/magento2-smile-elasticsuite": "^1.2"` | Verified via **LC-05**: OpenSearch 3.6.0 + ElasticSuite 2.12.0 + Hyvä compat 1.2.8.0 on local dev. |
| **C-002** | `2.4.8-p5` | `2.12.x` | N/A (Core Magento Search) | N/A (none needed) | `^1.5.2` (Hyvä 3.x) | **Tested** | `"opensearch-project/opensearch-php": "^2.3"` (transitive dep via Magento `OpenSearch` native module) | Smoke-test outcome: Magento native OpenSearch integration functioning. |
| **C-003** | `2.4.8-p5` | `2.12.x` | `^2.11` (Smile ElasticSuite) | `^1.1` (`hyva-themes/magento2-smile-elasticsuite`) | `^1.5.2` (Hyvä 3.x) | **Not Tested** | `"smile/elasticsuite": "^2.11"`, `"hyva-themes/magento2-smile-elasticsuite": "^1.1"` | Hyvä Module Tracker: https://gitlab.hyva.io/hyva-themes/hyva-compat/magento2-smile-elasticsuite — ElasticSuite 2.11.x not smoke-tested on Magento 2.4.8-p5. |
| **C-004** | `2.4.7-p4` | `2.11.x` | N/A (Core Magento Search) | N/A | `^1.4.x` (Hyvä 2.x) | **Deprecated** | — | Deprecated on 2026-08-21. Replaced by C-001 / C-002. Out of release eligibility. |

---

## Technical Notes

### Component-to-Component Support (Vendor Claims — not combination status)

| Dependency Pair | Vendor Support Status | Official Reference |
| :--- | :---: | :--- |
| Magento 2.4.8-p5 ↔ OpenSearch 2.x / 3.x | **Supported** | https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/system-requirements.html |
| Hyvä Default Theme 1.5.x ↔ Magento 2.4.8 | **Supported** | https://docs.hyva.io/hyva-themes/installation/index.html |
| Smile ElasticSuite 2.12.x ↔ Hyvä Theme 1.5.x (Compat `hyva-themes/magento2-smile-elasticsuite` 1.2.8) | **Supported** | GitLab: https://gitlab.hyva.io/hyva-themes/hyva-compat/magento2-smile-elasticsuite |

### Tracker & Changelog References

| Resource | URL | Purpose |
| :--- | :--- | :--- |
| Hyvä Module Compatibility Tracker | https://hyva-themes.github.io/hyva-compat | Tracks 3rd-party module compatibility with Hyvä Default Theme |
| Hyvä ElasticSuite Compat GitLab | https://gitlab.hyva.io/hyva-themes/hyva-compat/magento2-smile-elasticsuite | Official source and issue tracker for the Hyvä ElasticSuite compat module |
| Hyvä Changelog (Release Notes) | https://docs.hyva.io/hyva-themes/changelog/index.html | Tracks breaking changes across Hyvä releases |
| Magento System Requirements | https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/system-requirements.html | Official OpenSearch/Elasticsearch supported versions |
| Smile ElasticSuite GitHub Releases | https://github.com/Smile-SA/elasticsuite/releases | Tracks ElasticSuite releases and changelogs |

---

## Review Schedule & Combinatorial Explosion Guard

> **Risk warning — Combinatorial Explosion**: This matrix lists only the combinations Launchpad **actually supports and intends to use**. It does not attempt to map the entire theoretical combination space.

- **Review triggers**: Magento minor/security patches, Hyvä minor releases, or ElasticSuite major releases.
- **Owner**: Platform Lead or SA.
- **Workflow for adding new combinations**:
  1. Add a new row set to `Not Tested`.
  2. Perform smoke tests on the Launchpad Reference Storefront.
  3. Change status to `Tested` once verification logs are saved.
- **Workflow for deprecation**:
  1. Change status to `Deprecated` and record the deprecation date.
  2. **Do NOT remove the row** from the matrix to maintain audit history.
  3. Explicitly link the replacement combination ID in the notes.
