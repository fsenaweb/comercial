<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exporta um relatório do catálogo (ver ReportController) como .xlsx com células nativas
 * de verdade (filtrável/somável — ao contrário do export legado do AppLoja, que desenha a
 * planilha inteira como imagens vetoriais sobre uma grade de pixel; decisão registrada em
 * docs/05-sprints.md, Sprint 5). O bloco de "papel timbrado" (logo/nome/CNPJ/endereço), esse
 * sim, é uma única imagem gerada via GD — colar o texto em células mescladas ficava "pendurado"
 * numa planilha real (a grade continua além da última coluna de dados, sem limite de página
 * visível fora do modo de impressão), então uma imagem autocontida resolve isso melhor.
 */
class ReportArrayExport
{
    private const MIN_COLUMN_WIDTH = 8.0;

    /** Largura alvo (unidades de coluna do Excel) pra ocupar a área útil de uma folha A4 retrato com margens normais. */
    private const PORTRAIT_TARGET_WIDTH = 94.0;

    /** Idem, A4 paisagem — usada quando o relatório tem colunas demais pra caber confortavelmente em retrato. */
    private const LANDSCAPE_TARGET_WIDTH = 141.0;

    /** A partir de quantas colunas de dados a planilha muda pra paisagem. */
    private const LANDSCAPE_COLUMN_THRESHOLD = 4;

    private const LETTERHEAD_IMAGE_WIDTH = 900;

    private const LETTERHEAD_IMAGE_HEIGHT = 160;

    private const LETTERHEAD_DISPLAY_HEIGHT = 115;

    public function __construct(
        private readonly array $report,
        private readonly array $letterhead,
    ) {
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'report_').'.xlsx';
        $letterheadImagePath = $this->buildLetterheadImage();

        $labels = array_column($this->report['headers'], 'label');
        $keys = array_column($this->report['headers'], 'key');
        $lastColumnIndex = count($labels) - 1;
        $lastColumn = Coordinate::stringFromColumnIndex($lastColumnIndex + 1);
        $isLandscape = count($labels) >= self::LANDSCAPE_COLUMN_THRESHOLD;

        $rows = array_map(
            fn ($r) => array_map(fn ($key) => (string) ($r[$key] ?? ''), $keys),
            $this->report['rows'],
        );
        $widths = $this->columnWidths($labels, $rows, $isLandscape ? self::LANDSCAPE_TARGET_WIDTH : self::PORTRAIT_TARGET_WIDTH);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $drawing = new Drawing;
        $drawing->setPath($letterheadImagePath);
        $drawing->setHeight(self::LETTERHEAD_DISPLAY_HEIGHT);
        $drawing->setCoordinates('A1');
        $drawing->setWorksheet($sheet);

        $defaultRowHeightPx = 20;
        $row = (int) ceil(self::LETTERHEAD_DISPLAY_HEIGHT / $defaultRowHeightPx) + 2; // + respiro antes do título

        $titleRow = $row;
        $sheet->mergeCells("A{$titleRow}:{$lastColumn}{$titleRow}");
        $sheet->setCellValue("A{$titleRow}", mb_strtoupper($this->report['title']));
        $sheet->getStyle("A{$titleRow}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$titleRow}")->getAlignment()->setHorizontal('center');
        $row += 2;

        $columnAlignments = $this->columnAlignments($labels, $rows);

        $headerRow = $row;
        foreach ($labels as $index => $label) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$column}{$headerRow}", $label);
            $sheet->getStyle("{$column}{$headerRow}")->getAlignment()->setHorizontal($columnAlignments[$index]);
        }
        $headerRange = "A{$headerRow}:{$lastColumn}{$headerRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
        $sheet->getStyle($headerRange)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
        $sheet->getStyle($headerRange)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');

        $dataStartRow = $headerRow + 1;

        foreach ($rows as $rowIndex => $rowValues) {
            $excelRow = $dataStartRow + $rowIndex;
            foreach ($rowValues as $colIndex => $value) {
                $column = Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValueExplicit("{$column}{$excelRow}", $value, DataType::TYPE_STRING);
                $sheet->getStyle("{$column}{$excelRow}")->getAlignment()->setHorizontal($columnAlignments[$colIndex]);
            }
        }

        $lastDataRow = $dataStartRow + count($rows) - 1;

        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }

        // Encaixar em 1 página de largura (altura livre, cresce por quantas páginas precisar) —
        // sem isso o Excel imprime com as larguras "naturais" das colunas, que raramente batem
        // exatamente com a largura útil da folha, sobrando espaço em branco ou vazando pra uma
        // segunda página só por causa de uma coluna ultrapassando a margem por pouco.
        $sheet->getPageSetup()
            ->setOrientation($isLandscape ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setPrintArea("A1:{$lastColumn}{$lastDataRow}");

        (new Xlsx($spreadsheet))->save($path);
        @unlink($letterheadImagePath);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Monta o bloco de "papel timbrado" (logo + nome/CNPJ/endereço/contato + linha divisória)
     * como uma única imagem PNG via GD, num arquivo temporário — o chamador é responsável por
     * apagá-lo depois de salvar a planilha.
     */
    private function buildLetterheadImage(): string
    {
        $width = self::LETTERHEAD_IMAGE_WIDTH;
        $height = self::LETTERHEAD_IMAGE_HEIGHT;

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $black = imagecolorallocate($image, 20, 20, 20);
        $gray = imagecolorallocate($image, 60, 60, 60);

        $fontBold = base_path('vendor/mpdf/mpdf/ttfonts/DejaVuSans-Bold.ttf');
        $fontRegular = base_path('vendor/mpdf/mpdf/ttfonts/DejaVuSans.ttf');

        $textX = 10;

        if ($this->letterhead['logo_path'] && is_file($this->letterhead['logo_path'])) {
            $logo = @imagecreatefrompng($this->letterhead['logo_path']);

            if ($logo !== false) {
                $logoSize = 125;
                $resized = imagecreatetruecolor($logoSize, $logoSize);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $logo, 0, 0, 0, 0, $logoSize, $logoSize, imagesx($logo), imagesy($logo));
                imagecopy($image, $resized, 10, 10, 0, 0, $logoSize, $logoSize);
                imagedestroy($logo);
                imagedestroy($resized);
                $textX = 10 + $logoSize + 15;
            }
        }

        $y = 30;
        imagettftext($image, 19, 0, $textX, $y, $black, $fontBold, $this->letterhead['display_name']);
        $y += 26;

        foreach (['corporate_name', 'cnpj', 'address_line', 'contact_line'] as $key) {
            if (! empty($this->letterhead[$key])) {
                $text = $key === 'cnpj' ? "CNPJ: {$this->letterhead[$key]}" : $this->letterhead[$key];
                imagettftext($image, 13, 0, $textX, $y, $gray, $fontRegular, $text);
                $y += 20;
            }
        }

        imagefilledrectangle($image, 0, $height - 3, $width, $height - 1, $black);

        $path = tempnam(sys_get_temp_dir(), 'letterhead_').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * Alinhamento por coluna (0-based): direita pra colunas numéricas/monetárias (quantidade,
     * preço, total...), esquerda pro resto (nome, código, categoria...) — decidido pelo
     * conteúdo real de cada coluna, não pela posição. Sem isso, o PhpSpreadsheet detecta
     * sozinho que "10" é número (alinha à direita) mas "R$ 199,00" é texto (alinha à
     * esquerda), e as duas colunas vizinhas ficam "coladas" uma na outra.
     *
     * @param  string[]  $labels
     * @param  array<int, string[]>  $rows
     * @return array<int, string>
     */
    private function columnAlignments(array $labels, array $rows): array
    {
        $alignments = [];

        foreach (array_keys($labels) as $index) {
            $values = array_filter(array_column($rows, $index), fn ($value) => $value !== '');
            $isNumeric = $values !== [] && array_reduce(
                $values,
                fn ($carry, $value) => $carry && (bool) preg_match('/^-?(R\$\s?)?[\d.,]+%?$/', trim($value)),
                true,
            );
            $alignments[$index] = $isNumeric ? 'right' : 'left';
        }

        return $alignments;
    }

    /**
     * @param  string[]  $labels
     * @param  array<int, string[]>  $rows
     * @return float[] largura por índice de coluna (0-based). Parte de uma largura "natural" (baseada no
     *                 maior conteúdo de cada coluna) e escala tudo proporcionalmente pra ocupar a folha A4
     *                 inteira — cada relatório molda a própria largura de coluna e orientação de página
     *                 conforme o nº de colunas que tem, em vez de ficar espremido num canto da planilha.
     */
    private function columnWidths(array $labels, array $rows, float $targetTotalWidth): array
    {
        $natural = array_map(static fn ($label) => mb_strlen($label), $labels);

        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $natural[$index] = max($natural[$index] ?? 0, mb_strlen($value));
            }
        }

        $natural = array_map(fn ($length) => max(self::MIN_COLUMN_WIDTH, $length + 4), $natural);

        $naturalSum = array_sum($natural);
        if ($naturalSum < $targetTotalWidth) {
            $scale = $targetTotalWidth / $naturalSum;
            $natural = array_map(fn ($width) => round($width * $scale, 1), $natural);
        }

        return $natural;
    }
}
