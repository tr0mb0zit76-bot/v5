SET NOCOUNT ON;

DECLARE @tables TABLE (name sysname);
INSERT INTO @tables(name) VALUES
(N'Контрагент'), (N'Рейс'), (N'Маршрут'), (N'Заявка'), (N'Груз'),
(N'ЗаявкаКлиентская'), (N'ЗаявкаПеревозчику'), (N'Разнарядка'),
(N'Запрос'), (N'КоммерческоеПредложение'), (N'Заказ'), (N'ЗаказКлиента');

SELECT t.name AS table_name,
       CASE WHEN o.object_id IS NULL THEN 0 ELSE 1 END AS exists_flag,
       ISNULL(p.row_count, 0) AS row_count
FROM @tables t
LEFT JOIN sys.objects o ON o.name = t.name AND o.type = 'U'
OUTER APPLY (
    SELECT SUM(rows) AS row_count
    FROM sys.partitions p
    WHERE p.object_id = o.object_id AND p.index_id IN (0, 1)
) p
ORDER BY exists_flag DESC, t.name;

SELECT t.name AS table_name, c.column_id, c.name AS column_name, ty.name AS type_name, c.max_length, c.is_nullable
FROM @tables t
JOIN sys.objects o ON o.name = t.name AND o.type = 'U'
JOIN sys.columns c ON c.object_id = o.object_id
JOIN sys.types ty ON c.user_type_id = ty.user_type_id
ORDER BY t.name, c.column_id;
