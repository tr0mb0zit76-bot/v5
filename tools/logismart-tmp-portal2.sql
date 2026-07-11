SET NOCOUNT ON;
SELECT * FROM [ОператорЭДО];
SELECT * FROM [ЭДО_Сервис];
SELECT TOP 3 * FROM portal_roles;
SELECT TOP 3 * FROM portal_grid_layout;
SELECT TOP 3 * FROM portal_journals_list;
SELECT TOP 5 c.name FROM sys.columns c JOIN sys.objects o ON o.object_id=c.object_id WHERE o.name='portal_roles' ORDER BY c.column_id;
