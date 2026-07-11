SET NOCOUNT ON;
SELECT * FROM [ОператорЭДО];
SELECT * FROM [ЭДО_Сервис];
SELECT TOP 20 ID, Действие FROM [ЭДО_Действие];
SELECT TOP 15 ID, Наименование FROM [ЭДО_ТипДокумента];
SELECT TOP 10 ID, Статус FROM [Статус_ПредРасчета];
SELECT TOP 10 ID, Статус FROM [Статус_Расчет_ТП];
SELECT TOP 5 name FROM sys.views WHERE name LIKE N'%Предвар%' OR name LIKE N'%Расчет_ТП%';
