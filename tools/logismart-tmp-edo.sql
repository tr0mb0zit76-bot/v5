SET NOCOUNT ON;
SELECT name FROM sys.tables WHERE name LIKE N'%ЭДО%' ORDER BY name;
SELECT o.name, c.name, ty.name, c.max_length
FROM sys.objects o JOIN sys.columns c ON c.object_id=o.object_id JOIN sys.types ty ON c.user_type_id=ty.user_type_id
WHERE o.name IN (N'ЭДО', N'ОператорЭДО', N'ЭДО_Сервис', N'ЭДО_ТипДокумента', N'ЭДО_Действие', N'ЭДО_СтатусИмпорта')
AND o.type='U' ORDER BY o.name, c.column_id;
SELECT TOP 15 name, type_desc FROM sys.objects WHERE name LIKE N'view_portal%' ORDER BY name;
SELECT name FROM sys.tables WHERE name LIKE N'portal_%' ORDER BY name;
