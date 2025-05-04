define(['jquery', 'core/notification'], function($, Notification) {
    
    const init = function(inputId) {
        const textarea = $('#' + inputId);
        
        if (textarea.length === 0) {
            return;
        }
        
        textarea.on('keydown', function(e) {
            if (e.keyCode === 9) {
                e.preventDefault();
                
                const start = this.selectionStart;
                const end = this.selectionEnd;
                
                const value = $(this).val();
                $(this).val(value.substring(0, start) + '    ' + value.substring(end));
                
                this.selectionStart = this.selectionEnd = start + 4;
            }
        });
    };
    
    return {
        init: init
    };
});