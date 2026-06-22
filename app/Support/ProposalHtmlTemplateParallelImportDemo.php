<?php

namespace App\Support;

/**
 * Демо-шаблон КП по макету Unisender «Параллельный импорт» (без рассылки).
 */
class ProposalHtmlTemplateParallelImportDemo
{
    public const SLUG = 'parallel-import-demo';

    public const NAME = 'Параллельный импорт (демо Unisender)';

    public static function htmlBody(): string
    {
        return <<<'HTML'
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;margin:0 auto;background-color:#ffffff;border-collapse:collapse;">
  <tbody>
    <tr>
      <td style="padding:28px 32px 12px;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
          <tbody>
            <tr>
              <td width="52%" valign="top" style="font-family:Arial,Helvetica,sans-serif;">
                <div style="font-size:18px;font-weight:700;color:#111827;line-height:1.3;">{responsible.name}</div>
                <div style="margin-top:10px;font-size:14px;color:#4b5563;line-height:1.5;">{responsible.phone}</div>
                <div style="margin-top:4px;font-size:14px;color:#7c3aed;line-height:1.5;">{responsible.email}</div>
              </td>
              <td width="48%" valign="top" align="right" style="font-family:Arial,Helvetica,sans-serif;">
                <div style="font-size:20px;font-weight:800;color:#5b21b6;line-height:1.2;letter-spacing:0.04em;text-transform:uppercase;">{own_company.name}</div>
                <div style="margin-top:6px;font-size:11px;color:#6b7280;letter-spacing:0.18em;text-transform:uppercase;">Логистические решения</div>
              </td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:8px 32px 4px;font-family:Arial,Helvetica,sans-serif;">
        <div style="font-size:30px;font-weight:700;color:#111827;line-height:1.2;">Параллельный импорт</div>
        <div style="margin-top:8px;font-size:14px;color:#6b7280;line-height:1.5;">Международные и внутрироссийские перевозки</div>
      </td>
    </tr>
    <tr>
      <td style="padding:20px 32px 8px;font-family:Arial,Helvetica,sans-serif;">
        <p style="margin:0;font-size:16px;line-height:1.65;color:#111827;">Добрый день, <strong>{counterparty.contact_person}</strong>!</p>
      </td>
    </tr>
    <tr>
      <td style="padding:8px 32px 20px;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
          <tbody>
            <tr>
              <td width="58%" valign="top" style="padding-right:16px;font-family:Arial,Helvetica,sans-serif;">
                <p style="margin:0 0 12px;font-size:15px;line-height:1.65;color:#374151;">Наша компания специализируется на организации международных и внутрироссийских перевозок, в том числе в формате параллельного импорта и поставок «под ключ».</p>
                <p style="margin:0;font-size:15px;line-height:1.65;color:#374151;">Работаем с категориями грузов, где важны сроки, документальное сопровождение и прозрачная логистика — от забора до выгрузки.</p>
              </td>
              <td width="42%" valign="top" style="font-family:Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate;border-spacing:0 10px;">
                  <tbody>
                    <tr>
                      <td style="background-color:#ede9fe;border-radius:12px;padding:14px 16px;font-size:13px;color:#5b21b6;font-weight:600;">🌐 РФ и международные направления</td>
                    </tr>
                    <tr>
                      <td style="background-color:#ede9fe;border-radius:12px;padding:14px 16px;font-size:13px;color:#5b21b6;font-weight:600;">💰 Оптимизация затрат на логистику</td>
                    </tr>
                    <tr>
                      <td style="background-color:#ede9fe;border-radius:12px;padding:14px 16px;font-size:13px;color:#5b21b6;font-weight:600;">⚠ Сложные и чувствительные категории грузов</td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:4px 32px 24px;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;background-color:#faf5ff;border-radius:16px;">
          <tbody>
            <tr>
              <td style="padding:22px 24px;font-family:Arial,Helvetica,sans-serif;">
                <div style="font-size:20px;font-weight:700;color:#4c1d95;margin-bottom:14px;">Почему выбирают нас?</div>
                <ul style="margin:0;padding-left:20px;color:#374151;font-size:15px;line-height:1.7;">
                  <li style="margin-bottom:8px;">Оптимизация логистических затрат без потери надёжности цепочки поставки</li>
                  <li style="margin-bottom:8px;">Честные сроки и понятная коммуникация на каждом этапе перевозки</li>
                  <li style="margin-bottom:0;">Опыт работы с товарами под экспортными ограничениями, в том числе из Китая</li>
                </ul>
              </td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:0 32px 28px;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:12px;">
          <tbody>
            <tr>
              <td style="padding:18px 20px;font-family:Arial,Helvetica,sans-serif;">
                <div style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;">Параметры перевозки</div>
                <p style="margin:0 0 8px;font-size:15px;color:#111827;"><strong>Маршрут:</strong> {route.loading_first_city} → {route.unloading_last_city}</p>
                <p style="margin:0 0 8px;font-size:15px;color:#111827;"><strong>Груз:</strong> {cargo.summary}</p>
                <p style="margin:0;font-size:17px;color:#5b21b6;"><strong>Ставка:</strong> {offer.price} {offer.currency}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:0 32px 32px;font-family:Arial,Helvetica,sans-serif;">
        <p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">Готовы обсудить детали и подготовить расчёт под вашу задачу. Свяжитесь со мной удобным способом — отвечу в рабочее время.</p>
        <p style="margin:16px 0 0;font-size:14px;color:#111827;"><strong>{responsible.name}</strong><br>{responsible.phone}<br>{responsible.email}</p>
      </td>
    </tr>
  </tbody>
</table>
HTML;
    }

    public static function cssInline(): string
    {
        return <<<'CSS'
body {
  margin: 0;
  padding: 24px 12px;
  background-color: #f4f4f5;
  font-family: Arial, Helvetica, sans-serif;
  color: #111827;
}
table {
  border-collapse: collapse;
}
CSS;
    }
}
