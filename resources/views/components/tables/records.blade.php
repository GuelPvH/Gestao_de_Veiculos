@props(['colunas', 'registros', 'rotaDetalhe'])
@if ($registros->isEmpty())
    <x-ui.empty />
@else
    <div class="table-responsive d-none d-md-block" tabindex="0" role="region" aria-label="Registros com rolagem">
        <table class="table align-middle mb-0"><thead><tr>
            @foreach ($colunas as $rotulo)
                <th scope="col">{{ $rotulo }}</th>
            @endforeach
            <th scope="col"><span class="visually-hidden">Ações</span></th>
        </tr></thead><tbody>
            @foreach ($registros as $registro)
                <tr>
                    @foreach ($colunas as $chave => $rotulo)
                        <td>@if($chave === 'situacao') <x-ui.status :valor="$registro['valores'][$chave]" /> @else {{ $registro['valores'][$chave] ?? '—' }} @endif</td>
                    @endforeach
                    <td><a class="btn btn-outline-secondary btn-sm" href="{{ route($rotaDetalhe, $registro['id']) }}">Detalhes<span class="visually-hidden"> do registro {{ $registro['id'] }}</span></a></td>
                </tr>
            @endforeach
        </tbody></table>
    </div>
    <div class="mobile-records d-md-none">
        @foreach ($registros as $registro)
            <article class="card mb-3"><div class="card-body"><dl class="detail-grid mb-3">
                @foreach ($colunas as $chave => $rotulo)
                    <div><dt>{{ $rotulo }}</dt><dd>@if($chave === 'situacao') <x-ui.status :valor="$registro['valores'][$chave]" /> @else {{ $registro['valores'][$chave] ?? '—' }} @endif</dd></div>
                @endforeach
            </dl><a class="btn btn-outline-secondary w-100" href="{{ route($rotaDetalhe, $registro['id']) }}">Ver detalhes<span class="visually-hidden"> do registro {{ $registro['id'] }}</span></a></div></article>
        @endforeach
    </div>
@endif
