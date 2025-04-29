<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Models\Product;

class ImportCsv extends Command
{
    protected $signature = 'import:csv {filePath?}';
    protected $description = 'Импортировать CSV-файлы товаров из папок производителей или один конкретный файл';

    public function handle()
    {
        ini_set('max_execution_time', 0);
        $filePath = $this->argument('filePath');

        if ($filePath) {
            $this->info("Обрабатываю файл: {$filePath}");
            $this->importFile($filePath);
        } else {
            $basePath = base_path('app/imports/products');

            if (! File::exists($basePath)) {
                $this->error("Директория {$basePath} не найдена.");
                return;
            }

            $currencyMap = [
                'руб' => 'RUB', 'руб.' => 'RUB', 'рублей' => 'RUB', 'rur' => 'RUB', '₽' => 'RUB',
                'дол' => 'USD', 'долл' => 'USD', 'доллар' => 'USD', 'usd' => 'USD', '$' => 'USD',
                'евро' => 'EUR', 'eur' => 'EUR', '€' => 'EUR',
                'юань' => 'CNY', 'юаней' => 'CNY', 'cny' => 'CNY', '¥' => 'CNY',
                'йена' => 'JPY', 'иена' => 'JPY', 'jpy' => 'JPY',
                'фунт' => 'GBP', 'gbp' => 'GBP', '£' => 'GBP',
                'грн' => 'UAH', 'гривна' => 'UAH', 'uah' => 'UAH',
                'тенге' => 'KZT', 'kzt' => 'KZT', '₸' => 'KZT',
            ];

            foreach (File::directories($basePath) as $manufacturerPath) {
                $manufacturer = basename($manufacturerPath);

                foreach (File::files($manufacturerPath) as $csvFile) {
                    $this->info("Обрабатываю: {$csvFile->getFilename()} (производитель: {$manufacturer})");
                    $this->importFile($csvFile->getRealPath());
                }
            }
        }

        $this->info('Импорт завершён.');
    }

    private function importFile($filePath)
    {
        if (!File::exists($filePath)) {
            $this->error("Файл {$filePath} не найден.");
            return;
        }

        $raw = file_get_contents($filePath);
        $encoding = mb_detect_encoding($raw, ['UTF-8', 'CP1251', 'Windows-1251'], true);

        $utf8 = ($encoding !== 'UTF-8')
            ? mb_convert_encoding($raw, 'UTF-8', $encoding ?: 'CP1251')
            : $raw;

        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $utf8)));

        $rows = array_map(
            fn($line) => str_getcsv($line, ';'),
            $lines
        );

        $headers = array_shift($rows);

        foreach ($rows as $idx => $row) {
            if (count($row) < 3 || trim($row[0]) === '' || trim($row[1]) === '' || trim($row[2]) === '') {
                $this->warn("  Строка №" . ($idx + 2) . " пропущена (недостаточно данных)");
                continue;
            }

            [$nameRaw, $priceRaw, $infoRaw] = $row;

            $name     = trim($nameRaw);
            $priceRaw = trim($priceRaw);
            $infoRaw  = trim($infoRaw);

            $price = null;
            $currency = null;

            if (preg_match('/([\d\s]+(?:[.,]\d+)?)([\p{L}\p{Sc}]+)?/u', $priceRaw, $m)) {
                $price = isset($m[1])
                    ? (float) str_replace([' ', ','], ['', '.'], $m[1])
                    : null;

                if (isset($m[2])) {
                    $rawCurrency = mb_strtolower(trim($m[2]));
                    $currency = $currencyMap[$rawCurrency] ?? null;
                }
            }

            $parts = array_map('trim', explode(';', $infoRaw));
            $parameters = [];
            for ($i = 0; $i + 1 < count($parts); $i += 2) {
                if ($parts[$i] !== '') {
                    $parameters[$parts[$i]] = $parts[$i + 1];
                }
            }

            Product::create([
                'name'         => $name,
                'manufacturer' => basename(dirname($filePath)),
                'parameters'   => $parameters,
                'price'        => $price,
                'currency'     => $currency,
            ]);
        }
    }
}
