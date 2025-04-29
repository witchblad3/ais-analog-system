<?php

namespace App\Http\Controllers;

use App\Actions\DetermineAnalogsAction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ProductController extends Controller
{
    public function home(Request $request, DetermineAnalogsAction $action)
    {
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_order', 'asc');

        $sortableFields = ['name', 'manufacturer', 'parameters', 'price', 'currency'];

        if (!in_array($sortBy, $sortableFields)) {
            $sortBy = 'name';
        }

        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $products = Product::query()
            ->when($request->name, fn($q) => $q->where('name', 'like', '%' . $request->name . '%'))
            ->when($request->manufacturer, fn($q) => $q->where('manufacturer', 'like', '%' . $request->manufacturer . '%'))
            ->when($request->parameters, fn($q) =>
            $q->where('parameters', 'like', '%' . $request->parameters . '%')
            )
            ->when($request->price, fn($q) => $q->where('price', $request->price))
            ->orderBy($sortBy, $sortDirection)
            ->paginate(15);
        $analogProducts = [];
        $matchDegrees = [];

        foreach ($products as $product) {
            $analogs = $action->execute($product);
            $analogProducts[$product->id] = $analogs;
            $matchDegrees[$product->id] = [];

            foreach ($analogs as $analog) {
                $productParams = $product->parameters;
                $analogParams = $analog->parameters;

                $totalParams = count($productParams);
                if ($totalParams == 0) {
                    $matchDegrees[$product->id][$analog->id] = 0;
                    continue;
                }

                $matchedParams = count(array_intersect($productParams, $analogParams));
                $matchPercentage = ($matchedParams / $totalParams) * 100;
                $matchPercentage = round($matchPercentage);

                $matchDegrees[$product->id][$analog->id] = $matchPercentage;
            }
        }
        return view('app', compact('products', 'analogProducts', 'matchDegrees', 'sortBy', 'sortDirection'));
    }

    public function showAnalogs($id)
    {
        $product = Product::findOrFail($id);
        $analogProducts = (new DetermineAnalogsAction())->execute($product);
        $matchDegrees = $this->calculateMatchDegrees($product, $analogProducts);

        return view('analogs', compact('product', 'analogProducts', 'matchDegrees'));
    }

    protected function calculateMatchDegrees(Product $product, $analogProducts)
    {
        $matchDegrees = [];

        foreach ($analogProducts as $analog) {
            $matchPercentage = 0;
            $parameters1 = json_decode($product->parameters, true);
            $parameters2 = json_decode($analog->parameters, true);

            $matchCount = 0;
            for ($i = 0; $i < min(count($parameters1), 3); $i++) {
                if ($parameters1[$i] == $parameters2[$i]) {
                    $matchCount++;
                }
            }
            $matchPercentage = ($matchCount / 3) * 100;

            $matchDegrees[$product->id][$analog->id] = $matchPercentage;
        }

        return $matchDegrees;
    }
    public function uploadCsv(Request $request)
    {
        $validated = $request->validate([
            'manufacturer' => 'required|string|max:255',
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $manufacturer = $request->input('manufacturer');
        $file = $request->file('csv_file');

        $manufacturerPath = storage_path('app/Imports/Products/' . $manufacturer);

        if (!File::exists($manufacturerPath)) {
            File::makeDirectory($manufacturerPath, 0777, true);
        }

        $filePath = $manufacturerPath . '/' . $file->getClientOriginalName();
        $file->move($manufacturerPath, $file->getClientOriginalName());

        Artisan::call('import:csv', ['filePath' => $filePath]);

        return back()->with('success', 'Файл успешно загружен и данные добавлены в базу!');
    }
}
