(function () {
    document.addEventListener('DOMContentLoaded', function () {

        const btn  = document.getElementById('validate-sql');
        const area = document.querySelector('textarea[name="sqlcode"]');
        const envArea = document.querySelector('textarea[name="environment_init"]');
        const box  = document.getElementById('validate-sql-msg');

        if (!btn || !area || !box) { return; }

        btn.addEventListener('click', function () {

            box.textContent = '';          
            box.className   = '';          

            fetch(M.cfg.wwwroot + '/question/type/postgresqlrunner/validate_sql.php', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    sql:             area.value,
                    environment_init: envArea ? envArea.value : '',
                    sesskey:         M.cfg.sesskey
                })
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    box.textContent = M.util.get_string('validatesqlok',
                                     'qtype_postgresqlrunner');
                    box.className   = 'alert alert-success';
                } else {
                    const msg = M.util.get_string('validatesqlfail',
                                  'qtype_postgresqlrunner')
                                  .replace('{$a}', data.message);

                    box.textContent = msg;
                    box.className   = 'alert alert-danger';
                }
            })
            .catch(() => {
                box.textContent = 'AJAX error';
                box.className   = 'alert alert-warning';
            });
        });
    });
})();

