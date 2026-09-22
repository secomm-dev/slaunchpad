<?php
/**
 * TASK-0NNZCW (SLP-213) v3 — seed PageBuilder master-format content into the
 * `home` CMS page so the homepage is editable in Admin (Content > Pages).
 * Backup of the previous content is written next to this script's log.
 */
require '/var/www/projects/slaunchpad/app/bootstrap.php';
$bootstrap = Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$state = $om->get(Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

$repo = $om->get(Magento\Cms\Api\PageRepositoryInterface::class);
$page = $repo->getById('home');

file_put_contents('/var/www/projects/slaunchpad/.ai/evidence/TASK-0NNZCW/home-content-before-v3.txt', $page->getContent() ?: '(empty)');

// --- reusable conditions (generated via Magento\Widget\Helper\Conditions::encode) ---
$condFlashsale = '^[`1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Combine`,`aggregator`:`all`,`value`:`1`,`new_child`:``^],`1--1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Product`,`attribute`:`category_ids`,`operator`:`==`,`value`:`46`^]^]';
$condLiving = '^[`1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Combine`,`aggregator`:`all`,`value`:`1`,`new_child`:``^],`1--1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Product`,`attribute`:`category_ids`,`operator`:`==`,`value`:`42`^]^]';
$condBedroom = '^[`1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Combine`,`aggregator`:`all`,`value`:`1`,`new_child`:``^],`1--1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Product`,`attribute`:`category_ids`,`operator`:`==`,`value`:`49`^]^]';
$condAccessories = '^[`1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Combine`,`aggregator`:`all`,`value`:`1`,`new_child`:``^],`1--1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Product`,`attribute`:`category_ids`,`operator`:`==`,`value`:`60`^]^]';

$productsWidget = function (string $cond, string $count) {
    return '{{widget type="Magento\\CatalogWidget\\Block\\Product\\ProductsList"'
        . ' appearance="carousel" show_pager="0" products_count="' . $count . '"'
        . ' template="Magento_PageBuilder::catalog/product/widget/content/carousel.phtml"'
        . ' conditions_encoded="' . $cond . '"}}';
};

$rowContained = fn(string $inner) => '<div data-content-type="row" data-appearance="contained" data-element="main"><div data-element="inner">' . $inner . '</div></div>';
$rowBleed = fn(string $inner) => '<div data-content-type="row" data-appearance="full-bleed" data-element="main">' . $inner . '</div>';
$text = fn(string $html) => '<div data-content-type="text" data-element="main">' . $html . '</div>';
$btn = fn(string $label, string $href = '#') => '<a class="hp-btn-primary" href="' . $href . '"><span>' . $label . '</span></a>';

$heading = fn(string $t, string $sub = '') => $text(
    '<div class="hp-section-head">'
    . '<h2 class="hp-heading">' . $t . '</h2>'
    . ($sub !== '' ? '<p class="hp-subheading">' . $sub . '</p>' : '')
    . '</div>'
);

// --- 1. hero slider (full-bleed) ---
$slide = function (string $h, string $p, string $cta, string $gradient) use ($text, $btn) {
    return '<div data-content-type="slide" data-appearance="poster" data-element="main" style="background-color: #9b8c6c; background-image: ' . $gradient . '; background-size: cover; background-position: center;">'
        . '<div class="pagebuilder-slide-wrapper" style="min-height: 560px;">'
        . '<div data-element="overlay" style="display: flex; align-items: flex-end; min-height: 560px;">'
        . '<div data-element="content" style="width: 100%;">'
        . '<div class="hp-hero-copy">'
        . $text('<h2 class="hp-hero-heading">' . $h . '</h2><p class="hp-hero-text">' . $p . '</p>')
        . '<div data-content-type="buttons" data-element="main">' . $btn($cta) . '</div>'
        . '</div>'
        . '</div></div></div></div>';
};
$grad1 = 'radial-gradient(120% 90% at 80% 20%, rgba(255,255,255,.35) 0%, transparent 55%), linear-gradient(135deg, #cbbd9f 0%, #9b8c6c 45%, #5c523d 100%)';
$grad2 = 'linear-gradient(135deg, #d8cbb2 0%, #a89a7c 50%, #6b5a40 100%)';
$grad3 = 'linear-gradient(135deg, #c9b79a 0%, #8a7a5c 55%, #4e4534 100%)';
$grad4 = 'linear-gradient(135deg, #e0d5c0 0%, #b3a284 55%, #6f6049 100%)';
$grad5 = 'linear-gradient(135deg, #cfc2a6 0%, #97876a 50%, #57503c 100%)';
$grad6 = 'linear-gradient(135deg, #d6c9b0 0%, #a2937a 50%, #63573f 100%)';
$heroSlide = fn(string $h, string $p, string $g) => $slide($h, $p, 'Mua sắm bộ sưu tập', $g);
$heroText = 'Chế tác cho cuộc sống|Nội thất vượt thời gian, chất liệu tự nhiên và chi tiết tinh tế cho không gian hiện đại.';
$heroSlides = '';
foreach ([$grad1, $grad2, $grad3, $grad4, $grad5, $grad6] as $g) {
    $heroSlides .= $heroSlide('Chế tác cho cuộc sống', 'Nội thất vượt thời gian, chất liệu tự nhiên và chi tiết tinh tế cho không gian hiện đại.', $g);
}
$hero = $rowBleed(
    '<div data-content-type="slider" data-appearance="default" data-element="main"'
    . ' data-autoplay="false" data-autoplay-speed="4000" data-fade="false"'
    . ' data-infinite-loop="true" data-show-arrows="true" data-show-dots="true">'
    . $heroSlides
    . '</div>'
);

// --- 2. category strip ---
$catIcon = fn(string $svg) => $svg;
$cats = [
    ['GHẾ', '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-cat-icon"><path d="M9 4v14M23 4v14M9 18h14M11 18v6M21 18v6M9 14c0-2 2-3 7-3s7 1 7 3"/></svg>'],
    ['BÀN', '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-cat-icon"><ellipse cx="16" cy="10" rx="12" ry="3.4"/><path d="M11 13.2c1 5-.4 9-2 12M21 13.2c-1 5 .4 9 2 12M16 13.5V25"/></svg>'],
    ['TỦ', '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-cat-icon"><rect x="8" y="4" width="16" height="24" rx="1"/><path d="M16 4v24M12 14h2.5M17.5 14H20M12 20h2.5M17.5 20H20"/></svg>'],
    ['ĐÈN', '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-cat-icon"><path d="M8 28h10M13 28c0-8 0-14-1-18"/><path d="M12 10a5.5 5.5 0 0 1 11 0c0 3-2.4 4.6-5.5 4.6S12 13 12 10Z" fill="currentColor" stroke="none" opacity=".85"/></svg>'],
    ['GIƯỜNG', '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-cat-icon"><path d="M4 26V10M28 26v-8M4 18h24M6 18v-4a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v4"/><path d="M23 18v-3a2 2 0 0 1 2-2h1"/></svg>'],
    ['TỦ ĐẦU GIƯỜNG', '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-cat-icon"><rect x="7" y="9" width="18" height="15" rx="1"/><path d="M7 16.5h18M11 13h1.5M19.5 13H21M11 21h1.5M19.5 21H21M10 24v3M22 24v3"/></svg>'],
    ['GHẾ SOFA', '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-cat-icon"><path d="M6 14v-3a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v3"/><path d="M4 20a2 2 0 0 1 2-2h20a2 2 0 0 1 2 2v4H4Z"/><path d="M7 16v-2M25 16v-2M6 24v2M26 24v2"/></svg>'],
    ['THẢM', '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-cat-icon"><rect x="7" y="4" width="18" height="24" rx="1.5"/><path d="M11 8h10M11 24h10M11 12h10M11 20h10"/></svg>'],
];
$catTiles = '';
foreach ($cats as [$label, $svg]) {
    $catTiles .= '<a href="#" class="hp-cat-tile">' . $svg . '<span class="hp-cat-label">' . $label . '</span></a>';
}
$categories = $rowContained(
    $text('<div class="hp-section-head"><h2 class="hp-heading">Danh mục</h2></div><div class="hp-category-strip">' . $catTiles . '</div>')
);

// --- 3. flash sale (heading + countdown + products) ---
$countdown = '<div class="hp-flash-head"><h2 class="hp-heading">Siêu sale</h2>'
    . '<div class="hp-countdown" x-data="{ remaining: 3599, formatted() { const h = String(Math.floor(this.remaining / 3600)).padStart(2, \'0\'); const m = String(Math.floor((this.remaining % 3600) / 60)).padStart(2, \'0\'); const s = String(this.remaining % 60).padStart(2, \'0\'); return h + \':\' + m + \':\' + s; }, start() { this._timer = setInterval(() => { this.remaining = this.remaining > 0 ? this.remaining - 1 : 3599; }, 1000); }, destroy() { clearInterval(this._timer); } }" x-init="start()">'
    . '<span class="hp-countdown-label">Kết thúc sau</span>'
    . '<span class="hp-countdown-value" x-text="formatted()">01:00:00</span></div></div>';
$flashSale = $rowBleed(
    $text($countdown)
    . '<div data-content-type="products" data-appearance="carousel" data-show-dots="true" data-element="main">'
    . $productsWidget($condFlashsale, '8')
    . '</div>'
    . $text('<div class="hp-center-cta"><a class="hp-btn-primary" href="#"><span>Xem tất cả</span></a></div>')
);

// --- 4. everyday more value (4 draggable PB banners) ---
$banner = function () {
    return '<div data-content-type="column" data-appearance="full-height" data-element="main" class="hp-col-4">'
        . '<div data-content-type="banner" data-appearance="poster" data-show-button="never" data-show-overlay="always" data-element="main" style="background-color: #3d5245; min-height: 340px;">'
        . '<div data-element="overlay" style="display: flex; align-items: center;">'
        . '<div data-element="content" style="width: 100%;">'
        . '<div class="hp-banner-copy">'
        . '<p class="hp-banner-eyebrow">Giá trị hơn mỗi ngày</p>'
        . '<p class="hp-banner-heading">Mua tất cả sản phẩm giá tốt</p>'
        . '<span class="hp-banner-arrow">&#8594;</span>'
        . '</div>'
        . '</div></div></div></div>';
};
$valueBanners = $rowContained(
    $text('<div class="hp-section-head"><h2 class="hp-heading">Giá trị hơn mỗi ngày</h2><p class="hp-subheading">Chất lượng bạn tin, giá bạn ưng. Từ 20/08/26 - 25/08/27. Mua ngay!</p></div>')
    . '<div data-content-type="column-group" data-appearance="default" data-element="main" class="hp-banner-grid">'
    . $banner() . $banner() . $banner() . $banner()
    . '</div>'
);

// --- 5. living, reimagined (split) ---
$split = $rowContained(
    '<div data-content-type="column-group" data-appearance="default" data-element="main" class="hp-split-grid">'
    . '<div data-content-type="column" data-appearance="full-height" data-element="main" class="hp-split-media-col">'
    . '<div class="hp-split-media" style="background: radial-gradient(90% 70% at 70% 30%, rgba(255,255,255,.4) 0%, transparent 60%), linear-gradient(135deg, #e5d9c4 0%, #c2ab88 50%, #7a6a4f 100%); min-height: 460px;"></div>'
    . '</div>'
    . '<div data-content-type="column" data-appearance="full-height" data-element="main" class="hp-split-copy-col">'
    . '<div class="hp-split-copy">'
    . '<h2 class="hp-heading">Không gian sống, tái định nghĩa</h2>'
    . '<p class="hp-body-text">Khám phá bộ sưu tập nội thất mới, được chế tác cho sự thoải mái, bền bỉ và phong cách dễ dàng. Khám phá những món nội thất vượt thời gian, chất liệu sáng tạo và thiết kế tinh tế. Tạo nên không gian vừa thoải mái vừa tinh tế. Khám phá tính năng liền mạch, thanh lịch và hiệu quả trong bộ sưu tập nội thất của chúng tôi.</p>'
    . '<a class="hp-btn-primary" href="#"><span>Xem tất cả</span></a>'
    . '</div>'
    . '</div>'
    . '</div>'
);

// --- 6. product collections (3 carousels + CTA) ---
$collections = $rowContained(
    $text('<div class="hp-section-head"><h2 class="hp-heading">Phòng khách</h2></div>')
    . '<div data-content-type="products" data-appearance="carousel" data-show-dots="true" data-element="main">' . $productsWidget($condLiving, '8') . '</div>'
    . $text('<div class="hp-section-head hp-section-head-gap"><h2 class="hp-heading">Phòng ngủ</h2></div>')
    . '<div data-content-type="products" data-appearance="carousel" data-show-dots="true" data-element="main">' . $productsWidget($condBedroom, '8') . '</div>'
    . $text('<div class="hp-section-head hp-section-head-gap"><h2 class="hp-heading">Phụ kiện</h2></div>')
    . '<div data-content-type="products" data-appearance="carousel" data-show-dots="true" data-element="main">' . $productsWidget($condAccessories, '8') . '</div>'
    . $text('<div class="hp-center-cta hp-center-cta-gap"><a class="hp-btn-primary" href="#"><span>Đến bộ sưu tập</span></a></div>')
);

// --- 7. real spaces ---
$realSpaces = $rowBleed(
    $text(
        '<div class="hp-realspaces-media" style="background: radial-gradient(80% 60% at 30% 40%, rgba(255,255,255,.25) 0%, transparent 55%), linear-gradient(160deg, #cdbfa4 0%, #a08b68 55%, #6b5a40 100%); aspect-ratio: 16/7;">'
        . '<div class="hp-video-controls">'
        . '<button type="button" class="hp-video-btn hp-video-btn-sm" aria-label="Lùi 15 giây"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-video-icon"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg></button>'
        . '<button type="button" class="hp-video-btn hp-video-btn-lg" aria-label="Phát video"><svg viewBox="0 0 24 24" fill="currentColor" class="hp-video-icon"><path d="M8 5.5v13l11-6.5Z"/></svg></button>'
        . '<button type="button" class="hp-video-btn hp-video-btn-sm" aria-label="Tới 15 giây"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-video-icon"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg></button>'
        . '</div></div>'
        . '<div class="hp-container"><h2 class="hp-heading hp-heading-caps">KHÔNG GIAN THẬT</h2>'
        . '<p class="hp-subheading">Trải nghiệm sản phẩm trong không gian thật.</p>'
        . '<a class="hp-btn-outline" href="#"><span>Khám phá</span></a></div>'
    )
);

// --- 8. sofa banner (full-bleed PB banner) ---
$sofaBanner = $rowBleed(
    '<div data-content-type="banner" data-appearance="poster" data-show-button="never" data-show-overlay="always" data-element="main"'
    . ' style="background-color: #c2ab88; background-image: radial-gradient(90% 70% at 70% 30%, rgba(255,255,255,.4) 0%, transparent 60%), linear-gradient(135deg, #e5d9c4 0%, #c2ab88 50%, #7a6a4f 100%); background-size: cover; background-position: center; min-height: 600px;">'
    . '<div data-element="overlay" style="display: flex; align-items: flex-end;">'
    . '<div data-element="content" style="width: 100%;">'
    . '<div class="hp-banner-sofa-copy">'
    . '<p class="hp-eyebrow">Sẵn sàng giao hàng</p>'
    . '<h2 class="hp-banner-sofa-heading">Sofa mới cho mùa thu</h2>'
    . '<p class="hp-banner-sofa-text">Tìm phong cách hoàn hảo cho cả gia đình</p>'
    . '<a class="hp-btn-light" href="#"><span>Mua sofa có sẵn</span></a>'
    . '</div>'
    . '</div></div></div>'
);

// --- 9. why us ---
$usp = function (string $icon, string $title, string $body) {
    return '<div class="hp-usp-card">'
        . '<span class="hp-usp-icon">' . $icon . '</span>'
        . '<h3 class="hp-usp-title">' . $title . '</h3>'
        . '<p class="hp-usp-text">' . $body . '</p>'
        . '</div>';
};
$svgWrap = fn(string $inner) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-usp-svg">' . $inner . '</svg>';
$uspSection = $rowContained(
    $text(
        '<div class="hp-section-head hp-section-head-center"><h2 class="hp-heading">Vì sao chọn chúng tôi?</h2></div>'
        . '<div class="hp-usp-grid">'
        . $usp($svgWrap('<path d="M12 3 5 5.6v5.2c0 4.4 2.9 8.5 7 9.7 4.1-1.2 7-5.3 7-9.7V5.6Z"/><path d="m9.2 12 2 2 3.6-3.8"/>'), 'An toàn và bảo mật', 'Mua sắm yên tâm vì mọi đơn hàng trực tuyến của bạn đều được bảo vệ bởi các biện pháp bảo mật hiện đại.')
        . $usp($svgWrap('<path d="M2 7h11v9H2zM13 10h4l3 3v3h-7z"/><circle cx="6.5" cy="17.5" r="1.6"/><circle cx="16.5" cy="17.5" r="1.6"/>'), 'Miễn phí vận chuyển', 'Tận hưởng tiện lợi vận chuyển miễn phí cho mọi đơn hàng, giúp bạn dễ dàng nhận những sản phẩm yêu thích đến tận cửa.')
        . $usp($svgWrap('<rect x="4" y="9" width="16" height="4"/><path d="M6 13v7h12v-7M12 9v11M12 9s-4.5.2-4.5-2.4C7.5 4.6 11 5 12 9Zm0 0s4.5.2 4.5-2.4C16.5 4.6 13 5 12 9Z"/>'), 'Gói quà tinh tế', 'Nâng tầm trải nghiệm tặng quà với dịch vụ gói quà tinh tế, thêm chút sang trọng cho mỗi món quà.')
        . $usp($svgWrap('<path d="M3 3h8l10 10-8 8L3 11Z"/><circle cx="8" cy="8" r="1.5"/>'), 'Ưu đãi tốt nhất', 'Khám phá ưu đãi và tiết kiệm trực tuyến vượt trội, đảm bảo bạn nhận giá trị tốt nhất cho mỗi lần mua sắm.')
        . '</div>'
    )
);

// --- 10. journal ---
$journalCard = fn(string $gradient) => '<div class="hp-journal-card">'
    . '<div class="hp-journal-media" style="background: ' . $gradient . '; aspect-ratio: 4/3;"></div>'
    . '<h3 class="hp-journal-title">Không gian sống, tái định nghĩa</h3>'
    . '<p class="hp-journal-text">Khám phá bộ sưu tập nội thất mới, được chế tác cho sự thoải mái, bền bỉ và phong cách dễ dàng. Khám phá những món nội thất vượt thời gian, chất liệu sáng tạo và thiết kế tinh tế.</p>'
    . '</div>';
$journal = $rowContained(
    $text(
        '<div class="hp-section-head hp-section-head-center"><h2 class="hp-heading">Bài viết</h2></div>'
        . '<div class="hp-journal-grid">'
        . $journalCard('linear-gradient(160deg, #e8e0d2 0%, #b9a887 60%, #7d6c4e 100%)')
        . $journalCard('linear-gradient(160deg, #dde5da 0%, #a9bca4 60%, #6f8468 100%)')
        . $journalCard('linear-gradient(160deg, #e5dadb 0%, #c4a4a7 60%, #8a6468 100%)')
        . '</div>'
        . '<div class="hp-center-cta"><a class="hp-btn-primary" href="#"><span>Xem tất cả bài viết</span></a></div>'
    )
);

$content = $hero . $categories . $flashSale . $valueBanners . $split . $collections . $realSpaces . $sofaBanner . $uspSection . $journal;

$page->setContent($content);
$repo->save($page);
echo "saved — content length: " . strlen($content) . "\n";
