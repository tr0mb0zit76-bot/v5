SET NOCOUNT ON;

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
