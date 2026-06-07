import Link from '@tiptap/extension-link';

/**
 * Ссылки в Книге продаж. Не передаём `protocols`: TipTap регистрирует их в linkify
 * на каждом onCreate редактора, из‑за чего при втором экземпляре (чтение ↔ правка)
 * появляется warning «linkifyjs: already initialized… mailto».
 * mailto, http, https и др. уже разрешены в isAllowedUri по умолчанию.
 */
export const SalesBookLink = Link.configure({
    openOnClick: false,
    autolink: true,
});
