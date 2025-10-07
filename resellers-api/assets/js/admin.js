(function($){
    'use strict';

    $(function(){
        var $button = $('#resellers-api-test-connection');
        if ( !$button.length ) {
            return;
        }

        var $result = $('.resellers-api-test-result');

        $button.on('click', function(event){
            event.preventDefault();

            if ( typeof resellersApiAdmin === 'undefined' ) {
                return;
            }

            $button.prop('disabled', true);
            $result.removeClass('error success').text(resellersApiAdmin.testing);

            $.post(
                resellersApiAdmin.ajaxUrl,
                {
                    action: 'resellers_api_test_connection',
                    nonce: resellersApiAdmin.nonce
                }
            ).done(function(response){
                if ( response && response.success ) {
                    $result.addClass('success').text(response.data);
                } else {
                    var message = response && response.data ? response.data : resellersApiAdmin.error;
                    $result.addClass('error').text(message);
                }
            }).fail(function(){
                $result.addClass('error').text(resellersApiAdmin.error);
            }).always(function(){
                $button.prop('disabled', false);
            });
        });
    });
})(jQuery);
