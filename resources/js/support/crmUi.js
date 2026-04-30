/**
 * Единые визуальные классы для CRM: кнопки «создать», «разрушающее», вторичная.
 */

export const crmBtnCreate =
    'inline-flex items-center gap-2 rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-900 shadow-sm transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-800/70 dark:bg-emerald-950/50 dark:text-emerald-50 dark:hover:bg-emerald-900/45';

export const crmBtnDangerMuted =
    'inline-flex items-center gap-2 rounded-xl border border-rose-200/90 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-900 shadow-sm transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-800/70 dark:bg-rose-950/45 dark:text-rose-50 dark:hover:bg-rose-900/40';

export const crmBtnNeutral =
    'inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800';

/** Вторичная ссылка рядом с «Создать» (например «Перейти в канбан») */
export const crmBtnSecondaryOutline =
    'inline-flex items-center justify-center rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800';
