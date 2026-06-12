<?php

namespace Tests\Unit;

use App\Services\ManagementAccounting\SberRegistryXlsxParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class SberRegistryXlsxParserTest extends TestCase
{
    #[Test]
    public function it_parses_sber_registry_rows_from_xlsx(): void
    {
        $path = $this->createSampleXlsx();

        $parsed = (new SberRegistryXlsxParser)->parse($path);

        $this->assertSame('40702810959710001997', $parsed['account_number']);
        $this->assertSame('2026-06-09', $parsed['period_from']);
        $this->assertSame('2026-06-09', $parsed['period_to']);
        $this->assertCount(2, $parsed['lines']);
        $this->assertSame('in', $parsed['lines'][0]['direction']);
        $this->assertSame(150000.0, $parsed['lines'][0]['amount']);
        $this->assertStringContainsString('АС-2606-0001', $parsed['lines'][0]['description']);
        $this->assertSame('out', $parsed['lines'][1]['direction']);
        $this->assertSame(500.0, $parsed['lines'][1]['amount']);

        @unlink($path);
    }

    private function createSampleXlsx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sber-xlsx-').'.xlsx';
        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('xl/sharedStrings.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <si><t>Р/с 40702810959710001997</t></si>
  <si><t>№ п/п</t></si>
  <si><t>Дата</t></si>
  <si><t>Информация</t></si>
  <si><t>Поступление</t></si>
  <si><t>Списание</t></si>
  <si><t>1</t></si>
  <si><t>09.06.2026</t></si>
  <si><t>Оплата по заявке АС-2606-0001</t></si>
  <si><t>150000</t></si>
  <si><t></t></si>
  <si><t>2</t></si>
  <si><t>09.06.2026</t></si>
  <si><t>Комиссия банка</t></si>
  <si><t></t></si>
  <si><t>500</t></si>
  <si><t>Итого</t></si>
</sst>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1"><c r="A1" t="s"><v>0</v></c></row>
    <row r="3">
      <c r="A3" t="s"><v>1</v></c>
      <c r="B3" t="s"><v>2</v></c>
      <c r="C3" t="s"><v>3</v></c>
      <c r="D3" t="s"><v>4</v></c>
      <c r="E3" t="s"><v>5</v></c>
    </row>
    <row r="4">
      <c r="A4" t="s"><v>6</v></c>
      <c r="B4" t="s"><v>7</v></c>
      <c r="C4" t="s"><v>8</v></c>
      <c r="D4" t="s"><v>9</v></c>
      <c r="E4" t="s"><v>10</v></c>
    </row>
    <row r="5">
      <c r="A5" t="s"><v>11</v></c>
      <c r="B5" t="s"><v>12</v></c>
      <c r="C5" t="s"><v>13</v></c>
      <c r="D5" t="s"><v>14</v></c>
      <c r="E5" t="s"><v>15</v></c>
    </row>
    <row r="6"><c r="A6" t="s"><v>16</v></c></row>
  </sheetData>
</worksheet>
XML);

        $zip->close();

        return $path;
    }
}
