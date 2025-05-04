define(['jquery'], function($) {
    
    const secureConnection = function() {
        const connectionField = $('#id_db_connection');
        
        if (connectionField.length === 0) {
            return;
        }
        
        $('form').on('submit', function() {
        });
    };
    
    return {
        init: function() {
            secureConnection();
        }
    };
});