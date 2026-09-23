<?php
/**
 * TASK-0NNZCW (SLP-213) v3.1 — banner/hero styling moves INTO the CMS content
 * (inline styles, PageBuilder-editable) per user direction; product widget
 * styling stays in theme CSS (hp-pb-* / hp-card-*).
 */
require '/var/www/projects/slaunchpad/app/bootstrap.php';
$bootstrap = Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$state = $om->get(Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

$repo = $om->get(Magento\Cms\Api\PageRepositoryInterface::class);
$page = $repo->getById('home');
file_put_contents('/var/www/projects/slaunchpad/.ai/evidence/TASK-0NNZCW/home-content-before-v31.txt', $page->getContent() ?: '(empty)');

$condFlashsale = '^[`1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Combine`,`aggregator`:`all`,`value`:`1`,`new_child`:``^],`1--1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Product`,`attribute`:`category_ids`,`operator`:`==`,`value`:`46`^]^]';
$condLiving = '^[`1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Combine`,`aggregator`:`all`,`value`:`1`,`new_child`:``^],`1--1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Product`,`attribute`:`category_ids`,`operator`:`==`,`value`:`42`^]^]';
$condBedroom = '^[`1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Combine`,`aggregator`:`all`,`value`:`1`,`new_child`:``^],`1--1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Product`,`attribute`:`category_ids`,`operator`:`==`,`value`:`49`^]^]';
$condAccessories = '^[`1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Combine`,`aggregator`:`all`,`value`:`1`,`new_child`:``^],`1--1`:^[`type`:`Magento||CatalogRule||Model||Rule||Condition||Product`,`attribute`:`category_ids`,`operator`:`==`,`value`:`60`^]^]';

$productsWidget = fn(string $cond, string $count) => '{{widget type="Magento\\CatalogWidget\\Block\\Product\\ProductsList"'
    . ' appearance="carousel" show_pager="0" products_count="' . $count . '"'
    . ' template="Magento_PageBuilder::catalog/product/widget/content/carousel.phtml"'
    . ' conditions_encoded="' . $cond . '"}}';

$rowContained = fn(string $inner) => '<div data-content-type="row" data-appearance="contained" data-element="main"><div data-element="inner">' . $inner . '</div></div>';
$rowBleed = fn(string $inner) => '<div data-content-type="row" data-appearance="full-bleed" data-element="main">' . $inner . '</div>';
$text = fn(string $html) => '<div data-content-type="text" data-element="main">' . $html . '</div>';

/* ---- banner copy: full inline styles (PageBuilder-editable) ---- */
$bannerInner = '<p style="margin:0;font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.8)">Giá trị hơn mỗi ngày</p>'
    . '<p style="margin:0;font-size:26px;line-height:1.25;font-weight:700;color:#ffffff">Mua tất cả sản phẩm giá tốt</p>'
    . '<span style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border:1px solid rgba(255,255,255,.7);border-radius:9999px;color:#ffffff;font-size:16px">&#8594;</span>';
$bannerColumn = '<div data-content-type="column" data-appearance="full-height" data-element="main">'
    . '<div data-content-type="banner" data-appearance="poster" data-show-button="never" data-show-overlay="always" data-element="main"'
    . ' style="background-color: #3d5245; min-height: 340px;">'
    . '<div data-element="overlay" style="display: flex; align-items: center;">'
    . '<div data-element="content" style="width: 100%;">'
    . '<div style="display:flex;height:100%;min-height:340px;flex-direction:column;justify-content:space-between;gap:24px;padding:28px">' . $bannerInner . '</div>'
    . '</div></div></div></div>';

$valueBanners = $rowContained(
    $text('<div class="hp-section-head"><h2 class="hp-heading">Giá trị hơn mỗi ngày</h2><p class="hp-subheading">Chất lượng bạn tin, giá bạn ưng. Từ 20/08/26 - 25/08/27. Mua ngay!</p></div>')
    . '<div data-content-type="column-group" data-appearance="default" data-element="main" class="hp-banner-grid">'
    . $bannerColumn . $bannerColumn . $bannerColumn . $bannerColumn
    . '</div>'
);

/* ---- hero slides: inline typography per slide (editable per slide in PB) ---- */
$heroSlide = function (string $gradient) use ($text) {
    $copy = '<div style="max-width: 1200px; margin: 0 auto; padding: 0 24px 64px">'
        . '<h2 style="margin:0 0 12px;font-size:36px;line-height:1.15;font-weight:700;color:#ffffff">Chế tác cho cuộc sống</h2>'
        . '<p style="margin:0 0 24px;max-width:28rem;font-size:16px;line-height:1.6;color:rgba(255,255,255,.9)">Nội thất vượt thời gian, chất liệu tự nhiên và chi tiết tinh tế cho không gian hiện đại.</p>'
        . '<a href="#" style="display:inline-block;background-color:#3d5245;color:#ffffff;padding:11px 22px;font-size:14px;text-decoration:none;border-radius:6px"><span>Mua sắm bộ sưu tập</span></a>'
        . '</div>';
    return '<div data-content-type="slide" data-appearance="poster" data-element="main"'
        . ' style="background-color: #9b8c6c; background-image: ' . $gradient . '; background-size: cover; background-position: center;">'
        . '<div class="pagebuilder-slide-wrapper" style="min-height: 560px;">'
        . '<div data-element="overlay" style="display: flex; align-items: flex-end; min-height: 560px;">'
        . '<div data-element="content" style="width: 100%;">'
        . $text($copy)
        . '</div></div></div></div>';
};
$gradients = [
    'radial-gradient(120% 90% at 80% 20%, rgba(255,255,255,.35) 0%, transparent 55%), linear-gradient(135deg, #cbbd9f 0%, #9b8c6c 45%, #5c523d 100%)',
    'linear-gradient(135deg, #d8cbb2 0%, #a89a7c 50%, #6b5a40 100%)',
    'linear-gradient(135deg, #c9b79a 0%, #8a7a5c 55%, #4e4534 100%)',
    'linear-gradient(135deg, #e0d5c0 0%, #b3a284 55%, #6f6049 100%)',
    'linear-gradient(135deg, #cfc2a6 0%, #97876a 50%, #57503c 100%)',
    'linear-gradient(135deg, #d6c9b0 0%, #a2937a 50%, #63573f 100%)',
];
$heroSlides = '';
foreach ($gradients as $g) {
    $heroSlides .= $heroSlide($g);
}
$hero = $rowBleed(
    '<div data-content-type="slider" data-appearance="default" data-element="main"'
    . ' data-autoplay="false" data-autoplay-speed="4000" data-fade="false"'
    . ' data-infinite-loop="true" data-show-arrows="true" data-show-dots="true">'
    . $heroSlides . '</div>'
);

/* ---- sofa banner: inline styles ---- */
$sofaBanner = $rowBleed(
    '<div data-content-type="banner" data-appearance="poster" data-show-button="never" data-show-overlay="always" data-element="main"'
    . ' style="background-color: #c2ab88; background-image: radial-gradient(90% 70% at 70% 30%, rgba(255,255,255,.4) 0%, transparent 60%), linear-gradient(135deg, #e5d9c4 0%, #c2ab88 50%, #7a6a4f 100%); background-size: cover; background-position: center; min-height: 600px;">'
    . '<div data-element="overlay" style="display: flex; align-items: flex-end;">'
    . '<div data-element="content" style="width: 100%;">'
    . '<div style="max-width: 1200px; margin: 0 auto; padding: 0 24px 56px">'
    . '<p style="margin:0 0 8px;font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.9)">Sẵn sàng giao hàng</p>'
    . '<h2 style="margin:0 0 8px;font-size:40px;font-weight:700;color:#ffffff">Sofa mới cho mùa thu</h2>'
    . '<p style="margin:0 0 24px;font-size:15px;color:rgba(255,255,255,.9)">Tìm phong cách hoàn hảo cho cả gia đình</p>'
    . '<a href="#" style="display:inline-block;background-color:#ffffff;color:#1f2937;padding:11px 22px;font-size:14px;text-decoration:none;border-radius:6px"><span>Mua sofa có sẵn</span></a>'
    . '</div>'
    . '</div></div></div>'
);

/* ---- other sections unchanged from v3 (products widget = CSS in code) ---- */
$countdown = '<div class="hp-flash-head"><h2 class="hp-heading">Siêu sale</h2>'
    . '<div class="hp-countdown" x-data="{ remaining: 3599, formatted() { const h = String(Math.floor(this.remaining / 3600)).padStart(2, \'0\'); const m = String(Math.floor((this.remaining % 3600) / 60)).padStart(2, \'0\'); const s = String(this.remaining % 60).padStart(2, \'0\'); return h + \':\' + m + \':\' + s; }, start() { this._timer = setInterval(() => { this.remaining = this.remaining > 0 ? this.remaining - 1 : 3599; }, 1000); }, destroy() { clearInterval(this._timer); } }" x-init="start()">'
    . '<span class="hp-countdown-label">Kết thúc sau</span>'
    . '<span class="hp-countdown-value" x-text="formatted()">01:00:00</span></div></div>';
$flashSale = $rowBleed(
    $text($countdown)
    . '<div data-content-type="products" data-appearance="carousel" data-show-dots="true" data-element="main">'
    . $productsWidget($condFlashsale, '8') . '</div>'
    . $text('<div class="hp-center-cta"><a class="hp-btn-primary" href="#"><span>Xem tất cả</span></a></div>')
);

$cats = [
    ['GHẾ', '<path d="M9 4v14M23 4v14M9 18h14M11 18v6M21 18v6M9 14c0-2 2-3 7-3s7 1 7 3"/>'],
    ['BÀN', '<ellipse cx="16" cy="10" rx="12" ry="3.4"/><path d="M11 13.2c1 5-.4 9-2 12M21 13.2c-1 5 .4 9 2 12M16 13.5V25"/>'],
    ['TỦ', '<rect x="8" y="4" width="16" height="24" rx="1"/><path d="M16 4v24M12 14h2.5M17.5 14H20M12 20h2.5M17.5 20H20"/>'],
    ['ĐÈN', '<path d="M8 28h10M13 28c0-8 0-14-1-18"/><path d="M12 10a5.5 5.5 0 0 1 11 0c0 3-2.4 4.6-5.5 4.6S12 13 12 10Z" fill="currentColor" stroke="none" opacity=".85"/>'],
    ['GIƯỜNG', '<path d="M4 26V10M28 26v-8M4 18h24M6 18v-4a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v4"/><path d="M23 18v-3a2 2 0 0 1 2-2h1"/>'],
    ['TỦ ĐẦU GIƯỜNG', '<rect x="7" y="9" width="18" height="15" rx="1"/><path d="M7 16.5h18M11 13h1.5M19.5 13H21M11 21h1.5M19.5 21H21M10 24v3M22 24v3"/>'],
    ['GHẾ SOFA', '<path d="M6 14v-3a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v3"/><path d="M4 20a2 2 0 0 1 2-2h20a2 2 0 0 1 2 2v4H4Z"/><path d="M7 16v-2M25 16v-2M6 24v2M26 24v2"/>'],
    ['THẢM', '<rect x="7" y="4" width="18" height="24" rx="1.5"/><path d="M11 8h10M11 24h10M11 12h10M11 20h10"/>'],
];
$catTiles = '';
foreach ($cats as [$label, $path]) {
    $catTiles .= '<a href="#" class="hp-cat-tile"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-cat-icon">' . $path . '</svg><span class="hp-cat-label">' . $label . '</span></a>';
}
$categories = $rowContained(
    $text('<div class="hp-section-head"><h2 class="hp-heading">Danh mục</h2></div><div class="hp-category-strip">' . $catTiles . '</div>')
);

$split = $rowContained(
    '<div data-content-type="column-group" data-appearance="default" data-element="main" class="hp-split-grid">'
    . '<div data-content-type="column" data-appearance="full-height" data-element="main" class="hp-split-media-col">'
    . '<div class="hp-split-media" style="background: radial-gradient(90% 70% at 70% 30%, rgba(255,255,255,.4) 0%, transparent 60%), linear-gradient(135deg, #e5d9c4 0%, #c2ab88 50%, #7a6a4f 100%); min-height: 460px;"></div>'
    . '</div>'
    . '<div data-content-type="column" data-appearance="full-height" data-element="main" class="hp-split-copy-col">'
    . '<div class="hp-split-copy"><h2 class="hp-heading">Không gian sống, tái định nghĩa</h2>'
    . '<p class="hp-body-text">Khám phá bộ sưu tập nội thất mới, được chế tác cho sự thoải mái, bền bỉ và phong cách dễ dàng. Khám phá những món nội thất vượt thời gian, chất liệu sáng tạo và thiết kế tinh tế. Tạo nên không gian vừa thoải mái vừa tinh tế. Khám phá tính năng liền mạch, thanh lịch và hiệu quả trong bộ sưu tập nội thất của chúng tôi.</p>'
    . '<a class="hp-btn-primary" href="#"><span>Xem tất cả</span></a></div>'
    . '</div></div>'
);

$collections = $rowContained(
    $text('<div class="hp-section-head"><h2 class="hp-heading">Phòng khách</h2></div>')
    . '<div data-content-type="products" data-appearance="carousel" data-show-dots="true" data-element="main">' . $productsWidget($condLiving, '8') . '</div>'
    . $text('<div class="hp-section-head hp-section-head-gap"><h2 class="hp-heading">Phòng ngủ</h2></div>')
    . '<div data-content-type="products" data-appearance="carousel" data-show-dots="true" data-element="main">' . $productsWidget($condBedroom, '8') . '</div>'
    . $text('<div class="hp-section-head hp-section-head-gap"><h2 class="hp-heading">Phụ kiện</h2></div>')
    . '<div data-content-type="products" data-appearance="carousel" data-show-dots="true" data-element="main">' . $productsWidget($condAccessories, '8') . '</div>'
    . $text('<div class="hp-center-cta hp-center-cta-gap"><a class="hp-btn-primary" href="#"><span>Đến bộ sưu tập</span></a></div>')
);

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

$usp = fn(string $icon, string $title, string $body) => '<div class="hp-usp-card"><span class="hp-usp-icon">'
    . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="hp-usp-svg">' . $icon . '</svg></span>'
    . '<h3 class="hp-usp-title">' . $title . '</h3><p class="hp-usp-text">' . $body . '</p></div>';
$uspSection = $rowContained(
    $text(
        '<div class="hp-section-head hp-section-head-center"><h2 class="hp-heading">Vì sao chọn chúng tôi?</h2></div>'
        . '<div class="hp-usp-grid">'
        . $usp('<path d="M12 3 5 5.6v5.2c0 4.4 2.9 8.5 7 9.7 4.1-1.2 7-5.3 7-9.7V5.6Z"/><path d="m9.2 12 2 2 3.6-3.8"/>', 'An toàn và bảo mật', 'Mua sắm yên tâm vì mọi đơn hàng trực tuyến của bạn đều được bảo vệ bởi các biện pháp bảo mật hiện đại.')
        . $usp('<path d="M2 7h11v9H2zM13 10h4l3 3v3h-7z"/><circle cx="6.5" cy="17.5" r="1.6"/><circle cx="16.5" cy="17.5" r="1.6"/>', 'Miễn phí vận chuyển', 'Tận hưởng tiện lợi vận chuyển miễn phí cho mọi đơn hàng, giúp bạn dễ dàng nhận những sản phẩm yêu thích đến tận cửa.')
        . $usp('<rect x="4" y="9" width="16" height="4"/><path d="M6 13v7h12v-7M12 9v11M12 9s-4.5.2-4.5-2.4C7.5 4.6 11 5 12 9Zm0 0s4.5.2 4.5-2.4C16.5 4.6 13 5 12 9Z"/>', 'Gói quà tinh tế', 'Nâng tầm trải nghiệm tặng quà với dịch vụ gói quà tinh tế, thêm chút sang trọng cho mỗi món quà.')
        . $usp('<path d="M3 3h8l10 10-8 8L3 11Z"/><circle cx="8" cy="8" r="1.5"/>', 'Ưu đãi tốt nhất', 'Khám phá ưu đãi và tiết kiệm trực tuyến vượt trội, đảm bảo bạn nhận giá trị tốt nhất cho mỗi lần mua sắm.')
        . '</div>'
    )
);

$journalCard = fn(string $gradient) => '<div class="hp-journal-card">'
    . '<div class="hp-journal-media" style="background: ' . $gradient . '; aspect-ratio: 4/3;"></div>'
    . '<h3 class="hp-journal-title">Không gian sống, tái định nghĩa</h3>'
    . '<p class="hp-journal-text">Khám phá bộ sưu tập nội thất mới, được chế tác cho sự thoải mái, bền bỉ và phong cách dễ dàng. Khám phá những món nội thất vượt thời gian, chất liệu sáng tạo và thiết kế tinh tế.</p></div>';
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
echo "saved v3.1 — content length: " . strlen($content) . "\n";
