<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'PostgreSQL Runner';
$string['pluginname_help'] = 'Тип вопроса, позволяющий выполнять запросы PostgreSQL и сравнивать их результаты с ожидаемыми значениями.';
$string['pluginname_link'] = 'question/type/postgresqlrunner';
$string['pluginnameadding'] = 'Добавление вопроса PostgreSQL Runner';
$string['pluginnameediting'] = 'Редактирование вопроса PostgreSQL Runner';
$string['pluginnamesummary'] = 'Тип вопроса, который позволяет студентам писать запросы PostgreSQL и проверяет их, выполняя на базе данных.';

$string['sqlheader'] = 'Настройки SQL';
$string['sqlcode'] = 'SQL-код';
$string['sqlcode_help'] = 'Введите образец SQL-кода, который обеспечивает правильный ответ на вопрос.';
$string['validatesql'] = 'Проверить SQL';
$string['validatesqlok'] = 'SQL-код синтаксически корректен ✔️';
$string['validatesqlfail'] = 'Ошибка: {$a}';

$string['additionalinfo'] = 'Дополнительная информация';
$string['additionalinfo_help'] = 'Необязательное поле для дополнительной информации или пояснений о запросе.';
$string['template'] = 'Шаблон кода';
$string['template_help'] = 'Введите шаблон кода, который будет предоставлен студентам в качестве начальной точки для их ответа.';
$string['environmentinit'] = 'Инициализация окружения';
$string['environmentinit_help'] = 'Введите SQL-код для инициализации окружения (создание таблиц, вставка данных и т.д.). Этот код будет выполнен перед выполнением запросов студентов.';
$string['extracode'] = 'Дополнительный код';
$string['extracode_help'] = 'Введите необязательный SELECT-запрос, который будет выполнен после основного кода для целей оценивания. Оставьте пустым, если основной код является SELECT-запросом.';
$string['extracodemustselect'] = 'Дополнительный код должен быть SELECT-запросом.';
$string['gradingtype'] = 'Тип оценивания';
$string['gradingtype_help'] = 'Выберите, как будет оцениваться ответ студента. "Точное совпадение" требует полного соответствия результата (включая имена полей). "Частичное совпадение" допускает разные имена полей, но данные должны совпадать.';
$string['gradingtypeexact'] = 'Точное совпадение';
$string['gradingtypepartial'] = 'Частичное совпадение (игнорировать имена полей)';
$string['casesensitive'] = 'Чувствительность к регистру';
$string['casesensitive_help'] = 'Если включено, сравнение ожидаемых и фактических результатов будет учитывать регистр.';
$string['alloworderingdifference'] = 'Порядок результатов';
$string['alloworderingdifference_help'] = 'Если включено, порядок строк в результате будет игнорироваться при сравнении.';

$string['pleaseanswer'] = 'Пожалуйста, введите ваш SQL-запрос.';
$string['noresults'] = 'Нет результатов';
$string['correctresult'] = 'Правильно! Ваш запрос даёт ожидаемый результат.';
$string['incorrectresult'] = 'Неправильный результат. Ваш запрос не дал ожидаемого результата.';
$string['yourresult'] = 'Ваш результат:';
$string['expectedresult_display'] = 'Ожидаемый результат:';
$string['correctanswer'] = 'Правильный ответ: {$a}';
$string['invalidjson'] = 'Недопустимый формат JSON';

$string['queryerror'] = 'Ошибка запроса';
$string['yourtablestate'] = 'Состояние таблицы после вашего запроса';
$string['expectedtablestate'] = 'Ожидаемое состояние таблицы';
$string['tablestructure'] = 'Структура таблицы';
$string['tabledata'] = 'Данные таблицы';
$string['columnname'] = 'Имя столбца';
$string['datatype'] = 'Тип данных';
$string['nullable'] = 'Допускает NULL';
$string['defaultvalue'] = 'Значение по умолчанию';
$string['nodatainside'] = 'Таблица не содержит данных';
$string['forbidden_command'] = 'Обнаружена запрещённая SQL-команда';
$string['system_table_access'] = 'Доступ к системным таблицам запрещён';