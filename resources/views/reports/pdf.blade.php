<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de {{ $titulo }}</title>
    <style>
        @page {
            margin: 88px 34px 58px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #182b45;
            font-size: 9px;
            line-height: 1.45;
        }
        .header {
            position: fixed;
            top: -64px;
            left: 0;
            right: 0;
            border-bottom: 3px solid #153b66;
            padding-bottom: 12px;
        }
        .brand {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .brand-sub {
            color: #687b91;
            font-size: 8px;
            margin-top: 3px;
        }
        .ref {
            position: absolute;
            right: 0;
            top: 1px;
            text-align: right;
            color: #687b91;
        }
        .ref strong {
            color: #153b66;
        }
        .footer {
            position: fixed;
            bottom: -31px;
            left: 0;
            right: 0;
            border-top: 1px solid #d8e1eb;
            padding-top: 8px;
            color: #687b91;
            font-size: 8px;
        }
        .eyebrow {
            color: #60748d;
            font-size: 8px;
            letter-spacing: 1.4px;
            margin-top: 8px;
        }
        h1 {
            font-size: 25px;
            margin: 5px 0 4px;
            color: #153b66;
        }
        .lead {
            color: #687b91;
            margin: 0 0 20px;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            background: #f1f5fa;
        }
        .summary td {
            width: 33%;
            padding: 12px;
            vertical-align: top;
            border: 1px solid #e0e7ef;
        }
        .label {
            color: #687b91;
            font-size: 8px;
            margin-bottom: 4px;
        }
        .value {
            font-size: 11px;
            font-weight: bold;
        }
        .filters {
            border-left: 3px solid #b5c9df;
            padding: 8px 12px;
            margin-bottom: 20px;
            color: #465c76;
        }
        h2 {
            font-size: 12px;
            margin: 18px 0 8px;
        }
        .data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .data thead {
            display: table-header-group;
        }
        .data th {
            background: #153b66;
            color: white;
            padding: 9px 7px;
            text-align: left;
            font-size: 8px;
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }
        .data td {
            padding: 8px 7px;
            border-bottom: 1px solid #dfe7ef;
            vertical-align: top;
            word-wrap: break-word;
        }
        .data tr:nth-child(even) td {
            background: #f5f8fb;
        }
        .data tr {
            page-break-inside: avoid;
        }
        .number {
            color: #687b91;
        }
        .continued td {
            color: #52677e;
            border-top: 0;
        }
        .section-break {
            page-break-before: always;
        }
        .end {
            margin-top: 15px;
            font-size: 8px;
            color: #687b91;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">GESTÃO DE VEÍCULOS</div>
        <div class="brand-sub">Controle de frota e operações</div>
        <div class="ref">
            <strong>RELATÓRIO #{{ str_pad((string) $id, 6, '0', STR_PAD_LEFT) }}</strong><br>Emitido em {{ $emitido }}
        </div>
    </div>
    <div class="footer">Gestão de Veículos · {{ $titulo }} · Relatório #{{ $id }}</div>
    <div class="eyebrow">RELATÓRIO OPERACIONAL</div>
    <h1>{{ $titulo }}</h1>
    <p class="lead">Informações consolidadas da seleção confirmada para exportação.</p>
    <table class="summary">
        <tr>
            <td>
                <div class="label">REGISTROS</div>
                <div class="value">{{ number_format($total, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">UNIDADE DO EMISSOR</div>
                <div class="value">{{ $unidade }}</div>
            </td>
            <td>
                <div class="label">ALCANCE DA EXPORTAÇÃO</div>
                <div class="value">{{ $alcance }}</div>
            </td>
        </tr>
    </table>
    <div class="filters">
        <strong>Filtros aplicados</strong><br>
        Período: {{
            !empty($filtros['de'])
                ? \Carbon\CarbonImmutable::parse($filtros['de'])->format('d/m/Y')
                : 'Sem limite inicial'
        }} até {{
            !empty($filtros['ate'])
                ? \Carbon\CarbonImmutable::parse($filtros['ate'])->format('d/m/Y')
                : 'Sem limite final'
        }}<br>
        Busca: {{ $filtros['q'] ?? 'Todas as informações' }}<br>
        Emitido por: {{ $autor }} · Fuso horário: {{ $fuso }}
    </div>
    @foreach ($secoes as $secao)
        @if (!$loop->first)
            <div class="section-break"></div>
        @endif
        <h2>
            Detalhamento{{
                $secoes->count() > 1
                    ? ' · Grupo de campos ' . $loop->iteration . ' de ' . $secoes->count()
                    : ''
            }}
        </h2>
        <table class="data">
            <colgroup>
                <col style="width: 4%">
                @foreach ($secao['larguras'] as $largura)
                    <col style="width: {{ $largura }}%;">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    <th class="number" style="width: 4%">Nº</th>
                    @foreach ($secao['campos'] as $campo)
                        <th style="width: {{ $secao['larguras'][$loop->index] }}%;">{{ $campo->rotulo }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($secao['linhas'] as $linha)
                    <tr class="{{ $linha['continua'] ? 'continued' : '' }}">
                        <td class="number">{{ $linha['numero'] }}</td>
                        @foreach ($linha['valores'] as $valor)
                            <td>{{ $valor }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
    <p class="end">Fim do relatório · {{ $total }} registros · Dados referentes à seleção no momento da geração.</p>
</body>
</html>
