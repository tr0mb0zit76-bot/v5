SET NOCOUNT ON;

DECLARE @tables TABLE (name sysname);
INSERT INTO @tables(name) VALUES
(N'Предварительный_расчет'), (N'Предварительный_расчет_Товары'), (N'Предварительный_расчет_Услуги'),
(N'Расчет_ТП'), (N'Расчет_ТП_Строка'), (N'Декларация'), (N'ГТД_Груз'),
(N'Контейнер_Расчет'), (N'Контейнер_Расчет_Суммы');

SELECT t.name, CASE WHEN o.object_id IS NULL THEN 0 ELSE 1 END AS exists_flag
FROM @tables t
LEFT JOIN sys.objects o ON o.name = t.name AND o.type = 'U';

SELECT o.name AS table_name, c.column_id, c.name AS column_name, ty.name AS type_name
FROM @tables t
JOIN sys.objects o ON o.name = t.name AND o.type = 'U'
JOIN sys.columns c ON c.object_id = o.object_id
JOIN sys.types ty ON c.user_type_id = ty.user_type_id
ORDER BY o.name, c.column_id;

SELECT name FROM sys.tables WHERE name LIKE N'%ЭДО%' OR name LIKE N'%EDO%' ORDER BY name;

SELECT TOP 30 ROUTINE_NAME
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_TYPE = 'PROCEDURE'
  AND (ROUTINE_NAME LIKE N'%PreCalc%' OR ROUTINE_NAME LIKE N'%PreCalculation%' OR ROUTINE_NAME LIKE N'%Customs%' OR ROUTINE_NAME LIKE N'%GTD%' OR ROUTINE_NAME LIKE N'%TP%' OR ROUTINE_NAME LIKE N'%ЭДО%' OR ROUTINE_NAME LIKE N'%EDO%')
ORDER BY ROUTINE_NAME;
