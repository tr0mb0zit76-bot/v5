SET NOCOUNT ON;
SELECT o.name, COUNT(*) AS cols FROM sys.columns c JOIN sys.objects o ON o.object_id=c.object_id
WHERE o.name IN (N'Запрос_План', N'Разнарядка_Местоположение', N'Контейнер', N'ЗаявкаНаПеревозку', N'Рейс', N'Запрос_Клиента')
AND o.type='U' GROUP BY o.name;
SELECT o.name, c.column_id, c.name FROM sys.columns c JOIN sys.objects o ON o.object_id=c.object_id
WHERE o.name=N'Запрос_План' AND o.type='U' ORDER BY c.column_id;
SELECT TOP 15 o.name, c.column_id, c.name FROM sys.columns c JOIN sys.objects o ON o.object_id=c.object_id
WHERE o.name=N'Разнарядка_Местоположение' AND o.type='U' ORDER BY c.column_id;
SELECT TOP 10 name FROM sys.tables WHERE name LIKE N'%разнар%' OR name LIKE N'%Разнар%' ORDER BY name;
