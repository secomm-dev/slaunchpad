define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, alert, $t) {
    'use strict';

    return function (config, element) {
        $(element).on('click', function () {
            var $button = $(this);
            var $container = $button.closest('.mst-core__geolocation-field');

            $('body').trigger('processStart');
            $button.prop('disabled', true);

            $.ajax({
                url: config.url,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY
                }
            }).done(function (response) {
                if (response.success && response.info) {
                    // Update status message to success state
                    var $message = $container.find('.message');

                    $message.removeClass('message-warning message-notice')
                        .addClass('message-success')
                        .html('<strong>' + $t('Database status:') + '</strong> ' + $t('Available (auto-update enabled)'));

                    // Update or create info block
                    var $info = $container.find('.mst-core__geolocation-info');

                    if ($info.length === 0) {
                        $info = $('<div class="mst-core__geolocation-info"></div>');
                        $message.after($info);
                    }

                    var html = '';

                    if (response.info.version) {
                        html += '<p><strong>' + $t('Version:') + '</strong> ' + response.info.version + '</p>';
                    }

                    html += '<p><strong>' + $t('Size:') + '</strong> ' + response.info.size + '</p>';

                    $info.html(html);

                    // Remove hint text (no longer needed when auto-update is enabled)
                    $container.find('.mst-core__geolocation-hint').remove();

                    // Update button to non-primary state
                    $button.removeClass('primary').find('span').text($t('Update database'));

                    alert({
                        title: $t('Success'),
                        content: response.message || $t('Database updated successfully.')
                    });
                } else {
                    alert({
                        title: $t('Notice'),
                        content: response.message || $t('Database is up to date.')
                    });
                }
            }).fail(function () {
                alert({
                    title: $t('Error'),
                    content: $t('An error occurred while downloading the database.')
                });
            }).always(function () {
                $('body').trigger('processStop');
                $button.prop('disabled', false);
            });
        });
    };
});
