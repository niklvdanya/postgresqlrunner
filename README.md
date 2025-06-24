# PostgreSQL Runner — Moodle Question Type

**PostgreSQL Runner** — это тип вопроса для Moodle, позволяющий автоматически проверять SQL-запросы студентов, выполняя их в изолированной среде PostgreSQL и сравнивая результат с эталонным.

## Быстрый старт

### Установка
1. Скопируйте папку `postgresqlrunner` в директорию `question/type/` вашего Moodle.
2. Войдите в Moodle под администратором.
3. Перейдите в "Site administration" → "Notifications" и выполните обновление базы данных.

## Как пользоваться

### Создание вопроса
1. В разделе "Банк вопросов" выберите тип "PostgreSQL Runner".
2. Заполните поля:
   - **SQL Code** — эталонный ответ (например, INSERT/UPDATE/SELECT)
   - **Environment Init** — SQL для создания таблиц и наполнения данными
   - **Extra Code** — дополнительный SELECT для проверки результата (может быть пустым для обычных SELECT задач)
   - **Template** — шаблон кода для студентов (опционально)
   - **Grading Type** — тип сравнения (точное/частичное)
   - Дополнительные опции сравнения

### Пример задачи с изменением данных
- **Environment Init:**
  ```sql
  CREATE TABLE students (id SERIAL PRIMARY KEY, name VARCHAR(50), age INTEGER);
  ```
- **SQL Code:**
  ```sql
  INSERT INTO students (name, age) VALUES ('John', 20), ('Jane', 22)
  ```
- **Extra Code:**
  ```sql
  SELECT COUNT(*) as total_students, AVG(age) as average_age FROM students
  ```

### Пример обычной SELECT задачи
- **SQL Code:**
  ```sql
  SELECT department, AVG(salary) FROM employees GROUP BY department
  ```
- **Extra Code:** (оставьте пустым)

Больше примеров: см. [TESTING_EXAMPLES.md](TESTING_EXAMPLES.md)

## Принцип работы системы оценивания
1. Выполняется Environment Init (инициализация)
2. Выполняется код студента (или эталонный)
3. Выполняется Extra Code (если задан)
4. Сравнивается результат и/или состояние БД

## Документация 
- [docs/отчёт.pdf — подробное описание архитектуры](docs/Николаев-6-семестр-отчёт.pdf)