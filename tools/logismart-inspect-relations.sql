SET NOCOUNT ON;

SELECT OBJECT_NAME(fk.parent_object_id) AS parent_table,
       COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS parent_column,
       OBJECT_NAME(fk.referenced_object_id) AS ref_table,
       COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS ref_column
FROM sys.foreign_keys fk
JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
WHERE OBJECT_NAME(fk.parent_object_id) LIKE N'%Груз%'
   OR OBJECT_NAME(fk.referenced_object_id) LIKE N'%Контраг%'
   OR OBJECT_NAME(fk.parent_object_id) LIKE N'%Заяв%'
   OR OBJECT_NAME(fk.parent_object_id) LIKE N'%Рейс%'
   OR OBJECT_NAME(fk.parent_object_id) LIKE N'%Разнар%'
   OR OBJECT_NAME(fk.parent_object_id) LIKE N'%Марш%'
ORDER BY parent_table, parent_column;

SELECT v.name AS view_name
FROM sys.views v
WHERE v.is_ms_shipped = 0
  AND (
    v.name LIKE N'%Груз%'
    OR v.name LIKE N'%Заяв%'
    OR v.name LIKE N'%Заказ%'
    OR v.name LIKE N'%Рейс%'
    OR v.name LIKE N'%Разнар%'
    OR v.name LIKE N'%ATI%'
  )
ORDER BY v.name;

SELECT ROUTINE_NAME
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_TYPE = 'PROCEDURE'
  AND (
    ROUTINE_NAME LIKE N'%Груз%'
    OR ROUTINE_NAME LIKE N'%Заяв%'
    OR ROUTINE_NAME LIKE N'%ATI%'
    OR ROUTINE_NAME LIKE N'%Разнар%'
    OR ROUTINE_NAME LIKE N'%Рейс%'
  )
ORDER BY ROUTINE_NAME;
