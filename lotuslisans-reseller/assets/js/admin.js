(function($){
    'use strict';

    $(function(){
        var $button = $('#lotuslisans-test-connection');
        if ( !$button.length ) {
            return;
        }

        var $result = $('.lotuslisans-test-result');

        $button.on('click', function(event){
            event.preventDefault();

            if ( typeof lotuslisansReseller === 'undefined' ) {
                return;
            }

            $button.prop('disabled', true);
            $result.removeClass('error success').text(lotuslisansReseller.testing);

            $.post(
                lotuslisansReseller.ajaxUrl,
                {
                    action: 'lotuslisans_test_connection',
                    nonce: lotuslisansReseller.nonce
                }
            ).done(function(response){
                if ( response && response.success ) {
                    $result.addClass('success').text(response.data);
                } else {
                    var message = response && response.data ? response.data : lotuslisansReseller.error;
                    $result.addClass('error').text(message);
                }
            }).fail(function(){
                $result.addClass('error').text(lotuslisansReseller.error);
            }).always(function(){
                $button.prop('disabled', false);
            });
        });
    });
})(jQuery);
