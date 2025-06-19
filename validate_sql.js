(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('validate-sql');
        const sqlArea = document.querySelector('textarea[name="sqlcode"]');
        const questionBankArea = document.querySelector('textarea[name="question_bank"]');
        const useQuestionBankCheckbox = document.getElementById('id_use_question_bank');
        const envArea = document.querySelector('textarea[name="environment_init"]');
        const extraArea = document.querySelector('textarea[name="extra_code"]');
        const box = document.getElementById('validate-sql-msg');

        if (!btn || (!sqlArea && !questionBankArea) || !box) { return; }

        btn.addEventListener('click', function () {
            box.textContent = '';
            box.className = '';

            let params = {
                environment_init: envArea ? envArea.value : '',
                extra_code: extraArea ? extraArea.value : '',
                sesskey: M.cfg.sesskey
            };

            if (useQuestionBankCheckbox.checked) {
                params.question_bank = questionBankArea.value;
                params.use_question_bank = 1;
            } else {
                params.sql = sqlArea.value;
                params.use_question_bank = 0;
            }

            fetch(M.cfg.wwwroot + '/question/type/postgresqlrunner/validate_sql.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(params)
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    box.textContent = M.util.get_string('validatesqlok', 'qtype_postgresqlrunner');
                    box.className = 'alert alert-success';
                } else {
                    const msg = M.util.get_string('validatesqlfail', 'qtype_postgresqlrunner')
                        .replace('{$a}', data.message);
                    box.textContent = msg;
                    box.className = 'alert alert-danger';
                }
            })
            .catch(() => {
                box.textContent = 'AJAX error';
                box.className = 'alert alert-warning';
            });
        });
    });
})();