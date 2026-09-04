# TASK-JN2SH6 — Banner A vertical-slice evidence

Dates: 2026-08-24 to 2026-08-25

- Registered `banner_a` schema version 1 with Hyvä UI 2.8.0 `banner/A-default` provenance.
- Verified responsive media, safe CTA rendering, invalid URL rejection, context escaping and unique IDs for duplicate widget instances.
- Magento CMS Page and CMS Block filters rendered the persisted directive contract.
- PHP/PHTML/XML/JavaScript checks, DI compilation and isolated Tailwind v4 production compilation: passed.
- Unit suite at task completion: 23 tests, 46 assertions.
- Module-default and product-theme override resolution were both proven locally with the same schema. The temporary presentation fixture is intentionally not shipped in Batch 1 production source.
- TL code approval was recorded on 2026-08-25.

The later visual-parity correction replaced the initial adapted layout with the pinned upstream structure while retaining schema-version-1 compatibility.
