<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналоги для {{ $product->name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans antialiased">
<div class="container mx-auto px-4 py-8">

    <h1 class="text-3xl font-bold text-gray-800 mb-6">Аналоги для: {{ $product->name }}</h1>

    <a href="{{ route('product.home') }}" class="text-blue-600 border border-blue-600 px-3 py-1 rounded hover:bg-blue-50 mb-4 inline-block">Вернуться к продуктам</a>

    <table class="min-w-full bg-white rounded-lg shadow-lg mb-6">
        <thead class="bg-blue-600 text-white">
        <tr>
            <th class="px-4 py-2 text-left">Наименование аналога</th>
            <th class="px-4 py-2 text-left">Производитель</th>
            <th class="px-4 py-2 text-left">Параметры</th>
            <th class="px-4 py-2 text-left">Цена</th>
            <th class="px-4 py-2 text-left">Степень соответствия</th>
        </tr>
        </thead>
        <tbody>
        @forelse($analogProducts as $analog)
            <tr class="border-t">
                <td class="px-4 py-2">{{ $analog->name }}</td>
                <td class="px-4 py-2">{{ $analog->manufacturer }}</td>
                <td class="px-4 py-2">
                    @if (is_array($analog->parameters))
                        {{ implode(', ', $analog->parameters) }}
                    @else
                        {{ $analog->parameters }}
                    @endif
                </td>
                <td class="px-4 py-2">{{ $analog->price }} {{ $analog->currency }}</td>
                <td class="px-4 py-2">
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
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center px-4 py-2 text-gray-500">Нет аналогов для этого продукта</td>
            </tr>
        @endforelse
        </tbody>
    </table>

</div>
</body>
</html>
