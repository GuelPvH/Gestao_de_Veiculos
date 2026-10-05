<x-layouts.authenticated :titulo="$record->codigo.' · Pneu'" :breadcrumbs="[['label'=>'Pneus','href'=>route('tyres.index')],['label'=>$record->codigo]]">
    <x-ui.title :titulo="$record->codigo" subtitulo="Histórico de instalação e situação do pneu.">
        <x-slot:acoes><a class="btn btn-outline-secondary" href="{{ route('tyres.index') }}">Voltar</a></x-slot:acoes>
    </x-ui.title>
    <div class="dashboard-grid"><div class="stack">
        <x-ui.panel titulo="Dados do pneu"><dl class="detail-grid mb-0">
            <div><dt>Medida</dt><dd>{{ $record->medida }}</dd></div><div><dt>Marca e modelo</dt><dd>{{ trim(($record->marca ?? '').' '.($record->modelo ?? '')) ?: '—' }}</dd></div>
            <div><dt>Número de série</dt><dd>{{ $record->numero_serie ?? '—' }}</dd></div><div><dt>Situação</dt><dd><x-ui.status :valor="$record->situacao" /></dd></div>
            <div><dt>Despesa de aquisição</dt><dd>{{ $record->despesa_protocolo }}</dd></div>
        </dl></x-ui.panel>
        <x-ui.panel titulo="Instalações"><div class="table-responsive" tabindex="0" role="region" aria-label="Histórico de instalações"><table class="table">
            <thead><tr><th scope="col">Veículo</th><th scope="col">Posição</th><th scope="col">Instalado em</th><th scope="col">Removido em</th><th scope="col">Ação</th></tr></thead>
            <tbody>@forelse($installations as $installation)<tr>
                <td>{{ $installation->placa }}</td><td>{{ $installation->posicao }}</td><td>{{ \Carbon\CarbonImmutable::parse($installation->instalado_em,'UTC')->setTimezone(config('fleet.timezone'))->format('d/m/Y H:i') }}</td>
                <td>{{ $installation->removido_em ? \Carbon\CarbonImmutable::parse($installation->removido_em,'UTC')->setTimezone(config('fleet.timezone'))->format('d/m/Y H:i') : '—' }}</td>
                <td>@if($canEdit && $installation->removido_em === null)<a href="#remover-pneu">Registrar remoção</a>@else—@endif</td>
            </tr>@empty<tr><td colspan="5">Sem instalações registradas.</td></tr>@endforelse</tbody>
        </table></div></x-ui.panel>
    </div><div class="stack">
        @if($canEdit && $record->situacao === 'estoque')
            <x-ui.panel titulo="Instalar em veículo"><form method="post" action="{{ route('tyres.install', $record->id) }}">@csrf
                <x-forms.field nome="veiculo" rotulo="Placa do veículo" :obrigatorio="true" maxlength="20" />
                <x-forms.field nome="posicao" rotulo="Posição" :obrigatorio="true" maxlength="40" />
                <x-forms.field nome="instalado_em" rotulo="Data e hora da instalação" tipo="datetime-local" :obrigatorio="true" />
                <x-forms.field nome="quilometragem_instalacao" rotulo="Quilometragem" tipo="number" :obrigatorio="true" min="0" step="0.1" />
                <x-forms.field nome="manutencao_id" rotulo="ID da manutenção relacionada" tipo="number" min="1" />
                <button class="btn btn-primary" type="submit">Registrar instalação</button>
            </form></x-ui.panel>
        @endif
        @if($canEdit && $record->situacao === 'instalado')
            @php($active = $installations->firstWhere('removido_em', null))
            @if($active)<x-ui.panel titulo="Remover do veículo"><form id="remover-pneu" method="post" action="{{ route('tyres.remove', ['registro'=>$record->id,'instalacao'=>$active->id]) }}">@csrf
                <x-forms.field nome="removido_em" rotulo="Data e hora da remoção" tipo="datetime-local" :obrigatorio="true" />
                <x-forms.field nome="quilometragem_remocao" rotulo="Quilometragem" tipo="number" :obrigatorio="true" :min="$active->quilometragem_instalacao" step="0.1" />
                <x-forms.field nome="motivo_remocao" rotulo="Motivo" tipo="textarea" :obrigatorio="true" maxlength="500" />
                <button class="btn btn-primary" type="submit">Registrar remoção</button>
            </form></x-ui.panel>@endif
        @endif
        @if($canDiscard && $record->situacao === 'estoque')
            <x-ui.panel titulo="Descartar pneu"><form method="post" action="{{ route('tyres.discard', $record->id) }}">@csrf
                <x-forms.field nome="justificativa" rotulo="Justificativa do descarte" tipo="textarea" :obrigatorio="true" maxlength="500" />
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmar" value="1" id="tyre-confirmar" required><label class="form-check-label" for="tyre-confirmar">Confirmo o descarte deste pneu.</label></div>
                <button class="btn btn-outline-danger" type="submit">Descartar pneu</button>
            </form></x-ui.panel>
        @endif
    </div></div>
</x-layouts.authenticated>
