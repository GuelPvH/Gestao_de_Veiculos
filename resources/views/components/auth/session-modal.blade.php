@php
    $fleetSession = request()->attributes->get('fleet.session');
    $initialState = $fleetSession instanceof \stdClass ? ['state' => 'ACTIVE', 'server_time' => \Carbon\CarbonImmutable::now('UTC')->toIso8601String(), 'expires_at' => app(\App\Services\Auth\FleetSession::class)->deadline($fleetSession)->toIso8601String()] : null;
    $sessionScope = $fleetSession instanceof \stdClass ? hash_hmac('sha256', (string) $fleetSession->id, (string) config('app.key')) : '';
@endphp
<div
    id="fleet-session-warning"
    class="modal fade"
    tabindex="-1"
    aria-labelledby="fleet-session-title"
    aria-describedby="fleet-session-description"
    data-bs-backdrop="static"
    data-bs-keyboard="false"
    data-session-status="{{ route('session.status') }}"
    data-session-login="{{ route('login') }}"
    data-session-mfa="{{ route('mfa.index') }}"
    data-session-scope="{{ $sessionScope }}"
    data-session-initial="{{ $initialState ? json_encode($initialState) : '' }}"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="fleet-session-title">Confirme sua senha para continuar</h2>
            </div>
            <div class="modal-body">
                <p id="fleet-session-description">Sua sessão termina em <strong data-session-countdown>1 minuto</strong>. Confirme sua senha antes do prazo.</p>
                <p class="small text-body-secondary">Ao sair, alterações ainda não enviadas serão descartadas.</p>
                <form id="fleet-session-continue" action="{{ route('session.reauthenticate') }}" method="post">
                    @csrf
                    <label for="fleet-session-password" class="form-label">Senha atual</label>
                    <input
                        id="fleet-session-password"
                        type="password"
                        name="senha"
                        class="form-control"
                        autocomplete="current-password"
                        required
                        maxlength="1024"
                    />
                    <p class="text-danger mt-2" data-session-error role="alert" hidden></p>
                </form>
            </div>
            <div class="modal-footer">
                <form action="{{ route('logout') }}" method="post" data-session-logout>
                    @csrf
                    <button class="btn btn-outline-secondary" type="submit">Sair</button>
                </form>
                <button type="submit" class="btn btn-primary" form="fleet-session-continue">Continuar</button>
            </div>
        </div>
    </div>
</div>
