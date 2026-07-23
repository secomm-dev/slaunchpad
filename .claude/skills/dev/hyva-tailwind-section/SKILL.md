# Create a Responsive Tailwind Section in Hyvä

## Purpose
Use this skill to build a responsive page section — a "Featured Categories" hero, a "Product Highlights" grid, a promo banner — in a Hyvä theme using Tailwind CSS utility classes and a ViewModel for data. No LESS, no custom CSS, no Block methods.

## Prerequisites
- Read `AGENTS.md` Section 7.2 (Hyvä additions): Tailwind utilities exclusively, ViewModels preferred over Blocks
- Read `project-context/03-tech-stack.md` (confirm Hyvä) and `project-context/05-conventions.md`
- A Hyvä theme with `tailwind.config.js`
- Familiarity with the Magento 2 layout XML `<block>` / `<referenceContainer>`

## Input
- **Section name** (e.g. `Featured Categories`)
- **Layout handle** (e.g. `cms_index_index` for the homepage)
- **Container** to insert into (e.g. `content.top`, `main.content`)
- **Data needed** (e.g. list of category entities with name, url, image)

## Generated Files
- `ViewModel/FeaturedCategoriesViewModel.php` (implements `ArgumentInterface`)
- `view/frontend/templates/section/featured-categories.phtml`
- `view/frontend/layout/{handle}.xml`
- `tailwind.config.js` (safelist if dynamic classes are used)

## Hyvä Hard Rules
- **Tailwind utility classes ONLY** — no LESS, no `app/design/.../web/css/source/_module.less`, no inline `<style>`. Extend via `tailwind.config.js`.
- **ViewModels over Block methods**: data passed to the template via a class implementing `Magento\Framework\View\Element\Block\ArgumentInterface`. Do NOT add public methods to the block.
- **Dynamic class names safelisted** in `tailwind.config.js`.
- **Mobile-first**: base styles target mobile, then `sm:` / `md:` / `lg:` / `xl:` progressively enhance.

## Step-by-Step

### Step 1: Create the ViewModel
A ViewModel is a plain PHP class implementing `ArgumentInterface`. Inject repositories/services in its constructor; expose typed getters to the template.

`ViewModel/FeaturedCategoriesViewModel.php`:
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

declare(strict_types=1);

namespace Acme\StorePickup\ViewModel;

use Magento\Catalog\Api\CategoryListInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;

class FeaturedCategoriesViewModel implements ArgumentInterface
{
    public function __construct(
        private readonly CategoryListInterface $categoryList,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Return categories flagged as featured, ordered by position.
     * Returns an empty array on any failure so the template degrades gracefully.
     *
     * @return CategoryInterface[]
     */
    public function getFeaturedCategories(int $limit = 6): array
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('is_active', 1, 'eq')
                ->addFilter('include_in_menu', 1, 'eq')
                ->setSortOrders([
                    new \Magento\Framework\Api\SortOrder(
                        \Magento\Framework\Api\SortOrder::FIELD_POSITION,
                        \Magento\Framework\Api\SortOrder::SORT_ASC
                    ),
                ])
                ->setPageSize($limit)
                ->setCurrentPage(1)
                ->create();

            return $this->categoryList->getList($searchCriteria)->getItems();
        } catch (LocalizedException $e) {
            $this->logger->error('FeaturedCategoriesViewModel: failed to load categories', [
                'exception' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getCategoryImageUrl(CategoryInterface $category): string
    {
        $image = $category->getData('image');
        if (is_array($image) && isset($image[0]['url'])) {
            return (string) $image[0]['url'];
        }
        if (is_string($image) && $image !== '') {
            // Hyvä serves /media/catalog/category/<file>
            return '/media/catalog/category/' . ltrim($image, '/');
        }
        return '';
    }

    public function getCategoryUrl(CategoryInterface $category): string
    {
        try {
            return (string) $category->getUrlInstance()?->getUrl(null, ['_direct' => $category->getUrlKey()]) ?: '#';
        } catch (\Throwable $e) {
            return '#';
        }
    }
}
```

### Step 2: Create the layout reference with the ViewModel argument
Register the section in a layout handle and pass the ViewModel via `<arguments>`.

`view/frontend/layout/cms_index_index.xml` (homepage):
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content.top">
            <block name="acme.featured_categories"
                   template="Acme_StorePickup::section/featured-categories.phtml"
                   before="-"
                   cacheable="true">
                <arguments>
                    <argument name="view_model" xsi:type="object">
                        Acme\StorePickup\ViewModel\FeaturedCategoriesViewModel
                    </argument>
                </arguments>
            </block>
        </referenceContainer>
    </body>
</page>
```

The `xsi:type="object"` injects the class through DI — its constructor dependencies are auto-resolved.

### Step 3: Create the `.phtml` template
Mobile-first Tailwind classes. Base targets phone (1 column), `sm:` 2 columns, `md:` 3 columns, `lg:` 3 columns with larger images.

`view/frontend/templates/section/featured-categories.phtml`:
```php
<?php
/**
 * Copyright © Acme. All rights reserved.
 */

/** @var \Magento\Framework\View\Element\Template $block */
/** @var \Magento\Framework\Escaper $escaper */
/** @var \Acme\StorePickup\ViewModel\FeaturedCategoriesViewModel $viewModel */

$viewModel = $block->getData('view_model');
if (!$viewModel) {
    // Defensive: do not render if the ViewModel was not wired in layout XML.
    return;
}
$categories = $viewModel->getFeaturedCategories(6);
?>
<?php if (!empty($categories)): ?>
<section class="featured-categories py-8 sm:py-12 lg:py-16 bg-gray-50" aria-labelledby="featured-categories-heading">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 id="featured-categories-heading"
            class="text-2xl sm:text-3xl lg:text-4xl font-bold text-gray-900 text-center mb-6 sm:mb-8 lg:mb-12">
            <?= $escaper->escapeHtml(__('Featured Categories')) ?>
        </h2>

        <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8 list-none p-0 m-0">
            <?php foreach ($categories as $category): ?>
                <?php
                    $name = (string) $category->getName();
                    $url = $viewModel->getCategoryUrl($category);
                    $image = $viewModel->getCategoryImageUrl($category);
                ?>
                <li>
                    <a href="<?= $escaper->escapeUrl($url) ?>"
                       class="group block relative overflow-hidden rounded-lg shadow-sm hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <div class="aspect-w-4 aspect-h-3 bg-gray-100">
                            <?php if ($image !== ''): ?>
                                <img src="<?= $escaper->escapeUrl($image) ?>"
                                     alt="<?= $escaper->escapeHtmlAttr($name) ?>"
                                     loading="lazy"
                                     width="640"
                                     height="480"
                                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"/>
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-transparent pointer-events-none"></div>
                        <div class="absolute bottom-0 left-0 right-0 p-4 sm:p-5">
                            <span class="text-lg sm:text-xl font-semibold text-white drop-shadow-sm">
                                <?= $escaper->escapeHtml($name) ?>
                            </span>
                            <span class="block mt-1 text-sm font-medium text-white/90 opacity-0 group-hover:opacity-100 transition-opacity">
                                <?= $escaper->escapeHtml(__('Shop now')) ?>
                                <span aria-hidden="true"> &rarr;</span>
                            </span>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
```

### Step 4: Safelist dynamic classes if any
The template above uses static literals only, so no safelist is needed. If you later inject a color from the ViewModel (e.g. per-category accent), add the full class strings to `tailwind.config.js`:

```js
// tailwind.config.js (Hyvä theme root)
module.exports = {
    content: [
        './app/code/Acme/StorePickup/view/frontend/templates/**/*.phtml',
        './app/design/Frontend/Hyva/*/Magento_*/templates/**/*.phtml',
    ],
    safelist: [
        'from-red-600',
        'from-blue-600',
        'from-green-600',
    ],
};
```

### Step 5: Build and verify
```bash
# If your Hyvä setup compiles Tailwind on the fly, just clear cache:
bin/magento cache:clean
# Otherwise rebuild the theme assets from the theme root:
# npm run build   (or `npm run watch` during development)
```

## Coding Rules Applied
- **Tailwind utility classes exclusively** (AGENTS.md 7.2): no LESS, no `_module.less`, no inline `<style>`. Theme extension via `tailwind.config.js`.
- **ViewModel preferred over Block methods**: a class implementing `ArgumentInterface`, injected via `<argument xsi:type="object">`, exposes typed getters — the template stays logic-light
- **Mobile-first responsive**: base styles for mobile, `sm:`/`md:`/`lg:`/`xl:` progressively enhance
- **Dynamic class names safelisted** so Tailwind's purge does not drop them
- **All output escaped**: `$escaper->escapeHtml`, `escapeUrl`, `escapeHtmlAttr`

## Verification
- [ ] Homepage (`/`) shows the section above the product list, no LESS/CSS errors in the network tab
- [ ] Resize to 375px width → 1 column; 640px → 2 columns; 1024px+ → 3 columns
- [ ] `view-source:` search for `_module.less` from this module — should be absent
- [ ] Disable all categories (set `is_active=0`) → section renders nothing (graceful degradation), no error in log
- [ ] Lighthouse mobile audit shows no render-blocking CSS from this module and image `loading="lazy"` is present
- [ ] Tab through the grid with the keyboard → each link receives a visible focus ring
- [ ] Run Tailwind's content scan: `npx tailwindcss --content './app/code/Acme/StorePickup/view/frontend/templates/**/*.phtml' --output /dev/null` lists the classes used (confirms they are picked up)

## Common Mistakes
- **Using LESS / custom CSS instead of Tailwind**: dropping a `_module.less` into `web/css/source/` does nothing in Hyvä (Hyvä compiles Tailwind only). Rewrite styles as utility classes or extend `tailwind.config.js`.
- **Putting logic in Block public methods**: the template calls `$block->getFeaturedCategories()`. Hyvä convention is the ViewModel pattern — data access in a separate `ArgumentInterface` class, the block stays a thin `Template`. This keeps the block cacheable and testable.
- **Dynamic class names purged**: `class="bg-<?= $accent ?>-600"` where `$accent` is a string — Tailwind cannot see the full class, so it is stripped. Use a lookup map of complete class strings or safelist.
- **Not mobile-first**: writing desktop styles as the base and using `sm:` to downgrade. Tailwind is mobile-first by default; base = mobile, modifiers scale UP.
- **Forgetting to pass the ViewModel in layout XML**: `$block->getData('view_model')` returns null and the template silently renders nothing. Always pair the template with `<argument name="view_model" xsi:type="object">`.
- **Block set to non-cacheable due to session data**: if the ViewModel reads customer/session-specific data, the whole block becomes uncacheable and harms FPC. For personalized sections, render the static shell server-side and hydrate via Alpine + a customer-section endpoint instead.
- **Unescaped output**: `<?= $category->getName() ?>` without `$escaper->escapeHtml()` allows stored XSS via category name. Always escape.
