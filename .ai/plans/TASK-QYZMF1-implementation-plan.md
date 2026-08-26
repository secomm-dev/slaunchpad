# TASK-QYZMF1 — Implementation Plan: llms.txt V1.1

| Specification | SPEC-TASK-QYZMF1 (`.ai/specs/SPEC-TASK-QYZMF1-ai-discoverability-llms-v1-1.md`) |
|---|---|
| Branch | `task/ai-discoverability-llms-v1-1` (base `0719e4f2`) |
| Type | MINI — output enrichment, no config/DI/endpoint change |

## Steps

1. **LlmsTxtFormatter** — extend: prose-section entries (`['prose' => string[]]`), commerce detail entries (`['detail' => ['label','url','purpose','plain?']]` → `### Label` / `GET url` / `Purpose: …`), raise link-description bound 200→240. Sanitize mọi prose line.
2. **CommerceEndpointsSource** — thêm `purpose` vào 4 entries (Store Information / Product Search / Categories / Product Detail). Không đổi availability logic.
3. **CategoriesSource** — description precedence: `meta_description` → sanitized bounded (240) `description` → omit. Cùng category object đã load (0 query thêm).
4. **SitemapRefsSource** — label `Sitemap` → `XML Sitemap`.
5. **LlmsTxtGenerator** — rename sections (`Featured Collections`, `Key Pages`); chèn prose sections: `Store Summary` (brand_summary khi configured), `Agent Guidance` + `Commerce Limitations` (chỉ khi commerce entries ≠ []); thứ tự §3.1; URL-bound loop chỉ áp cho URL-carrying sections (như cũ, commerce detail vẫn đếm).
6. **Tests** (TDD: viết/extend trước khi implement):
   - `LlmsTxtFormatterTest`: prose render, detail render, bound 240, HTML strip.
   - `LlmsTxtGeneratorTest`: V1.1 structure, commerce enabled/disabled, forbidden-capability strings absent, store summary omit khi blank.
   - `CategoriesSourceTest`: meta_description render, description fallback bounded, HTML stripped, omit.
   - `CommerceEndpointsSourceTest`: purpose fields.
7. **README + CHANGELOG** (1.4.0) mô tả cấu trúc V1.1.
8. **Runtime proof** vi_vn (full excerpt) + second store (default) isolation; temporary test data (nếu cần) dọn sạch.
9. **Validation**: targeted + full AiDiscoverability + AiCommerce suites, php -l, PHPCS Magento2 (0 err/warn docblock), `project-ai-validate --check-specs`, `git diff --check`, `app/etc/config.php` unchanged.

## Guards

Không OM, không direct SQL, không N+1, không field config mới, không cron, không đổi AiCommerce, không capability claim chưa hỗ trợ.
