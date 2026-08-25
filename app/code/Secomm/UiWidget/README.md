# Secomm_UiWidget

Shared Magento widget foundation for approved Secomm UI components on Hyvä storefronts.

## Current status

This release contains the reusable foundation and dynamic parameter layer:

- one Magento widget type labelled `Secomm UI`;
- explicit component registry and definition contracts;
- source provenance and schema version metadata;
- safe registered-template resolution;
- an Admin component source model;
- registry-driven scalar, media and ordered collection fields;
- deterministic versioned payload validation for directives, instances and storefront rendering.

The production registry currently contains `banner_a` and `generic_content_a`. Generic Content A is the first consumer of the explicit trusted rich-text contract.

## Architecture rules

- Runtime templates are owned by this module or overridden by a Hyvä product theme.
- Never render or scan `vendor/hyva-themes/hyva-ui` at runtime.
- Component IDs and schema versions are persisted compatibility contracts.
- CMS parameters never select arbitrary PHP classes or template paths.
- Product/category selection is manual-only.
- Luma and non-Hyvä themes are not supported.
- Only fields declared as `trusted-rich-text` use Magento WYSIWYG and the CMS block filter. Other output remains context-escaped.

See `SPEC-FEAT-J06WXZ` and `DEC-FEATJ06WXZ-001` for the canonical contract.
Trusted rich text is governed by `DEC-FEATJ06WXZ-002`.

## Registering a component

Components are added to the registry through dependency injection with an object implementing `ComponentDefinitionInterface`. Each definition must provide a stable ID, label, registered Magento template alias, schema version, field metadata, source component/version and sort order.

Component registration is intentionally explicit. Updating the Hyvä UI Composer package does not add or change runtime components.

### Banner A provenance

- Internal ID: `banner_a`; schema version: `1`.
- Upstream: Hyvä UI 2.8.0 `banner/A-default`.
- Local modifications: removed demo title/Unsplash fallback, replaced arbitrary colors/classes with allowlisted presentation enums, added registry payload/media fields, compound CTA validation, responsive image semantics and instance-safe IDs.
- Default template: `Secomm_UiWidget::components/banner/a.phtml`.
- Example override: `Secomm/launchpad_fashion/Secomm_UiWidget/templates/components/banner/a.phtml`.

## Parameter persistence

Component-specific values use canonical JSON encoded as Base64URL inside the Magento widget `payload` parameter. The persisted envelope includes format version `1`. Limits are 16 KiB encoded, six nested levels and 50 rows per collection.

The Admin helper renders fields from the server-owned component schema. JavaScript manages only editing, media selection and collection ordering. Magento validates the component ID, schema version, payload format and field types before building a directive or saving a widget instance. Storefront rendering fails closed for tampered data.

## Theme overrides

Default templates will use Magento template aliases such as:

```text
Secomm_UiWidget::components/banner/a.phtml
```

A Hyvä product theme can override presentation at:

```text
Secomm_UiWidget/templates/components/banner/a.phtml
```

The registry, schema and data-provider contracts remain module-owned.
