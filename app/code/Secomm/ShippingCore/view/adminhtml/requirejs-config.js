/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — `templates/form/collapsible` (the ui_component form
 * container template id shipped by core XMLs) resolves client-side to
 * `templates/template/form/collapsible.html`, which Magento 2.4.8 no longer ships.
 * Map that prefix to this module, which provides the file (static fallback
 * cannot serve prefix-less paths from app/code modules).
 */
var config = {
    paths: {
        'templates/template': 'Secomm_ShippingCore/templates/template'
    }
};
