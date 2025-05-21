<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use League\Csv\Reader;
use League\Csv\CharsetConverter;
use App\Models\Product;
use App\Models\Characteristic;
use App\Models\CharacteristicKeyMapping;
use App\Models\ProductCharacteristicValue;

class ImportInductionProducts extends Command
{
    protected $signature = 'import:induction {directory : Путь к папке с CSV}';
    protected $description = 'Импорт товаров Индукции из всех CSV в указанной папке';

    public function handle()
    {
        $dir = rtrim($this->argument('directory'), DIRECTORY_SEPARATOR);
        if (!is_dir($dir) || !is_readable($dir)) {
            return $this->error("Папка не найдена или недоступна: {$dir}");
        }
        $this->info("Сканирую папку: {$dir}");

        $site     = 'induction';
        $mappings = CharacteristicKeyMapping::where('site', $site)
            ->get()
            ->keyBy('remote_key')
            ->map(fn($m)=>[
                'id'                => $m->id,
                'characteristic_id' => $m->characteristic_id,
            ])->toArray();
        $chars = Characteristic::pluck('id','key')->toArray();

        foreach (glob("{$dir}/*.csv") as $file) {
            $this->info("→ Файл: " . basename($file));
            $this->importFile($site, $file, $mappings, $chars);
        }
        $this->info("Готово.");
    }

    protected function importFile(string $site, string $path, array &$mappings, array &$chars): void
    {
        // 1) Определяем разделитель по первой строке
        $firstLine = (string)@fgets(fopen($path,'r'));
        $tabs   = substr_count($firstLine, "\t");
        $semis  = substr_count($firstLine, ";");
        $commas = substr_count($firstLine, ",");
        if ($tabs > $semis && $tabs > $commas)  $delim="\t";
        elseif ($semis > $tabs && $semis > $commas) $delim=";";
        else $delim=",";
        $this->info("   использую разделитель: ".($delim==="\t"?"tab":$delim));

        // 2) Открываем CSV
        $csv = Reader::createFromPath($path,'r');
        $csv->setDelimiter($delim);

        // 3) Проверка и конвертация кодировки
        $records = iterator_to_array($csv->getRecords(), false);

        $needConv = false;
        foreach($records as $row){
            foreach($row as $cell){
                if (!mb_check_encoding($cell,'UTF-8')){
                    $needConv = true; break 2;
                }
            }
        }
        if($needConv){
            CharsetConverter::addTo($csv,'Windows-1251','UTF-8');
            $this->info("   конвертировал CP1251→UTF-8");
        }

        $currentProduct = null;
        $cntProd = 0;
        $cntAttr = 0;
        $fileName = basename($path);

        foreach($csv->getRecords() as $row){
            // Оставляем только непустые ячейки
            $cells = array_values(array_filter(array_map('trim',$row), fn($v)=>$v!==''));

            // Попытка определить начало нового товара:
            // Нужно хотя бы 4 ячейки, последние три: артикул, код, «да»/«нет»
            if(count($cells)>=3){

                $last = strtolower($cells[count($cells)-1]);
                $code = $cells[count($cells)-2];
                $art  = $cells[count($cells)-3];

                if (in_array($last,['Да','Нет','да','нет'],true) && $code!=='' && $art!=='') {
                    // создаём новый товар
                    $name = $cells[0];  // первое непустое в строке
                    $currentProduct = Product::create([
                        'source_site'=> $site,
                        'name'       => $name,
                        'price'      => null,
                        'info'       => null,
                        'file_name'  => $fileName,
                        'link'       => null,
                    ]);
                    $cntProd++;
                    $this->line("    [+] Товар: {$currentProduct->name}");
                    continue;
                }
            }

            // Иначе — это характеристика для текущего товара
            if (!$currentProduct || count($cells)<2) {
                continue;
            }
            // Параметр = первый элемент, значение = последний
            $param = $cells[0];
            $value = $cells[count($cells)-1];

            // Определяем characteristic_id / mapping_id
            if (isset($mappings[$param])){
                $charId = $mappings[$param]['characteristic_id'];
                $mapId  = $mappings[$param]['id'];
            } else {
                $charId = $chars[$param] ?? null;
                if(!$charId){
                    $charId = Characteristic::create(['key'=>$param])->id;
                    $chars[$param] = $charId;
                    $this->line("      [*] Новая харак-ка: {$param}");
                }
                $mapId = null;
            }

            ProductCharacteristicValue::create([
                'product_id'                    => $currentProduct->id,
                'characteristic_id'             => $charId,
                'characteristic_key_mapping_id' => $mapId,
                'value'                         => $value,
            ]);
            $cntAttr++;
        }

        $this->info("   создано {$cntProd} товаров, {$cntAttr} значений");
    }
}
