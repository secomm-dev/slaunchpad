define([
    'jquery'
], function ($) {
    'use strict';

    if ($('#shipping-delivery-status').length) {
        let status = $('#shipping-delivery-status').detach();
        $('.order-shipping-method').append(status);
    }
    if ($('#shipping-delivery-status-sub').length) {
        let status = $('#shipping-delivery-status-sub').detach();
        $('.order-shipping-method').append(status);
    }
    if ($('#shipping-delivery-cancel-comment').length) {
        let comment = $('#shipping-delivery-cancel-comment').detach();
        $('.order-shipping-method').append(comment);
    }
    if ($('#shipping-delivery-share-link').length) {
        let shareLink = $('#shipping-delivery-share-link').detach();
        $('.order-shipping-method').append(shareLink);
    }
});
