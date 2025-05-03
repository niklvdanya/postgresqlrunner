define(['jquery', 'core/notification'], function($, Notification) {
    
    const init = function(inputId) {
        const textarea = $('#' + inputId);
        
        if (textarea.length === 0) {
            return;
        }
        
        // Добавляем обработчик табуляции для удобства ввода SQL
        textarea.on('keydown', function(e) {
            if (e.keyCode === 9) { // Tab key
                e.preventDefault();
                
                const start = this.selectionStart;
                const end = this.selectionEnd;
                
                // Установить новое значение текстового поля
                const value = $(this).val();
                $(this).val(value.substring(0, start) + '    ' + value.substring(end));
                
                // Поместить курсор в правильную позицию
                this.selectionStart = this.selectionEnd = start + 4;
            }
        });
        
        // Дополнительные функции можно добавить здесь,
        // например, поддержку синтаксической подсветки,
        // автозаполнение и другие возможности редактирования кода
    };
    
    return {
        init: init
    };
});