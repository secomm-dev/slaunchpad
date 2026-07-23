# Create a Hyvä Alpine.js Interactive Component

## Purpose
Use this skill to build an interactive frontend component in a Hyvä theme — a button with optimistic state, a quantity stepper, a mini-form that POSTs to cart, etc. Hyvä uses **Alpine.js**, NOT Knockout/RequireJS. This skill is for Hyvä themes only.

## Prerequisites
- Read `AGENTS.md` Section 7.2 (Hyvä additions): Alpine.js + Tailwind only, NO RequireJS, NO jQuery, NO `data-bind`
- Read `project-context/03-tech-stack.md` (confirm Hyvä, not Luma) and `project-context/05-conventions.md`
- A Hyvä theme installed (`vendor/hyva-themes/...` or `app/design/Frontend/Hyva/...`)
- `tailwind.config.js` reachable for safelisting dynamic classes

## Input
- **Component purpose** (e.g. "Add to Wishlist with optimistic state")
- **Endpoint** (Magento controller or `/customer/section/load`)
- **Data needed at render** (product id, form key, is-in-wishlist flag)
- **State transitions** (idle → loading → success / error)

## Generated Files
- `view/frontend/templates/component/{name}.phtml` (Alpine component template)
- `view/frontend/web/js/{name}.js` (Alpine component factory — native ES module, NO RequireJS)
- `view/frontend/layout/{handle}.xml` (layout reference, optional)
- `tailwind.config.js` (safelist dynamic classes, if any)

## Hyvä Hard Rules
- **No RequireJS** anywhere on the frontend. Use native ES module `import`.
- **No jQuery**. Use Alpine.js or vanilla `fetch`.
- **No Knockout `data-bind`**. Use Alpine `x-data`, `x-on` (`@`), `x-bind` (`:`), `x-text`, `x-show`, `x-transition`.
- **Dynamic Tailwind classes must be safelisted** in `tailwind.config.js` (Tailwind purges classes it cannot see literally).
- **Form key via `hyva.getFormKey()`**, prices via `hyva.formatPrice()`, cookies via `hyva.getCookie()`.

## Step-by-Step

### Step 1: Create the Alpine component factory (ES module)
Hyvä convention: a JS file exports a function returning the Alpine data object. The `.phtml` template calls it inside `x-data`.

`view/frontend/web/js/wishlist-button.js`:
```js
// Native ES module — NO define(), NO requirejs.
// Hyvä exposes window.hyva with helpers: getFormKey, formatPrice, getCookie, postForm, translate.

export function wishlistButton(productId, initialInWishlist, isLoggedIn) {
    return {
        productId: productId,
        inWishlist: !!initialInWishlist,
        loading: false,
        error: '',
        success: false,

        // Hyvä provides window.hyva with a Stratus-style event bus and helpers.
        init() {
            // React to external wishlist updates (e.g. header counter or another component removing the item).
            window.addEventListener('hyva:wishlist-changed', (event) => {
                if (event.detail && event.detail.productId === this.productId) {
                    this.inWishlist = !!event.detail.inWishlist;
                }
            });
        },

        async toggle() {
            if (this.loading) return;
            this.loading = true;
            this.error = '';
            this.success = false;

            try {
                if (!isLoggedIn) {
                    // Redirect to login, preserving the product page as the return target.
                    window.location.href = '/customer/account/login/referer/' + btoa(window.location.href);
                    return;
                }

                const formKey = window.hyva && window.hyva.getFormKey
                    ? window.hyva.getFormKey()
                    : '';

                const url = this.inWishlist
                    ? '/wishlist/index/remove/'
                    : '/wishlist/index/add/';

                const body = new URLSearchParams({
                    product: String(this.productId),
                    form_key: formKey,
                });

                // No jQuery — fetch with credentials so the customer session cookie is sent.
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body.toString(),
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok (' + response.status + ')');
                }

                // Optimistic update — flip state immediately, revert on failure below.
                const previous = this.inWishlist;
                this.inWishlist = !this.inWishlist;
                this.success = true;

                // Broadcast so the header wishlist counter and sibling components update.
                window.dispatchEvent(new CustomEvent('hyva:wishlist-changed', {
                    detail: { productId: this.productId, inWishlist: this.inWishlist },
                }));

                // Refresh the customer section data so the header counter updates server-side.
                if (window.hyva && window.hyva.refreshCustomerData) {
                    window.hyva.refreshCustomerData();
                }
            } catch (e) {
                this.error = (e && e.message) ? e.message : 'Could not update wishlist';
                this.inWishlist = previous; // revert optimistic state
            } finally {
                this.loading = false;
                // Clear success message after 2.5s
                if (this.success) {
                    setTimeout(() => { this.success = false; }, 2500);
                }
            }
        },
    };
}
```

### Step 2: Create the `.phtml` template
`view/frontend/templates/component/wishlist-button.phtml`:
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

/** @var \Magento\Framework\View\Element\Template $block */
/** @var \Magento\Framework\Escaper $escaper */

/** @var \Hyva\Theme\ViewModel\Customer $customerViewModel */
$customerViewModel = $block->getData('customer_view_model') ?? $block->getLayout()->createBlock(\Hyva\Theme\ViewModel\Customer::class);
$product = $block->getData('product');
$productId = (int) ($product ? $product->getId() : ($block->getData('product_id') ?? 0));
$isInWishlist = (bool) ($block->getData('is_in_wishlist') ?? false);
$isLoggedIn = $customerViewModel && method_exists($customerViewModel, 'isLoggedIn') ? $customerViewModel->isLoggedIn() : false;
?>
<script>
    // Import the factory once per page; Alpine initializes it when the component mounts.
    (async () => {
        if (!window.wishlistButtonModule) {
            window.wishlistButtonModule = await import('<?= $escaper->escapeJs($block->getViewFileUrl('Acme_StorePickup::js/wishlist-button.js')) ?>');
        }
        // Register the data factory on the Alpine global so x-data can reference it by name.
        document.addEventListener('alpine:init', () => {
            if (window.Alpine && !window.Alpine.data('wishlistButton')) {
                window.Alpine.data('wishlistButton', (productId, inWishlist, isLoggedIn) =>
                    window.wishlistButtonModule.wishlistButton(productId, inWishlist, isLoggedIn)
                );
            }
        });
    })();
</script>

<div x-data="wishlistButton(<?= $escaper->escapeHtmlAttr($productId) ?>, <?= $isInWishlist ? 'true' : 'false' ?>, <?= $isLoggedIn ? 'true' : 'false' ?>)"
     x-init="init()"
     class="inline-block">
    <button type="button"
            @click="toggle()"
            :disabled="loading"
            :class="loading ? 'opacity-60 cursor-wait' : 'hover:text-red-600'"
            class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
        <!-- Heart icon — static classes only so Tailwind keeps them -->
        <svg xmlns="http://www.w3.org/2000/svg"
             class="w-5 h-5"
             :class="inWishlist ? 'text-red-500 fill-current' : 'text-gray-400'"
             viewBox="0 0 24 24"
             stroke="currentColor"
             stroke-width="2"
             fill="none"
             aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 20.364l-7.682-7.682a4.5 4.5 0 010-6.364z"/>
        </svg>
        <span x-text="loading
            ? '...'
            : (inWishlist ? '<?= $escaper->escapeHtml(__('In Wishlist')) ?>' : '<?= $escaper->escapeHtml(__('Add to Wishlist')) ?>')"></span>
    </button>

    <p x-show="success" x-transition x-cloak
       class="mt-2 text-sm text-green-600">
        <?= $escaper->escapeHtml(__('Wishlist updated.')) ?>
    </p>
    <p x-show="error" x-transition x-cloak
       class="mt-2 text-sm text-red-600" x-text="error"></p>
</div>
```

### Step 3: Reference in layout XML (optional)
`view/frontend/layout/catalog_product_view.xml`:
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="product.info.main">
            <block name="product.info.wishlist.acme"
                   template="Acme_StorePickup::component/wishlist-button.phtml"
                   after="product.info.addtocart">
                <arguments>
                    <argument name="product" xsi:type="object">\Magento\Catalog\Block\Product\View</argument>
                </arguments>
            </block>
        </referenceContainer>
    </body>
</page>
```

### Step 4: Safelist any dynamic Tailwind classes
If you build class strings dynamically (e.g. `'text-' + color`), add them to `tailwind.config.js`. The example above uses static classes only, so no safelist needed — but if you later add dynamic colors:
```js
// tailwind.config.js (Hyvä theme root)
module.exports = {
    // ...
    safelist: [
        'text-red-500',
        'text-green-600',
        'bg-red-50',
    ],
};
```

### Step 5: Build and verify
```bash
# Regenerate the Hyvä theme's compiled Tailwind/JS if your build pipeline uses it:
# (Hyvä ships a watcher; run from theme root if needed)
bin/magento cache:clean
```

## Coding Rules Applied
- **Alpine.js only** (AGENTS.md 7.2): `x-data`, `x-on`/`@`, `x-bind`/`:`, `x-text`, `x-show`, `x-transition` — NOT Knockout `data-bind`
- **NO RequireJS in frontend**: native ES module `import` via `getViewFileUrl(...)` + dynamic import
- **NO jQuery**: `fetch` with `credentials: 'same-origin'` to send the customer session cookie
- **Dynamic Tailwind classes safelisted** in `tailwind.config.js` — purge-aware
- **Hyvä helpers**: `window.hyva.getFormKey()`, `window.hyva.formatPrice()`, `window.hyva.getCookie()`, `window.hyva.refreshCustomerData()`

## Verification
- [ ] Page loads with no RequireJS errors in the browser console (search for `define is not defined` or `require` references)
- [ ] No jQuery on the page (`window.jQuery` undefined or your code does not call it)
- [ ] Click "Add to Wishlist" → button shows `...`, then flips to "In Wishlist", heart fills red
- [ ] Open DevTools Network → POST to `/wishlist/index/add/` returns 200, header wishlist counter increments
- [ ] Trigger a network failure (DevTools → Offline) → error message shows, button reverts to original state
- [ ] Logged-out customer: click redirects to `/customer/account/login/`
- [ ] Two wishlist buttons on the same page: toggling one updates the other via the `hyva:wishlist-changed` event (shared state via custom event, not a global `$store` abuse)

## Common Mistakes
- **Using RequireJS (`define`/`require`)**: Hyvä does not load RequireJS on the frontend — the call silently fails. Use native ES `import` and `getViewFileUrl()`.
- **Referencing jQuery** (`$.ajax`, `$('#id')`): jQuery is not present in Hyvä. Use `fetch` or Alpine's `x-on` bindings.
- **Knockout `data-bind` left over from a ported Luma template**: nothing renders. Rewrite to Alpine `x-bind`/`x-text`.
- **Dynamic Tailwind classes purged**: `:class="'text-' + color"` where `color` is a variable — Tailwind's JIT cannot see the literal class, so it is dropped from the build. Either use a lookup object of full class strings, or safelist.
- **Data not isolated between components**: two instances of the component share state if you put the Alpine data object on `window` once. Register via `Alpine.data(name, factory)` so each `x-data` gets its own instance. For genuinely shared state use `Alpine.store(...)`.
- **Missing form key on POST**: Magento rejects the request with a redirect to the homepage or a 302. Always include `form_key` from `hyva.getFormKey()`.
- **Not escaping JS output**: passing product names into inline JS without `$escaper->escapeJs()` causes a quote-injection break. Always escape.
- **Forgetting `x-cloak` + `[x-cloak] { display: none }`**: Alpine templates flash their default content before Alpine initializes. Add `x-cloak` to elements that must be hidden until ready.
