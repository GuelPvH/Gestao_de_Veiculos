<x-ui.panel titulo="Documentos do veículo (CRLV, seguro, licenciamento)">
    @if ($documentosVeiculo->isEmpty())
        <p class="text-body-secondary mb-3">Nenhum documento registrado para este veículo.</p>
    @else
        @php
            $substituidos = $documentosVeiculo->pluck('substitui_documento_id')->filter()->all();
            $hoje = now(config('fleet.timezone'))->toDateString();
        @endphp
        <div class="table-responsive mb-3" tabindex="0" role="region" aria-label="Documentos do veículo">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Tipo</th>
                        <th scope="col">Número / Exercício</th>
                        <th scope="col">Validade</th>
                        <th scope="col">Arquivo</th>
                        <th scope="col">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documentosVeiculo as $doc)
                        @php
                            $foiSubstituido = in_array($doc->id, $substituidos, true);
                            $vencido = $doc->vence_em !== null && $doc->vence_em < $hoje;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $doc->tipo }}</strong>
                                @if ($foiSubstituido)
                                    <span class="badge text-bg-secondary ms-1">Substituído</span>
                                @elseif ($vencido)
                                    <span class="badge text-bg-danger ms-1">Vencido</span>
                                @elseif ($doc->vence_em !== null)
                                    <span class="badge text-bg-success ms-1">Vigente</span>
                                @endif
                            </td>
                            <td>
                                {{ $doc->numero ?? '—' }}{{ $doc->exercicio ? ' · '.$doc->exercicio : '' }}
                                @if ($doc->substitui_documento_id)
                                    <div class="small text-body-secondary">
                                        Substitui #{{ $doc->substitui_documento_id }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($doc->emitido_em)
                                    Emissão: {{ $leituras->format($doc->emitido_em, 'date') }}<br
                                     />
                                @endif
                                Vencimento: {{ $leituras->format($doc->vence_em, 'date') }}
                            </td>
                            <td>{{ $doc->nome_original }}</td>
                            <td>
                                @if ($doc->situacao === 'disponivel')
                                    <a
                                        class="btn btn-outline-secondary btn-sm"
                                        href="{{ route('vehicles.documents.download', ['registro' => $registro->id, 'documento' => $doc->id]) }}"
                                        >Baixar</a
                                    >
                                @else
                                    <span class="text-body-secondary small">{{ $doc->situacao }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($podeGerenciarDocumentos)
        <hr class="my-3" />
        <h3 class="h6 mb-3">Registrar novo documento</h3>
        <form
            method="post"
            action="{{ route('vehicles.documents.store', $registro->id) }}"
            enctype="multipart/form-data"
        >
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <x-forms.field
                        nome="tipo"
                        rotulo="Tipo de documento"
                        :obrigatorio="true"
                        maxlength="60"
                        placeholder="CRLV, Seguro, Licenciamento..."
                    />
                </div>
                <div class="col-md-4">
                    <x-forms.field nome="numero" rotulo="Número" maxlength="80" />
                </div>
                <div class="col-md-4">
                    <x-forms.field
                        nome="exercicio"
                        rotulo="Exercício (ano)"
                        tipo="number"
                        min="1990"
                        :max="date('Y') + 2"
                    />
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <x-forms.field nome="emitido_em" rotulo="Data de emissão" tipo="date" />
                </div>
                <div class="col-md-4">
                    <x-forms.field nome="vence_em" rotulo="Data de vencimento" tipo="date" />
                </div>
                <div class="col-md-4">
                    <x-forms.field
                        nome="substitui_documento_id"
                        rotulo="Substitui versão anterior (opcional)"
                        tipo="select"
                        :opcoes="$documentosVeiculo->mapWithKeys(fn ($d) => [$d->id => '#'.$d->id.' · '.$d->tipo.($d->exercicio ? ' ('.$d->exercicio.')' : '')])->all()"
                        placeholder="Documento novo"
                    />
                </div>
            </div>
            <x-forms.field
                nome="arquivo"
                rotulo="Arquivo (PDF, PNG ou JPG, até 10 MB)"
                tipo="file"
                accept="application/pdf,image/png,image/jpeg"
                :obrigatorio="true"
            />
            <button type="submit" class="btn btn-primary">Anexar documento do veículo</button>
        </form>
    @endif
</x-ui.panel>
