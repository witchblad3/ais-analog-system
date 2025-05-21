<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Поиск аналогов</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
    <style>
        .ui-autocomplete { max-height:200px; overflow-y:auto; z-index:1000; }
        .ui-autocomplete li { white-space:nowrap; }
        .bg-match-100 { background-color: #d1fae5; }
        .bg-match-75  { background-color: #fef3c7; }
        .bg-match-50  { background-color: #fee2e2; }
    </style>
</head>
<body class="bg-gray-100">

<div class="container mx-auto p-8">
    <h1 class="text-3xl font-bold mb-6">Поиск аналогов</h1>

    <div class="flex mb-4 space-x-2">
        <input id="search" type="text"
               placeholder="Введите название изделия..."
               class="flex-grow border rounded px-4 py-2 focus:outline-none"
               autocomplete="off">
        <button id="search-btn"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 rounded">🔍</button>
        <button id="reset-btn"
                class="bg-gray-400 hover:bg-gray-500 text-white px-4 rounded">⟳</button>
    </div>

    <div class="mb-4 flex items-center justify-between">
        <div>
            <label class="mr-2 font-medium">На странице:</label>
            <select id="per-page" class="border rounded px-2 py-1">
                <option>10</option>
                <option>20</option>
                <option>30</option>
                <option>50</option>
            </select>
        </div>
        <div id="pagination" class="flex space-x-2"></div>
    </div>

    <div id="compare-container" class="overflow-x-auto hidden">
        <table id="compare-table" class="table-auto w-full border border-gray-300 bg-white rounded shadow">
            <thead id="compare-head" class="sticky top-0 bg-gray-200 text-gray-700 text-sm font-semibold"></thead>
            <tbody id="compare-body" class="text-gray-800"></tbody>
            <tfoot id="compare-foot" class="bg-gray-100 text-gray-900 font-semibold"></tfoot>
        </table>
    </div>
</div>

<script>
    $(function(){
        let base, analogs;

        $("#search").autocomplete({
            source(req, rsp){
                $.getJSON("{{ route('product.suggestions') }}", {
                    term: req.term, limit: 20
                }, rsp);
            },
            minLength: 2,
            select(_, ui){
                $("#search").val(ui.item.value);
                loadData();
            }
        });
        $("#search-btn").click(loadData);
        $("#per-page").change(loadData);

        function loadData(){
            const name = $("#search").val().trim();
            if (!name) return;
            $.get("{{ route('api.analogs') }}", { name })
                .done(json => {
                    base = json.base;
                    analogs = json.analogs
                        .map(a => ({ ...a, match_percent: Math.min(a.match_percent,100) }))
                        .filter(a => a.match_percent >= 50);
                    renderTable();
                });
        }

        function renderTable(){
            $("#compare-head, #compare-body, #compare-foot").empty();
            $("#compare-container").addClass('hidden');

            $("#reset-btn").click(() => {
                $("#search").val('');
                $("#compare-head, #compare-body, #compare-foot, #pagination").empty();
                $("#compare-container").addClass('hidden');
            });

            if (!analogs.length) {
                $("#compare-container").removeClass('hidden');
                $("#compare-head")
                    .append('<tr><th class="px-4 py-2 text-center">Совпадений не найдено</th></tr>');
                return;
            }

            const keys = new Set(Object.keys(base.parameters));
            analogs.forEach(a => Object.keys(a.parameters).forEach(k=>keys.add(k)));

            let head = '<tr>';
            head += '<th class="border-b px-4 py-2">Производитель</th>';
            head += `<th class="border-b px-4 py-2">${base.source_site}</th>`;
            analogs.forEach(a => head += `<th class="border-b px-4 py-2">${a.source_site}</th>`);
            head += '</tr>';
            $("#compare-head").append(head);

            addRow('Продукт для сравнения', [base.name].concat(analogs.map(()=>'')));
            addRow('Наименование аналога', [''].concat(
                analogs.map(a => a.external_id!==a.name
                    ? `${a.name}` : a.name
                )
            ));
            addRow('Степень соответствия', [''].concat(analogs.map(a=>a.match_percent+'%')));

            [...keys].forEach(key => {
                addRow(key,
                    [base.parameters[key]||'']
                        .concat(analogs.map(a=>a.parameters[key]||''))
                );
            });

            // TFOOT
            let foot = '<tr>';
            foot += '<th class="border-t px-4 py-2">Стоимость</th>';
            foot += `<th class="border-t px-4 py-2">${base.price}</th>`;
            analogs.forEach(a => foot += `<th class="border-t px-4 py-2">${a.price}</th>`);
            foot += '</tr>';
            $("#compare-foot").append(foot);

            // Цвет колонок аналогов
            analogs.forEach((a, idx)=>{
                const cls = a.match_percent===100
                    ? 'bg-match-100' : a.match_percent>=75
                        ? 'bg-match-75' : 'bg-match-50';
                $("#compare-table tr").each((_,tr)=>{
                    $(tr).children().eq(idx+2).addClass(cls);
                });
            });

            $("#compare-container").removeClass('hidden');
            paginate();
        }

        function addRow(label, values){
            let tr = `<tr class="even:bg-gray-50 odd:bg-white">` +
                `<td class="font-medium text-left px-4 py-2 border-r">${label}</td>`;
            values.forEach(v => {
                tr += `<td class="px-4 py-2 border-r">${v}</td>`;
            });
            tr += '</tr>';
            $("#compare-body").append(tr);
        }

        function paginate(){
            const perPage = +$("#per-page").val();
            const rows = $("#compare-body tr");
            const pages = Math.ceil(rows.length / perPage);
            let html = '';
            for(let i=1;i<=pages;i++){
                html += `<button class="px-3 py-1 border rounded ${i===1?'bg-blue-600 text-white':''}" data-page="${i}">${i}</button>`;
            }
            $("#pagination").html(html)
                .off('click')
                .on('click','button', e=>{
                    const page = +e.currentTarget.dataset.page;
                    $("#pagination button").removeClass('bg-blue-600 text-white');
                    $(e.currentTarget).addClass('bg-blue-600 text-white');
                    const start = (page-1)*perPage, end = start+perPage;
                    rows.each((i, tr)=> $(tr).toggle(i>=start && i<end));
                });
            // показать первую
            rows.each((i,tr)=> $(tr).toggle(i<perPage));
        }
    });
</script>
</body>
</html>
