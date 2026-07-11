SELECT IFNULL(p.title, ''), a.title
FROM sales_book_articles a
LEFT JOIN sales_book_articles p ON p.id = a.parent_id
ORDER BY a.id;
