# Примеры тестирования PostgreSQL Runner

## Пример 1: INSERT + проверка через SELECT

**Environment Init:**
```sql
CREATE TABLE students (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50),
    age INTEGER
);
```

**SQL Code (эталонный ответ):**
```sql
INSERT INTO students (name, age) VALUES ('John', 20), ('Jane', 22)
```

**Extra Code:**
```sql
SELECT * FROM students
```

**Пояснение:**
- Студент пишет INSERT, а система проверяет результат через SELECT.

---

## Пример 2: UPDATE + проверка результата

**Environment Init:**
```sql
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50),
    price DECIMAL(10,2)
);
INSERT INTO products (name, price) VALUES 
('Laptop', 1000.00),
('Mouse', 25.00),
('Keyboard', 75.00);
```

**SQL Code:**
```sql
UPDATE products SET price = price * 1.1 WHERE price > 50
```

**Extra Code:**
```sql
SELECT * FROM products
```

---

## Пример 3: Обычный SELECT (без Extra Code)

**Environment Init:**
```sql
CREATE TABLE employees (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50),
    department VARCHAR(50),
    salary INTEGER
);
INSERT INTO employees (name, department, salary) VALUES 
('Alice', 'IT', 75000),
('Bob', 'HR', 55000),
('Charlie', 'IT', 80000);
```

**SQL Code:**
```sql
SELECT department, AVG(salary) as avg_salary 
FROM employees 
GROUP BY department 
ORDER BY department
```

**Extra Code:** (оставьте пустым)

**Пояснение:**
- Для обычных SELECT задач поле Extra Code не требуется.

---

## Пример 4: DELETE + проверка состояния

**Environment Init:**
```sql
CREATE TABLE items (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50)
);
INSERT INTO items (name) VALUES ('A'), ('B'), ('C');
```

**SQL Code:**
```sql
DELETE FROM items WHERE name = 'B';
```

**Extra Code:**
```sql
SELECT * FROM items ORDER BY id;
```

---

## Примечания
- Для задач с изменением данных всегда используйте Extra Code для проверки результата.
- Для обычных SELECT задач Extra Code можно не заполнять.
