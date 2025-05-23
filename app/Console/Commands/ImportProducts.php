<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use League\Csv\CharsetConverter;
use League\Csv\Reader;
use App\Models\Product;
use App\Models\Characteristic;
use App\Models\CharacteristicKeyMapping;
use App\Models\ProductCharacteristicValue;

class ImportProducts extends Command
{
    protected $signature = 'import:products {site : Site key} {directory : Path to CSV directory}';
    protected $description = 'Import products from CSV directory for a given site';

    public function handle()
    {
        $site = $this->argument('site');
        $dir  = rtrim($this->argument('directory'), DIRECTORY_SEPARATOR);

        $mappings = CharacteristicKeyMapping::where('site', $site)
            ->get()
            ->keyBy('remote_key')
            ->map(fn($item) => [
                'id' => $item->id,
                'characteristic_id' => $item->characteristic_id,
            ])
            ->toArray();


        $chars = Characteristic::pluck('id', 'key')->toArray();

        foreach (glob("{$dir}/*.csv") as $file) {
            $this->importFile($site, $file, $mappings, $chars);
        }

        $this->info("Products imported for site '{$site}'.");
    }

    protected function importFile(
        string $site,
        string $path,
        array &$mappings,
        array &$chars
    ): void {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $records = iterator_to_array($csv->getRecords(), false);
        $needsConversion = false;

        foreach ($records as $record) {
            foreach ($record as $value) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $needsConversion = true;
                    break 2;
                }
            }
        }

        if ($needsConversion) {
            CharsetConverter::addTo($csv, 'Windows-1251', 'UTF-8');
            $records = iterator_to_array($csv->getRecords(), false);
        }

        $fileName = basename($path);

        foreach ($csv->getRecords() as $row) {
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $product = Product::create([
                'source_site' => $site,
                'name'        => $name,
                'price'       => $row['price'] ?? null,
                'info'        => json_encode($row['info'] ?? []),
                'file_name'   => $fileName,
                'link'        => $row['link'] ?? null,
            ]);

            foreach ($row as $col => $val) {
                $val = trim((string)$val);
                if (in_array($col, ['name','price','info','link'], true) || $val === '') {
                    continue;
                }

                $charId = null;
                $mappingId = null;

                if (isset($mappings[$col])) {
                    $charId = $mappings[$col]['characteristic_id'];
                    $mappingId = $mappings[$col]['id'];
                } else {
                    if (!isset($chars[$col])) {
                        $chars[$col] = Characteristic::create(['key' => $col])->id;
                    }
                    $charId = $chars[$col];
                }

                ProductCharacteristicValue::create([
                    'product_id' => $product->id,
                    'characteristic_id' => $charId,
                    'characteristic_key_mapping_id' => $mappingId,
                    'value' => $val,
                ]);
            }
        }
    }
}
