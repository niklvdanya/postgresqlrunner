define(['jquery'], function($) {
    
    const secureConnection = function() {
        const connectionField = $('#id_db_connection');
        
        if (connectionField.length === 0) {
            return;
        }
        
        connectionField.on('change', function() {
            try {
                const connData = JSON.parse($(this).val());
                if (connData && connData.password) {
                    $(this).data('secure-password', connData.password);
                    connData.password = '********';
                    $(this).val(JSON.stringify(connData, null, 2));
                }
            } catch (e) {
                // Invalid JSON, do nothing
            }
        });
        
        connectionField.on('blur', function() {
            try {
                const connData = JSON.parse($(this).val());
                if (connData && connData.password && connData.password !== '********') {
                    $(this).data('secure-password', connData.password);
                    connData.password = '********';
                    $(this).val(JSON.stringify(connData, null, 2));
                }
            } catch (e) {
                // Invalid JSON, do nothing
            }
        });
        
        connectionField.on('focus', function() {
            try {
                const connData = JSON.parse($(this).val());
                if (connData && connData.password === '********' && $(this).data('secure-password')) {
                    connData.password = $(this).data('secure-password');
                    $(this).val(JSON.stringify(connData, null, 2));
                }
            } catch (e) {
                // Invalid JSON, do nothing
            }
        });
        
        connectionField.on('submit', function() {
            try {
                const connData = JSON.parse($(this).val());
                if (connData && connData.password === '********' && $(this).data('secure-password')) {
                    connData.password = $(this).data('secure-password');
                    $(this).val(JSON.stringify(connData, null, 2));
                }
            } catch (e) {
                // Invalid JSON, do nothing
            }
        });
        
        connectionField.trigger('change');
    };
    
    return {
        init: function() {
            secureConnection();
        }
    };
});