define(['jquery'], function($) {
    
    const secureConnection = function() {
        const connectionField = $('#id_db_connection');
        
        if (connectionField.length === 0) {
            return;
        }
        
        const handleConnectionData = function(connField) {
            try {
                const connData = JSON.parse(connField.val());
                
                if (!connData) {
                    return;
                }
                const sessionId = 'conn_' + Math.random().toString(36).substring(2, 15);
                
                if (connData && connData.password && connData.password !== '********') {
                    $.ajax({
                        url: M.cfg.wwwroot + '/question/type/postgresqlrunner/ajax/store_session_data.php',
                        method: 'POST',
                        data: {
                            sesskey: M.cfg.sesskey,
                            session_id: sessionId,

                            conn_password: connData.password,
                            action: 'store'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response && response.success) {
                                connField.attr('data-session-id', sessionId);

                                connData.password = '********';
                                connField.val(JSON.stringify(connData, null, 2));
                            }
                        }
                    });
                }
            } catch (e) {
              
            }
        };
        
        const restoreConnectionData = function() {
            try {
                const connField = $('#id_db_connection');
                const sessionId = connField.attr('data-session-id');
                
                if (!sessionId) {
                    return;
                }
                
                $.ajax({
                    url: M.cfg.wwwroot + '/question/type/postgresqlrunner/ajax/store_session_data.php',
                    method: 'POST',
                    data: {
                        sesskey: M.cfg.sesskey,
                        session_id: sessionId,
                        action: 'retrieve'
                    },
                    dataType: 'json',
                    async: false, 
                    success: function(response) {
                        if (response && response.success && response.password) {
                            const connData = JSON.parse(connField.val());
                            if (connData && connData.password === '********') {
                                connData.password = response.password;
                                connField.val(JSON.stringify(connData, null, 2));
                            }
                        }
                    },
                    complete: function() {
                        $.ajax({
                            url: M.cfg.wwwroot + '/question/type/postgresqlrunner/ajax/store_session_data.php',
                            method: 'POST',
                            data: {
                                sesskey: M.cfg.sesskey,
                                session_id: sessionId,
                                action: 'delete'
                            },
                            async: false
                        });
                    }
                });
                
            } catch (e) {
                
            }
        };
        
        connectionField.on('change', function() {
            handleConnectionData($(this));
        });
        
        connectionField.on('blur', function() {
            handleConnectionData($(this));
        });

        $('form').on('submit', function() {
            restoreConnectionData();
        });

        handleConnectionData(connectionField);
    };
    
    return {
        init: function() {
            secureConnection();
        }
    };
});