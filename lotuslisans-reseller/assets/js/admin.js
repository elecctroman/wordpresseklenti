(function($){
    'use strict';

    $(function(){
        var $buttons = $('.lotuslisans-test-connection');

        if ( !$buttons.length || typeof lotuslisansReseller === 'undefined' ) {
            return;
        }

        $buttons.on('click', function(event){
            event.preventDefault();

            var $button  = $(this);
            var provider = $button.data('provider') || 'lotus';
            var $wrapper = $button.closest('.lotuslisans-test-wrapper');
            var $result  = $wrapper.find('.lotuslisans-test-result');

            if ( !$result.length ) {
                $result = $('<span/>', {
                    'class': 'lotuslisans-test-result',
                    'aria-live': 'polite'
                }).appendTo($wrapper);
            }

            $button.prop('disabled', true);
            $result.removeClass('error success').text(lotuslisansReseller.testing);

            $.post(
                lotuslisansReseller.ajaxUrl,
                {
                    action: 'lotuslisans_test_provider',
                    nonce: lotuslisansReseller.nonce,
                    provider: provider
                }
            ).done(function(response){
                var message;

                if ( response && response.success ) {
                    message = response.data && response.data.message ? response.data.message : lotuslisansReseller.success;
                    $result.addClass('success').text(message);
                } else {
                    message = response && response.data ? response.data : lotuslisansReseller.error;
                    $result.addClass('error').text(message);
                }
            }).fail(function(xhr){
                var message = lotuslisansReseller.error;

                if ( xhr && xhr.responseJSON && xhr.responseJSON.data ) {
                    message = xhr.responseJSON.data;
                }

                $result.addClass('error').text(message);
            }).always(function(){
                $button.prop('disabled', false);
            });
        });
    });
})(jQuery);
