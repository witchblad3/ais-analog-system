<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная страница</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans antialiased">
<div class="container mx-auto px-4 py-8">

    <h1 class="text-3xl font-bold text-gray-800 mb-6">Список товаров</h1>
    <form action="{{ route('product.uploadCsv') }}" method="POST" enctype="multipart/form-data" class="mb-6 flex items-center gap-6">
        @csrf

        <div class="flex items-center gap-2">
            <label for="manufacturer" class="text-lg font-semibold">Производитель:</label>
            <input type="text" name="manufacturer" id="manufacturer" class="border border-gray-300 rounded px-3 py-2 w-48" required placeholder="Укажите производителя">
        </div>

        <div class="flex items-center gap-2">
            <label for="csv_file" class="text-lg font-semibold">Загрузить CSV файл:</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" class="border border-gray-300 rounded px-3 py-2 w-48" required>
        </div>

        <button type="submit" class="text-white bg-blue-600 px-6 py-2 rounded hover:bg-blue-700">Загрузить</button>
    </form>
    @if (session('success'))
        <div class="bg-green-500 text-white p-4 rounded mb-6">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-500 text-white p-4 rounded mb-6">
            {{ session('error') }}
        </div>
    @endif

    <form method="GET" class="mb-4 grid grid-cols-5 gap-4">
        <input type="text" name="name" value="{{ request('name') }}" class="border border-gray-300 rounded px-2 py-1" placeholder="Название">
        <input type="text" name="manufacturer" value="{{ request('manufacturer') }}" class="border border-gray-300 rounded px-2 py-1" placeholder="Производитель">
        <input type="text" name="parameters" value="{{ request('parameters') }}" class="border border-gray-300 rounded px-2 py-1" placeholder="Параметры">
        <input type="text" name="price" value="{{ request('price') }}" class="border border-gray-300 rounded px-2 py-1" placeholder="Цена">
        <div class="flex gap-2">
            <button type="submit" class="text-white bg-blue-600 px-3 py-1 rounded">Поиск</button>
            <a href="{{ route('product.home') }}" class="text-blue-600 border border-blue-600 px-3 py-1 rounded hover:bg-blue-50">Сброс</a>
        </div>
    </form>

    <table class="min-w-full bg-white rounded-lg shadow-lg mb-6">
        <thead class="bg-blue-600 text-white">
        <tr>
            <th class="px-4 py-2 text-left">
                <a href="{{ route('product.home', array_merge(request()->all(), ['sort_by' => 'name', 'sort_order' => request('sort_order') == 'asc' ? 'desc' : 'asc'])) }}">
                    Продукт для сравнения
                </a>
            </th>
            <th class="px-4 py-2 text-left">
                <a href="{{ route('product.home', array_merge(request()->all(), ['sort_by' => 'manufacturer', 'sort_order' => request('sort_order') == 'asc' ? 'desc' : 'asc'])) }}">
                    Производитель
                </a>
            </th>
            <th class="px-4 py-2 text-left">
                Параметры
            </th>
            <th class="px-4 py-2 text-left">
                Цена
            </th>
            <th class="px-4 py-2 text-left">Наименование аналога</th>
            <th class="px-4 py-2 text-left">Степень соответствия</th>
        </tr>
        </thead>
        <tbody>
        @forelse($products as $product)
            <tr class="border-t">
                <td class="px-4 py-2">{{ $product->name }}</td>
                <td class="px-4 py-2">{{ $product->manufacturer }}</td>
                <td class="px-4 py-2">
                    @if (is_array($product->parameters))
                        {{ implode(', ', $product->parameters) }}
                    @else
                        {{ $product->parameters }}
                    @endif
                </td>
                <td class="px-4 py-2">{{ $product->price . ' '  .  $product->currency }} </td>
                <td class="px-4 py-2">
                    @if(isset($analogProducts[$product->id]) && $analogProducts[$product->id]->count() > 0)
                        <a href="{{ route('product.analogs', $product->id) }}" class="text-white bg-blue-600 px-4 py-2 rounded hover:bg-blue-700">Посмотреть аналоги</a>
                    @else
                        <span class="text-gray-500">Нет аналогов</span>
                    @endif
                </td>
                <td class="px-4 py-2">
                    @if (isset($analogProducts[$product->id]) && $analogProducts[$product->id]->count() > 0)
                        @foreach($analogProducts[$product->id] as $analog)
                            <div>
                                @php
                                    $matchPercentage = $matchDegrees[$product->id][$analog->id] ?? 0;
                                @endphp
                                @if($matchPercentage >= 50)
                                    <span style="color: {{ $matchPercentage == 100 ? 'green' : ($matchPercentage >= 75 ? 'orange' : 'red') }}">
                                        {{ $matchPercentage }}%
                                    </span>
                                @else
                                    <span>—</span>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <span>Нет аналогов</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center px-4 py-2 text-gray-500">Нет продуктов</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <!-- Пагинация -->
    <div class="mt-6">
        {{ $products->appends(request()->query())->links('pagination::tailwind') }}
    </div>

</div>
</body>
</html>
