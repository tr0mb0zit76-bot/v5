<?php

namespace App\Support;

class ProposalHtmlTemplateColdEmailLibrary
{
    private const ASSET_BASE = '/assets/proposal-emails';

    /**
     * @return list<array{slug: string, name: string, subject: string, preheader: string, html_body: string, css_inline: string}>
     */
    public static function templates(): array
    {
        return [
            self::template(
                slug: ProposalHtmlTemplateParallelImportDemo::SLUG,
                name: 'Параллельный импорт — холодное письмо',
                subject: 'Логистика для параллельного импорта',
                preheader: 'Если поставки идут через сложные маршруты, можем подсказать более устойчивую схему.',
                title: 'Параллельный импорт без лишних перегрузок',
                intro: 'Пишу коротко: мы в «Автоальянс-Смоленск» помогаем выстраивать поставки, где важны сроки, документы и понятная схема движения груза.',
                angle: 'Чаще всего подключаемся, когда маршрут уже есть, но хочется проверить стоимость, сроки или риски на границе.',
                points: [
                    'подбираем маршрут под товар, ограничения и нужный срок;',
                    'сравниваем автомобильные, мультимодальные и складские варианты;',
                    'держим фокус на прозрачной коммуникации по статусу груза.',
                ],
                cta: 'Если сейчас есть поставка или планируемый маршрут, пришлите направление и параметры груза. Посмотрим, где можно выиграть по сроку или стоимости.',
                asset: 'route.svg',
            ),
            self::template(
                slug: 'aa-export-china-cold',
                name: 'Экспорт в Китай — холодное письмо',
                subject: 'Перевозки в Китай: маршрут и документы',
                preheader: 'Можем проверить схему доставки до границы, порта или склада получателя.',
                title: 'Экспорт в Китай с понятной логистикой',
                intro: 'Если вы отправляете груз в Китай или только считаете экспортный проект, можем помочь с маршрутом, перевозчиком и документальной частью.',
                angle: 'Такие перевозки часто упираются не только в ставку, но и в предсказуемость прохождения этапов: забор, граница, порт, склад получателя.',
                points: [
                    'просчитываем автомобильные, железнодорожные и мультимодальные схемы;',
                    'учитываем требования к документам и контрольным точкам маршрута;',
                    'помогаем выбрать вариант без лишних перегрузок, если это критично.',
                ],
                cta: 'Если актуально, отправьте направление, тип груза и ориентировочную дату. Вернёмся с первичной оценкой по схеме перевозки.',
                asset: 'customs.svg',
            ),
            self::template(
                slug: 'aa-chemical-logistics-cold',
                name: 'Химия и специальные грузы — холодное письмо',
                subject: 'Перевозка химической продукции',
                preheader: 'Аккуратно считаем маршруты для грузов, где важны требования к безопасности и документам.',
                title: 'Химические грузы: безопасный маршрут и контроль',
                intro: 'Вижу, что для химической продукции логистика редко бывает простой: требования к транспорту, упаковке, документам и срокам должны совпасть.',
                angle: 'Мы помогаем выстроить перевозку так, чтобы заранее понимать ограничения и не решать критичные вопросы уже в пути.',
                points: [
                    'подбираем транспорт и маршрут под специфику продукции;',
                    'проверяем, какие документы и условия перевозки нужно учесть;',
                    'держим связь по статусу и контрольным точкам движения.',
                ],
                cta: 'Если есть груз для расчёта, достаточно описать продукцию, маршрут и объём. Подскажем, какой вариант перевозки выглядит рабочим.',
                asset: 'chemical.svg',
            ),
            self::template(
                slug: 'aa-heavy-equipment-cold',
                name: 'Спецтехника и негабарит — холодное письмо',
                subject: 'Перевозка спецтехники и негабарита',
                preheader: 'Поможем оценить маршрут, транспорт и разрешения до запуска перевозки.',
                title: 'Спецтехника без сюрпризов на маршруте',
                intro: 'Перевозка техники и негабарита обычно требует больше подготовки, чем стандартная ставка: маршрут, крепление, разрешения и контроль сроков.',
                angle: 'Мы берём на себя расчёт схемы и заранее подсвечиваем места, где могут появиться задержки или дополнительные расходы.',
                points: [
                    'подбираем транспорт под габариты и массу;',
                    'учитываем разрешения, сопровождение и особенности маршрута;',
                    'контролируем этапы перевозки от погрузки до выгрузки.',
                ],
                cta: 'Если хотите быстро проверить возможность перевозки, пришлите габариты, вес, маршрут и желаемую дату погрузки.',
                asset: 'heavy-equipment.svg',
            ),
            self::template(
                slug: 'aa-temperature-cargo-cold',
                name: 'Температурные грузы — холодное письмо',
                subject: 'Температурная перевозка без потери контроля',
                preheader: 'Подберём маршрут и транспорт для груза, которому важен температурный режим.',
                title: 'Температурный режим под контролем',
                intro: 'Если груз чувствителен к температуре, важно заранее понимать не только ставку, но и доступность транспорта, время в пути и точки контроля.',
                angle: 'Мы помогаем подобрать схему перевозки, где режим и сроки не расходятся с реальностью маршрута.',
                points: [
                    'подбираем транспорт под режим и объём партии;',
                    'сравниваем прямые и сборные варианты, если это допустимо;',
                    'фиксируем требования к контролю температуры на маршруте.',
                ],
                cta: 'Пришлите режим, маршрут, объём и дату. Посмотрим, какой вариант можно рассчитать без лишней нагрузки на бюджет.',
                asset: 'temperature.svg',
            ),
            self::template(
                slug: 'aa-groupage-cargo-cold',
                name: 'Сборные грузы — холодное письмо',
                subject: 'Сборные перевозки для небольших партий',
                preheader: 'Поможем выбрать схему, если полный транспорт пока не нужен.',
                title: 'Сборный груз без переплаты за пустой объём',
                intro: 'Когда партия небольшая, отдельный транспорт не всегда оправдан. В таких случаях полезно сравнить сборную схему и прямую доставку.',
                angle: 'Мы смотрим не только на ставку, но и на срок, количество перегрузок и требования к грузу.',
                points: [
                    'считаем варианты для партий от небольших коробов до паллет;',
                    'сравниваем экономию со сроками и рисками перегрузок;',
                    'помогаем понять, когда выгоднее перейти на отдельную машину.',
                ],
                cta: 'Если есть регулярные небольшие партии, пришлите тип груза, вес, объём и направления. Предложим понятную схему расчёта.',
                asset: 'warehouse.svg',
            ),
            self::template(
                slug: 'aa-rate-request-cold',
                name: 'Срочный расчёт ставки — холодное письмо',
                subject: 'Можем быстро проверить ставку по маршруту',
                preheader: 'Если нужен ориентир по перевозке, достаточно маршрута, груза и даты.',
                title: 'Быстрая проверка ставки по маршруту',
                intro: 'Если у вас периодически появляются маршруты, по которым нужно быстро понять порядок ставки, можем быть вторым источником расчёта.',
                angle: 'Это удобно, когда нужно сверить бюджет, проверить предложение текущего подрядчика или быстро ответить клиенту.',
                points: [
                    'даём первичный ориентир по ставке и срокам;',
                    'подсказываем, какие параметры сильнее всего влияют на цену;',
                    'при необходимости раскладываем несколько вариантов маршрута.',
                ],
                cta: 'Для проверки достаточно маршрута, даты, веса, объёма и типа груза. Ответим с первичной оценкой.',
                asset: 'rate.svg',
            ),
            self::template(
                slug: 'aa-follow-up-call-cold',
                name: 'Follow-up после звонка',
                subject: 'Коротко по логистике после разговора',
                preheader: 'Собрал основные направления, где можем быть полезны.',
                title: 'Коротко о том, где можем помочь',
                intro: 'Спасибо за разговор. Отправляю краткое описание, чтобы было проще вернуться к теме, когда появится конкретная перевозка.',
                angle: 'Мы подключаемся к задачам, где важны маршрут, сроки, документы и спокойный контроль движения груза.',
                points: [
                    'международные и внутрироссийские перевозки;',
                    'сложные, специальные и негабаритные грузы;',
                    'проверка ставки и маршрута до запуска перевозки.',
                ],
                cta: 'Когда появится задача, пришлите маршрут и параметры груза. Подготовим расчёт или предложим альтернативную схему.',
                asset: 'documents.svg',
            ),
            self::template(
                slug: 'aa-dormant-lead-return',
                name: 'Возврат уснувшего лида',
                subject: 'Вернуться к вопросу перевозок?',
                preheader: 'Если задачи по логистике снова актуальны, можем быстро обновить расчёт.',
                title: 'Можно обновить расчёт под текущие условия',
                intro: 'Ранее обсуждали логистику. Понимаю, что такие задачи часто откладываются, а потом возвращаются уже с новыми сроками и бюджетом.',
                angle: 'Если вопрос снова актуален, можем быстро пересчитать маршрут и проверить, какие варианты сейчас выглядят рабочими.',
                points: [
                    'обновим ставку под текущую дату и направление;',
                    'проверим, не появились ли более удобные варианты маршрута;',
                    'подскажем, какие данные нужны для точного расчёта.',
                ],
                cta: 'Если перевозка ещё в планах, ответьте маршрутом и ориентировочной датой. Дальше сориентируем по вариантам.',
                asset: 'calendar.svg',
            ),
            self::template(
                slug: 'aa-proposal-follow-up',
                name: 'После отправки КП',
                subject: 'Есть ли вопросы по расчёту?',
                preheader: 'Можем уточнить маршрут, сроки или альтернативный вариант перевозки.',
                title: 'Готовы уточнить расчёт по перевозке',
                intro: 'Отправляли предложение по перевозке. Хочу уточнить, всё ли понятно по маршруту, срокам и условиям.',
                angle: 'Если ставка или схема не попали в ожидания, можем пересчитать альтернативу: другой тип транспорта, дату или маршрут.',
                points: [
                    'уточним спорные параметры расчёта;',
                    'проверим альтернативный вариант перевозки;',
                    'подскажем, какие условия сильнее всего влияют на итоговую стоимость.',
                ],
                cta: 'Если удобно, ответьте, что нужно изменить в расчёте. Вернёмся с обновлённым вариантом.',
                asset: 'proposal.svg',
            ),
        ];
    }

    /**
     * @return array{slug: string, name: string, subject: string, preheader: string, html_body: string, css_inline: string}
     */
    public static function templateBySlug(string $slug): array
    {
        foreach (self::templates() as $template) {
            if ($template['slug'] === $slug) {
                return $template;
            }
        }

        return self::templates()[0];
    }

    public static function htmlBody(string $slug): string
    {
        return self::templateBySlug($slug)['html_body'];
    }

    public static function cssInline(): string
    {
        return <<<'CSS'
body {
  margin: 0;
  padding: 0;
  background-color: #ffffff;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, Helvetica, sans-serif;
  color: #56565a;
}
table {
  border-collapse: collapse;
}
img {
  border: 0;
  line-height: 100%;
  outline: none;
  text-decoration: none;
}
a {
  color: #1d4ed8;
}
CSS;
    }

    /**
     * @param  list<string>  $points
     * @return array{slug: string, name: string, subject: string, preheader: string, html_body: string, css_inline: string}
     */
    private static function template(
        string $slug,
        string $name,
        string $subject,
        string $preheader,
        string $title,
        string $intro,
        string $angle,
        array $points,
        string $cta,
        string $asset,
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'subject' => $subject,
            'preheader' => $preheader,
            'html_body' => self::layout($preheader, $title, $intro, $angle, $points, $cta, $asset),
            'css_inline' => self::cssInline(),
        ];
    }

    /**
     * @param  list<string>  $points
     */
    private static function layout(
        string $preheader,
        string $title,
        string $intro,
        string $angle,
        array $points,
        string $cta,
        string $asset,
    ): string {
        $pointsHtml = collect($points)
            ->map(fn (string $point): string => '<li style="margin:0 0 9px;">'.e($point).'</li>')
            ->implode('');
        $assetUrl = self::ASSET_BASE.'/'.$asset;

        return <<<HTML
<div style="display:none;max-height:0;overflow:hidden;color:#ffffff;font-size:1px;line-height:1px;">{$preheader}</div>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background-color:#ffffff;">
  <tbody>
    <tr>
      <td align="center" style="padding:22px 10px 34px;">
        <table width="660" cellpadding="0" cellspacing="0" border="0" style="width:660px;max-width:660px;background-color:#ffffff;">
          <tbody>
            <tr>
              <td style="padding:4px 18px 18px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tbody>
                    <tr>
                      <td width="47%" valign="top" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,Helvetica,sans-serif;">
                        <table cellpadding="0" cellspacing="0" border="0">
                          <tbody>
                            <tr>
                              <td valign="top" style="padding-top:4px;padding-right:10px;">
                                <img src="/assets/favicon/favicon.svg" width="28" height="28" alt="Автоальянс-Смоленск" style="display:block;width:28px;height:28px;">
                              </td>
                              <td valign="top" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,Helvetica,sans-serif;">
                                <div style="font-family:Georgia,'Times New Roman',serif;font-size:22px;font-weight:700;line-height:1.2;color:#5b5b5f;">{responsible.name}</div>
                                <div style="margin-top:10px;font-size:15px;line-height:1.45;color:#2f3338;font-weight:700;">{responsible.phone}</div>
                                <div style="margin-top:3px;font-size:15px;line-height:1.45;color:#1d4ed8;font-weight:700;text-decoration:underline;">{responsible.email}</div>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                      <td width="53%" align="right" valign="top" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,Helvetica,sans-serif;">
                        <table cellpadding="0" cellspacing="0" border="0" align="right">
                          <tbody>
                            <tr>
                              <td valign="middle" style="padding-right:12px;">
                                <img src="/assets/favicon/favicon.svg" width="54" height="54" alt="Автоальянс-Смоленск" style="display:block;width:54px;height:54px;">
                              </td>
                              <td valign="middle" align="left">
                                <div style="font-size:24px;font-weight:900;line-height:1;color:#f97316;text-transform:uppercase;letter-spacing:.02em;">Автоальянс</div>
                                <div style="margin-top:3px;font-size:27px;font-weight:900;line-height:1;color:#3f3f46;text-transform:uppercase;letter-spacing:.02em;">Смоленск</div>
                              </td>
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
              <td style="padding:0 18px 6px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tbody>
                    <tr>
                      <td width="405" valign="top" style="padding-right:28px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,Helvetica,sans-serif;">
                        <p style="margin:0 0 28px;font-size:20px;line-height:1.45;color:#5a5a5f;">Добрый день, <strong style="color:#de3b3b;">{counterparty.contact_person}</strong>.</p>
                        <p style="margin:0 0 28px;font-size:20px;line-height:1.48;color:#5a5a5f;">{$intro}</p>
                        <p style="margin:0;font-size:20px;line-height:1.48;color:#5a5a5f;">{$angle}</p>
                      </td>
                      <td width="200" valign="middle" align="center">
                        <img src="{$assetUrl}" width="170" height="170" alt="Логистика" style="display:block;width:170px;height:170px;max-width:170px;">
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:30px 18px 4px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tbody>
                    <tr>
                      <td width="405" valign="top" style="padding-right:28px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,Helvetica,sans-serif;">
                        <div style="font-size:21px;font-weight:800;color:#4f4f54;margin-bottom:10px;">{$title}</div>
                        <ul style="margin:0;padding-left:20px;font-size:20px;line-height:1.48;color:#5a5a5f;">{$pointsHtml}</ul>
                      </td>
                      <td width="200" valign="middle" align="center">
                        <img src="/assets/proposal-emails/documents.svg" width="155" height="155" alt="Документы и контроль" style="display:block;width:155px;height:155px;max-width:155px;">
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:30px 18px 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tbody>
                    <tr>
                      <td width="405" valign="top" style="padding-right:28px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,Helvetica,sans-serif;">
                        <p style="margin:0;font-size:20px;line-height:1.48;color:#5a5a5f;">{$cta}</p>
                        <p style="margin:20px 0 0;font-size:17px;line-height:1.45;color:#3f3f46;"><strong>{responsible.name}</strong><br>{responsible.phone}<br>{responsible.email}</p>
                      </td>
                      <td width="200" valign="middle" align="center">
                        <img src="/assets/proposal-emails/rate.svg" width="155" height="155" alt="Расчёт перевозки" style="display:block;width:155px;height:155px;max-width:155px;">
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
            <tr>
              <td align="right" style="padding:14px 18px 0;font-family:Georgia,'Times New Roman',serif;">
                <div style="font-size:12px;line-height:1.6;color:#6b6b70;">Автоальянс-Смоленск. Если письмо не по адресу, ответьте на него — больше не будем отвлекать.</div>
              </td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
  </tbody>
</table>
HTML;
    }
}
