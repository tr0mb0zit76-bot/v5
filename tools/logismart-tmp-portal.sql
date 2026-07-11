SET NOCOUNT ON;
SELECT ID, CAST(Наименование AS NVARCHAR(200)) AS n FROM [ОператорЭДО];
SELECT ID, CAST(Наименование AS NVARCHAR(200)) AS n FROM [ЭДО_Сервис];
SELECT TOP 8 ID, CAST(Name AS NVARCHAR(200)) AS n FROM portal_roles ORDER BY ID;
SELECT TOP 8 id, CAST(journal_name AS NVARCHAR(200)) AS jn FROM portal_journals_list;
SELECT TOP 5 id, CAST(grid_name AS NVARCHAR(200)) AS gn, CAST(column_name AS NVARCHAR(100)) AS col FROM portal_grid_layout ORDER BY id;
