<?php

namespace Tests\Unit;

use App\Support\MailSync\MailHtmlSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailHtmlSanitizerTest extends TestCase
{
    #[Test]
    public function to_plain_text_strips_style_blocks_and_css_rules(): void
    {
        $html = <<<'HTML'
<p>Встречное предложение на груз: "#UJJ5461, оборудование, Обнинск-Сочи"</p>
<style>
.ExternalClass { width: 100%; }
img { border: 0 none; height: auto; }
#outlook a { padding: 0; }
</style>
HTML;

        $plain = MailHtmlSanitizer::toPlainText($html);

        $this->assertStringContainsString('Встречное предложение на груз', $plain);
        $this->assertStringContainsString('#UJJ5461', $plain);
        $this->assertStringNotContainsString('.ExternalClass', $plain);
        $this->assertStringNotContainsString('border: 0 none', $plain);
        $this->assertStringNotContainsString('#outlook', $plain);
    }

    #[Test]
    public function to_plain_text_decodes_entities_and_preserves_line_breaks(): void
    {
        $html = '<p>Строка&nbsp;один</p><br><div>Строка два</div>';

        $plain = MailHtmlSanitizer::toPlainText($html);

        $this->assertStringContainsString('Строка один', $plain);
        $this->assertStringContainsString('Строка два', $plain);
    }

    #[Test]
    public function sanitize_removes_scripts_and_inline_styles(): void
    {
        $html = '<p>Текст</p><script>alert(1)</script><style>.x{color:red}</style>';

        $sanitized = MailHtmlSanitizer::sanitize($html);

        $this->assertNotNull($sanitized);
        $this->assertStringContainsString('Текст', $sanitized);
        $this->assertStringNotContainsString('alert', $sanitized);
        $this->assertStringNotContainsString('.x', $sanitized);
    }

    #[Test]
    public function clean_plain_text_strips_ati_css_noise_and_footer(): void
    {
        $raw = <<<'TEXT'
        a img {
        #outlook a {
        #allrecords-internal td {
        #allrecords-internal ul {
        .t-records ol, .t-records ul {
            .apps img {
Встречное предложение на груз: "#UJJ5461, оборудование, Обнинск-Сочи, 1.50 т., 15.00 m3"
Встречное предложение на груз: #UJJ5461, оборудование, Обнинск-Сочи, 1.50 т., 15.00 m3, тентованный, бортовой, шаланда, 8 июня - 8 июня Ставка: 120000.00 руб б/нал без НДСДоп. информация: б/н, на выгрузкеФирма: "Багаутдинов Денис Фанзилович, ИП"Контакт: Багаутдинов ДенисМоб.: +7(919)6027232, E-mail: b.d.f.237@mail.ru
Не отвечайте на это сообщение, оно автоматически создано сервером.
Вы получили это письмо, потому что подписались на рассылки ATI.SU
© ATI.SU, Россия, Санкт-Петербург
TEXT;

        $plain = MailHtmlSanitizer::cleanPlainText($raw);

        $this->assertStringContainsString('Встречное предложение на груз:', $plain);
        $this->assertStringContainsString('#UJJ5461', $plain);
        $this->assertStringContainsString('Ставка:', $plain);
        $this->assertStringContainsString('120000.00', $plain);
        $this->assertStringContainsString('b.d.f.237@mail.ru', $plain);
        $this->assertStringNotContainsString('#outlook', $plain);
        $this->assertStringNotContainsString('#allrecords-internal', $plain);
        $this->assertStringNotContainsString('Не отвечайте на это сообщение', $plain);
        $this->assertStringNotContainsString('© ATI.SU', $plain);
        $this->assertSame(1, substr_count($plain, 'Встречное предложение на груз:'));
    }

    #[Test]
    public function noise_score_prefers_html_derived_body_over_css_plain_part(): void
    {
        $noisyPlain = "#outlook a {\n#allrecords-internal td {\nКороткий текст\n";
        $cleanHtmlDerived = "Встречное предложение на груз: #UJJ5461, Обнинск-Сочи\nСтавка: 120000.00 руб";

        $this->assertGreaterThan(
            MailHtmlSanitizer::noiseScore($cleanHtmlDerived),
            MailHtmlSanitizer::noiseScore($noisyPlain),
        );
    }
}
