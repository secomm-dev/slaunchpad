# Secomm_UiWidget

Shared Magento widget foundation for approved Secomm UI components on Hyvä storefronts.

## Current status

This release contains the foundation only:

- one Magento widget type labelled `Secomm UI`;
- explicit component registry and definition contracts;
- source provenance and schema version metadata;
- safe registered-template resolution;
- an Admin component source model.

No component is registered yet. Dynamic component options and the first `banner_a` component are delivered by later tasks in `FEAT-J06WXZ`.

## Architecture rules

- Runtime templates are owned by this module or overridden by a Hyvä product theme.
- Never render or scan `vendor/hyva-themes/hyva-ui` at runtime.
- Component IDs and schema versions are persisted compatibility contracts.
- CMS parameters never select arbitrary PHP classes or template paths.
- Product/category selection is manual-only.
- Luma and non-Hyvä themes are not supported.

See `SPEC-FEAT-J06WXZ` and `DEC-FEATJ06WXZ-001` for the canonical contract.

## Registering a component

Components are added to the registry through dependency injection with an object implementing `ComponentDefinitionInterface`. Each definition must provide a stable ID, label, registered Magento template alias, schema version, field metadata, source component/version and sort order.

Component registration is intentionally explicit. Updating the Hyvä UI Composer package does not add or change runtime components.

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
