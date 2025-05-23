<?php
// app/Console/Commands/ImportCharacteristicMappings.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Characteristic;
use App\Models\CharacteristicKeyMapping;

class ImportCharacteristicMappings extends Command
{
    protected $signature = 'import:mappings {file : Path to mapping CSV}';
    protected $description = 'Import characteristic key mappings from CSV (file‑row layout)';

    public function handle()
    {
        $path = $this->argument('file');

        if (!is_readable($path)) {
            return $this->error("File not found or not readable: {$path}");
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return $this->error("Cannot open file: {$path}");
        }

        // 1) Читаем первую строку "сырым" и определяем разделитель:
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return $this->error("Empty file: {$path}");
        }

        // Авто‑детект: если табов больше запятых — используем таб, иначе запятую
        $tabs   = substr_count($firstLine, "\t");
        $commas = substr_count($firstLine, ",");
        $delimiter = $tabs > $commas ? "\t" : ",";

        // Перемотаем обратно
        rewind($handle);

        // 2) Парсим первую строку по выбранному delimiter
        $fileNames = fgetcsv($handle, 0, $delimiter);
        if (!$fileNames || count($fileNames) < 2) {
            fclose($handle);
            return $this->error("Invalid header row: need at least two columns");
        }

        // Очистка имён файлов
        foreach ($fileNames as &$fn) {
            $fn = preg_replace('/^файл\s*-\s*/iu', '', trim((string)$fn));
        }
        unset($fn);

        $rowNum = 1;
        // 3) Проходим по всем остальным строкам
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            // Пропускаем, если нет даже ключа в колонке 0
            $inductionKey = mb_substr(trim((string)($row[0] ?? '')), 0, 191);
            if ($inductionKey === '') {
                $this->warn("Row {$rowNum}: empty inductionKey, skipping");
                continue;
            }

            $characteristic = Characteristic::Create(['key' => $inductionKey]);

            // 3.1. Проходим по всем колонкам строки (вплоть до count($row))
            foreach ($row as $colIndex => $remoteKey) {
                // Пропускаем колонку 0
                if ($colIndex === 0 || trim((string)$remoteKey) === '') {
                    continue;
                }
                $remoteKey = trim((string)$remoteKey);
                if ($remoteKey === '') {
                    continue;
                }

                // Имя соответствующего файла из header (если есть)
                $fileName = $fileNames[$colIndex] ?? null;
                $site     = $fileName
                    ? $this->detectSiteFromFileName($fileName)
                    : 'induction';

                CharacteristicKeyMapping::create([
                    'site'              => $site,
                    'remote_key'        => $remoteKey,
                    'characteristic_id' => $characteristic->id,
                    'file_name'         => $fileName
                ]);
            }
        }

        fclose($handle);
        $this->info("Mappings imported successfully from {$path}");
    }

    protected function detectSiteFromFileName(string $fileName): string
    {
        $fn = mb_strtolower($fileName, 'UTF-8');
        if (str_contains($fn, 'sensoren'))  return 'sensoren';
        if (str_contains($fn, 'megak'))     return 'megak';
        if (str_contains($fn, 'beskonta'))  return 'beskonta';

        return 'induction';
    }
}
