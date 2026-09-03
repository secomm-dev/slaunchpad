# LC-30 — Diff Summary (TASK-0X552E)

Branch `task/lc-30-aieo-baseline` (base `dev/development/thanhle` @ 56d47ade).

## Added — new module `app/code/Secomm/AiDiscoverability/` (~38 files)

- registration.php, etc/module.xml (sequence: Store, Cms, Catalog, UrlRewrite, Sitemap — no
  Mirasvit), etc/config.xml (defaults: disabled, sitemap refs on, lifetime 86400, max 100)
- etc/frontend/{di,routes}.xml — RouterList router (sortOrder 20) for root /llms.txt
- etc/adminhtml/{system,acl,routes}.xml — config section + preview ACL; etc/events.xml — 8
  targeted invalidation observers (module config, Mirasvit seo config, CMS page, category,
  sitemap save/delete commit)
- Controller/Index/Index.php — standalone GET/HEAD controller (no session, 200/304/404)
- Controller/Adminhtml/Preview/Index.php — ACL preview via fresh (cache-bypass) seam
- Controller/Router.php — root-path llms.txt matcher (Magento_Robots pattern)
- Model/Config.php, Model/InvalidateCache.php, Model/Config/Source/{CmsPages,Categories}.php
- Service/{SeoPolicy,EligibilityChecker,CanonicalPolicy,UrlCollector,LlmsTxtFormatter,
  LlmsTxtGenerator,LlmsTxtProvider}.php + Service/Source/{PriorityUrls,Categories,CmsPages,
  SitemapRefs}Source.php
- Observer/*.php (5), ViewModel/DescribedBy.php, view/frontend/layout/default.xml,
  view/frontend/templates/head/describedby.phtml
- Test/Unit/Service/*.php (21 tests), i18n/{vi_VN,en_US}.csv, README.md, CHANGELOG.md

## Modified — existing files

- `app/etc/config.php` — `Secomm_AiDiscoverability` enabled by `bin/magento module:enable`
  (generated file; only the new module row)
- `.gitignore` — ignore `.codegraph/` (local codegraph index) and local lookbook media

## Notable implementation decisions / fixes during Phase 3

- `ifConfig` on `<block>` is invalid per page_configuration.xsd and crashed the storefront —
  replaced by ViewModel `isEnabled()` guard in the template (bug found via live smoke test).
- SeoPolicy parses Mirasvit `noindex_pages2` via injected `Serializer\Json` /
  `Serializer\Serialize` (no direct json_decode/unserialize — magento-spec §1).
- Category canonical = oldest non-redirect url_rewrite row (mirrors proven Mirasvit rule).
- No `clean_sitemap` event exists in this installation — sitemap invalidation hooks
  `core_abstract_save/delete_commit_after` with an instanceof Sitemap filter.
- PHPCS Magento2: 0 errors / 0 warnings after docblock pass (static -> instance methods,
  parse_url replacement, line wraps).
