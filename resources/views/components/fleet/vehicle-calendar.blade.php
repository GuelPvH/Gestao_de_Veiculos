@props (['inicio', 'dias', 'leituras'])
<section class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h6 mb-0">{{ ucfirst($inicio->locale('pt_BR')->monthName) }} de {{ $inicio->year }}</h2>
            <nav class="d-flex gap-2" aria-label="Navegação do calendário">
                <a
                    class="btn btn-outline-secondary"
                    href="{{ route('agenda.index',['semana'=>$inicio->subMonth()->format('Y-m-d')]) }}"
                    aria-label="Mês anterior"
                    >‹</a
                >
                <a
                    class="btn btn-outline-secondary"
                    href="{{ route('agenda.index',['semana'=>$inicio->addMonth()->format('Y-m-d')]) }}"
                    aria-label="Próximo mês"
                    >›</a
                >
            </nav>
        </div>
        <div class="vehicle-calendar-grid">
            @foreach (['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'] as $rotulo)
                <span class="small text-body-secondary">{{ $rotulo }}</span>
            @endforeach
            @for ($i=1;$i<$inicio->dayOfWeekIso;$i++)
                <span aria-hidden="true"></span>
            @endfor
            @foreach ($dias as $dia)
                <div @class (['vehicle-calendar-day','has-reservations'=>$dia['reservas']->isNotEmpty()])>
                    <time datetime="{{ $dia['data']->format('Y-m-d') }}">{{ $dia['data']->day }}</time>
                    @foreach ($dia['reservas'] as $reserva)
                        <a
                            class="vehicle-calendar-reservation text-decoration-none"
                            href="{{ route('agenda.show', $reserva->id) }}"
                            ><span
                                >{{ \Carbon\CarbonImmutable::parse($reserva->inicio,'UTC')->setTimezone(config('fleet.timezone'))->format('H:i') }}</span
                            ><span>{{ $reserva->nome }}</span></a
                        >
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="d-flex gap-2 mt-3">
            @php ($primeiraReserva = collect($dias)->pluck('reservas')->flatten(1)->first())
            @if ($primeiraReserva)
                <a class="btn btn-primary" href="{{ route('agenda.show', $primeiraReserva->id) }}">Ver reserva</a>
            @else
                <button class="btn btn-primary" type="button" disabled>Ver reserva</button>
            @endif
            <a class="btn btn-outline-secondary" href="{{ route('vehicles.index') }}">Frota</a>
        </div>
    </div>
</section>
