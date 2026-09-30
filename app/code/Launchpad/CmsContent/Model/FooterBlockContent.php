<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Model;

/**
 * Footer CMS block content builders (TASK-7EYJ4C v3.1, SLP-275).
 *
 * Content is native PageBuilder elements only (Row / Column group / Heading /
 * Text / Image) so the admin edits everything visually in the PB stage — no
 * "Edit HTML Code" surfaces. Tailwind classes ride in each element's
 * "CSS Classes" field (compiled through the generated safelist, see
 * regen-safelist evidence script). The newsletter form is embedded as a
 * {{block}} directive inside a Text element (WYSIWYG) — not an html element
 * (that would face the admin with "Edit HTML Code") and not a static-block
 * widget (Hyva CMS JIT two-pass rendering empties it, TASK-0NNZCW). The
 * directive itself is code-owned: admins edit the heading, not the directive.
 *
 * Styling contract: section containers + the rules that Tailwind classes
 * cannot own (homepage.css !important bleed counters, column-line containers,
 * accordion chrome) live in the theme footer.css / footer.phtml
 * (strip-proof, TASK-0NNZCW v4.5); everything else is classes in content.
 */
final class FooterBlockContent
{
    private const HEADING_BAND = 'text-center text-[20px] font-medium uppercase leading-[28px] text-black';

    /**
     * @return array<string, array{0: string, 1: array<int, string>}> identifier => [title, [store0, store1]]
     */
    public static function blocks(): array
    {
        return [
            'footer_newsletter' => ['Footer Newsletter', [self::newsletter('Subscribe to our newsletter'), self::newsletter('HÃY LIÊN HỆ VỚI CHÚNG TÔI!')]],
            'footer_links' => ['Footer Links', [self::links(self::linksEn()), self::links(self::linksVi())]],
            'footer_social' => ['Footer Social', [self::social('Follow us on'), self::social('THEO DÕI CHÚNG TÔI TẠI')]],
            'footer_trust_payments' => ['Footer Trust & Payments', [self::trust(), self::trust()]],
        ];
    }

    public static function newsletter(string $heading): string
    {
        // The form is embedded as a {{block}} directive inside a Text element
        // (WYSIWYG) — NOT an html element (admin would face the "Edit HTML
        // Code" dialog) and NOT a static-block widget (Hyva CMS JIT two-pass
        // rendering empties it, TASK-0NNZCW). The directive is code-owned:
        // admins leave it as-is and edit the heading above it.
        return '<div data-content-type="row" data-appearance="full-width" data-background-images="{}"'
            . ' data-video-fallback-src="" data-element="main"><div class="row-full-width-inner" data-element="inner">'
            . '<h2 class="' . self::HEADING_BAND . '" data-content-type="heading" data-appearance="default"'
            . ' data-element="main">' . $heading . '</h2>'
            . '<div class="footer-newsletter-form" data-content-type="text" data-appearance="default"'
            . ' data-element="main">'
            . '{{block class="Launchpad\\CmsContent\\Block\\Newsletter\\Subscribe"'
            . ' template="Launchpad_CmsContent::newsletter/subscribe.phtml"}}'
            . '</div></div></div>';
    }

    /**
     * @param list<array{0: string, 1: list<array{0: string, 1: string}>, 2?: bool}> $groups [title, links, open?]
     */
    public static function links(array $groups): string
    {
        $columns = '';
        foreach ($groups as [$title, $links, $open]) {
            $items = '';
            foreach ($links as [$label, $url]) {
                $items .= '<li><a href="{{store url="' . $url . '"}}">' . $label . '</a></li>';
            }
            $columns .= '<div class="pagebuilder-column footer-links-group'
                . (!empty($open) ? ' footer-links-open' : '')
                . ' border-b border-[#e4e7ec] py-5 lg:border-b-0 lg:py-0 lg:flex-1"'
                . ' data-content-type="column" data-appearance="full-height" data-background-images="{}" data-grid-size="3" data-element="main">'
                . '<h3 class="footer-links-title flex items-center justify-between text-[16px] font-medium'
                . ' leading-[24px] text-[#364153] lg:pointer-events-none" data-content-type="heading"'
                . ' data-appearance="default" data-element="main">' . $title . '</h3>'
                . '<div class="footer-links-list mt-4 [&_ul]:m-0 [&_ul]:list-none [&_ul]:p-0 [&_li]:py-1.5'
                . ' [&_a]:text-[16px] [&_a]:leading-[24px] [&_a]:text-[#4a5565] [&_a]:transition-colors'
                . ' hover:[&_a]:text-[#101828]" data-content-type="text" data-appearance="default"'
                . ' data-element="main"><ul>' . $items . '</ul></div></div>';
        }

        return '<div data-content-type="row" data-appearance="full-width" data-background-images="{}"'
            . ' data-video-fallback-src="" data-element="main"><div class="row-full-width-inner" data-element="inner">'
            . '<div class="pagebuilder-column-group" data-background-images="{}" data-content-type="column-group"'
            . ' data-appearance="default" data-grid-size="12" data-background-lazy-load="" data-element="main">'
            . '<div class="pagebuilder-column-line" data-content-type="column-line" data-element="main">'
            . $columns
            . '</div></div></div></div>';
    }

    /**
     * @return list<array{0: string, 1: list<array{0: string, 1: string}>, 2: bool}>
     */
    public static function linksEn(): array
    {
        return [
            ['Company', [['About', 'about-us'], ['Order &amp; Return', 'sales/guest/form/'], ['Keyword', 'search/term/popular/'], ['Get in touch', 'contact']], true],
            ['Legal', [['Privacy', 'privacy-policy-cookie-restriction-mode'], ['Terms and Conditions', 'terms-and-conditions']], false],
            ['Our Company', [['About us', 'about-us'], ['Blog', 'blog'], ['Our stores', 'our-stores'], ['Sustainability', 'sustainability']], false],
            ['My Account', [['My account', 'customer/account/'], ['My orders', 'sales/order/history/']], false],
        ];
    }

    /**
     * @return list<array{0: string, 1: list<array{0: string, 1: string}>, 2: bool}>
     */
    public static function linksVi(): array
    {
        return [
            ['Công ty', [['Giới thiệu', 'about-us'], ['Đơn hàng và trả hàng', 'sales/guest/form/'], ['Từ khóa tìm kiếm', 'search/term/popular/'], ['Liên hệ', 'contact']], true],
            ['Pháp lý', [['Quyền riêng tư', 'privacy-policy-cookie-restriction-mode'], ['Điều khoản và Điều kiện', 'terms-and-conditions']], false],
            ['Về chúng tôi', [['Về chúng tôi', 'about-us'], ['Blog', 'blog'], ['Hệ thống cửa hàng', 'our-stores'], ['Phát triển bền vững', 'sustainability']], false],
            ['Tài khoản', [['Tài khoản của tôi', 'customer/account/'], ['Đơn hàng của tôi', 'sales/order/history/']], false],
        ];
    }

    public static function social(string $heading): string
    {
        $icons = [
            ['social-facebook.png', 'Facebook'],
            ['social-instagram.png', 'Instagram'],
            ['social-pinterest.png', 'Pinterest'],
            ['social-twitter.png', 'Twitter'],
        ];
        $columns = '';
        foreach ($icons as [$file, $label]) {
            $columns .= '<div class="pagebuilder-column footer-social-item" data-content-type="column"'
                . ' data-appearance="full-height" data-background-images="{}" data-grid-size="1" data-element="main">'
                . '<figure data-content-type="image" data-appearance="full-width" data-element="main">'
                . '<img class="pagebuilder-image" src="{{media url="wysiwyg/footer/' . $file . '"}}"'
                . ' alt="' . $label . '" title="' . $label . '" loading="lazy" data-element="desktop_image">'
                . '</figure></div>';
        }

        return '<div data-content-type="row" data-appearance="full-width" data-background-images="{}"'
            . ' data-video-fallback-src="" data-element="main"><div class="row-full-width-inner" data-element="inner">'
            . '<h2 class="' . self::HEADING_BAND . '" data-content-type="heading" data-appearance="default"'
            . ' data-element="main">' . $heading . '</h2>'
            . '<div class="pagebuilder-column-group mt-6" data-background-images="{}" data-content-type="column-group"'
            . ' data-appearance="default" data-grid-size="12" data-background-lazy-load="" data-element="main">'
            . '<div class="pagebuilder-column-line" data-content-type="column-line" data-element="main">'
            . $columns
            . '</div></div></div></div>';
    }

    public static function trust(): string
    {
        $payments = [
            ['payment-visa.png', 'Visa'],
            ['payment-mastercard.png', 'Mastercard'],
            ['payment-momo.png', 'MoMo'],
            ['payment-vnpay.png', 'VNPAY'],
            ['payment-zalopay.png', 'ZaloPay'],
            ['payment-vietqr.png', 'VietQR'],
            ['payment-apple-pay.png', 'Apple Pay'],
            ['payment-google-pay.png', 'Google Pay'],
        ];
        $paymentColumns = '';
        foreach ($payments as [$file, $label]) {
            $paymentColumns .= '<div class="pagebuilder-column" data-content-type="column"'
                . ' data-appearance="full-height" data-background-images="{}" data-grid-size="1" data-element="main">'
                . '<figure data-content-type="image" data-appearance="full-width" data-element="main">'
                . '<img class="pagebuilder-image" src="{{media url="wysiwyg/footer/' . $file . '"}}"'
                . ' alt="' . $label . '" title="' . $label . '" loading="lazy" data-element="desktop_image">'
                . '</figure></div>';
        }

        return '<div data-content-type="row" data-appearance="full-width" data-background-images="{}"'
            . ' data-video-fallback-src="" data-element="main"><div class="row-full-width-inner" data-element="inner">'
            . '<div class="pagebuilder-column-group" data-background-images="{}" data-content-type="column-group"'
            . ' data-appearance="default" data-grid-size="12" data-background-lazy-load="" data-element="main">'
            . '<div class="pagebuilder-column-line" data-content-type="column-line" data-element="main">'
            . '<div class="pagebuilder-column footer-trust-badge" data-content-type="column"'
            . ' data-appearance="full-height" data-background-images="{}" data-grid-size="3" data-element="main">'
            . '<figure data-content-type="image" data-appearance="full-width" data-element="main">'
            . '<img class="pagebuilder-image" src="{{media url="wysiwyg/footer/bo-cong-thuong-badge.png"}}"'
            . ' width="169" height="64" alt="Đã thông báo Bộ Công Thương" title="Đã thông báo Bộ Công Thương"'
            . ' loading="lazy" data-element="desktop_image"></figure></div>'
            . '<div class="pagebuilder-column footer-trust-payments" data-content-type="column"'
            . ' data-appearance="full-height" data-background-images="{}" data-grid-size="9" data-element="main">'
            . '<div class="pagebuilder-column-group" data-background-images="{}" data-content-type="column-group"'
            . ' data-appearance="default" data-grid-size="12" data-background-lazy-load="" data-element="main">'
            . '<div class="pagebuilder-column-line" data-content-type="column-line" data-element="main">'
            . $paymentColumns
            . '</div></div></div>'
            . '</div></div></div></div>';
    }
}
