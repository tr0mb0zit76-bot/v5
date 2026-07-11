SET NOCOUNT ON;

-- Core entity tables (pattern search)
SELECT t.name AS table_name, SUM(p.rows) AS row_count
FROM sys.tables t
JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
WHERE t.is_ms_shipped = 0
  AND (
    t.name LIKE N'%Заяв%'
    OR t.name LIKE N'%Груз%'
    OR t.name LIKE N'%Контраг%'
    OR t.name LIKE N'%Перев%'
    OR t.name LIKE N'%Рейс%'
    OR t.name LIKE N'%Разнар%'
    OR t.name LIKE N'%Марш%'
    OR t.name LIKE N'%Запрос%'
    OR t.name LIKE N'%ATI%'
    OR t.name LIKE N'%Ati%'
    OR t.name LIKE N'%Тариф%'
    OR t.name LIKE N'%Марж%'
    OR t.name LIKE N'%Заказ%'
    OR t.name LIKE N'%Плат%'
    OR t.name LIKE N'%Счет%'
    OR t.name LIKE N'%Договор%'
    OR t.name LIKE N'%КомПред%'
  )
GROUP BY t.name
ORDER BY row_count DESC, t.name;

PRINT '--- COLUMNS: Контрагент ---';
SELECT c.column_id, c.name, ty.name AS type_name, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types ty ON c.user_type_id = ty.user_type_id
WHERE c.object_id = OBJECT_ID(N'dbo.Контрагент')
ORDER BY c.column_id;

PRINT '--- COLUMNS: Рейс ---';
SELECT c.column_id, c.name, ty.name AS type_name, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types ty ON c.user_type_id = ty.user_type_id
WHERE c.object_id = OBJECT_ID(N'dbo.Рейс')
ORDER BY c.column_id;

PRINT '--- COLUMNS: Маршрут ---';
SELECT c.column_id, c.name, ty.name AS type_name, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types ty ON c.user_type_id = ty.user_type_id
WHERE c.object_id = OBJECT_ID(N'dbo.Маршрут')
ORDER BY c.column_id;

PRINT '--- FK sample for Контрагент ---';
SELECT OBJECT_NAME(fk.parent_object_id) AS parent_table,
       COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS parent_column,
       OBJECT_NAME(fk.referenced_object_id) AS ref_table,
       COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS ref_column
FROM sys.foreign_keys fk
JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.constraint_object_id
WHERE OBJECT_NAME(fk.parent_object_id) LIKE N'%Груз%'
   OR OBJECT_NAME(fk.referenced_object_id) LIKE N'%Контраг%'
   OR OBJECT_NAME(fk.parent_object_id) LIKE N'%Заяв%'
   OR OBJECT_NAME(fk.parent_object_id) LIKE N'%Рейс%'
ORDER BY parent_table, parent_column;

PRINT '--- Views related to cargo/orders ---';
SELECT v.name
FROM sys.views v
WHERE v.is_ms_shipped = 0
  AND (
    v.name LIKE N'%Груз%'
    OR v.name LIKE N'%Заяв%'
    OR v.name LIKE N'%Заказ%'
    OR v.name LIKE N'%Рейс%'
    OR v.name LIKE N'%Разнар%'
  )
ORDER BY v.name;
