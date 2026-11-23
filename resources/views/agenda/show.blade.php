<x-layouts.authenticated
    :titulo="$titulo"
    :breadcrumbs="[['label'=>'Detalhes', 'href'=>route('agenda.index')], ['label'=>$titulo]]"
>
    <x-ui.title :titulo="$titulo" subtitulo="Acompanhe a operação e o que precisa da sua atenção." />
    <x-ui.panel titulo="Detalhes do registro">
        <dl class="detail-grid mb-3">
            <div>
                <dt>Tipo de reserva</dt>
                <dd>
                    {{ match ($registro->tipo) { 'viagem' => 'Viagem', 'manutencao' => 'Manutenção', default => 'Indisponibilidade' } }}
                </dd>
            </div>
            <div>
                <dt>Veículo</dt>
                <dd>
                    <a href="{{ route('vehicles.show', $registro->veiculo_id) }}"
                        >{{ $registro->veiculo }} · {{ $registro->placa }}</a
                    >
                </dd>
            </div>
            <div>
                <dt>Início</dt>
                <dd>{{ $leituras->format($registro->inicio, 'datetime') }}</dd>
            </div>
            <div>
                <dt>Término</dt>
                <dd>{{ $leituras->format($registro->fim, 'datetime') }}</dd>
            </div>
            @if ($registro->tipo === 'viagem')
                <div>
                    <dt>Solicitante</dt>
                    <dd>{{ $registro->solicitante ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Motorista</dt>
                    <dd>{{ $registro->motorista ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Origem</dt>
                    <dd>{{ $registro->origem ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Destino</dt>
                    <dd>{{ $registro->destino ?? '—' }}</dd>
                </div>
            @endif
            @if (! empty($registro->manutencao_protocolo))
                <div>
                    <dt>Ordem de manutenção</dt>
                    <dd>{{ $registro->manutencao_protocolo }}</dd>
                </div>
            @endif
            <div>
                <dt>
                    {{ $registro->tipo === 'viagem' ? 'Observação / Justificativa' : 'Motivo do bloqueio / manutenção' }}
                </dt>
                <dd>{{ $registro->descricao ?? '—' }}</dd>
            </div>
        </dl>
        @if ($registro->tipo === 'viagem')
            <x-fleet.reservation-map :registro="$registro" />
        @endif
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a
                class="btn btn-primary"
                href="{{ route('agenda.index', ['semana'=>\Carbon\CarbonImmutable::parse($registro->inicio, 'UTC')->setTimezone(config('fleet.timezone'))->format('Y-m-d')]) }}"
                >Voltar</a
            >
            @if (! empty($registro->solicitacao_id) && $acesso->level('solicitacoes') > 0)
                <a class="btn btn-outline-secondary" href="{{ route('solicitacoes.show', $registro->solicitacao_id) }}"
                    >Ver solicitação</a
                >
            @endif
            @if (! empty($registro->viagem_id) && $acesso->level('viagens') > 0)
                <a class="btn btn-outline-secondary" href="{{ route('trips.show', $registro->viagem_id) }}"
                    >Ver viagem</a
                >
            @endif
            @if (! empty($registro->manutencao_id) && $acesso->level('despesas') > 0)
                <a class="btn btn-outline-secondary" href="{{ route('maintenance.show', $registro->manutencao_id) }}"
                    >Ver manutenção</a
                >
            @endif
            @if ($acesso->level('rastreamento') && $acesso->level('rastreamento', 'ver_localizacao'))
                <a class="btn btn-outline-secondary" href="{{ route('history.index') }}">Histórico de trajetos</a>
            @endif
        </div>
    </x-ui.panel>
</x-layouts.authenticated>
