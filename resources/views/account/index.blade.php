<x-layouts.authenticated titulo="Conta e aparência" :breadcrumbs="[['label' => 'Conta e aparência']]">
    <x-ui.title titulo="Conta e aparência" subtitulo="Sua identidade, segurança e preferência visual." />
    <div class="dashboard-grid">
        <x-ui.panel titulo="Dados da conta">
            <dl class="detail-grid">
                <div>
                    <dt>Nome</dt>
                    <dd>{{ $usuario->nome }}</dd>
                </div>
                <div>
                    <dt>Identificador</dt>
                    <dd>{{ $usuario->identificador }}</dd>
                </div>
                <div>
                    <dt>E-mail</dt>
                    <dd>{{ $usuario->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Perfil atual</dt>
                    <dd>{{ $vinculoAtual->perfil_nome }}</dd>
                </div>
            </dl>
            <a class="btn btn-outline-secondary" href="{{ route('password.edit') }}">Alterar senha</a></x-ui.panel
        >
        <x-ui.panel titulo="Aparência">
            <label for="theme-choice" class="form-label">Tema neste dispositivo</label
            ><select class="form-select" id="theme-choice" data-theme-select>
                <option value="system">Preferência do dispositivo</option>
                <option value="light">Claro</option>
                <option value="dark">Escuro</option>
            </select>
            <p class="form-text">A preferência é aplicada imediatamente neste navegador.</p></x-ui.panel
        >
    </div>
    <x-ui.panel titulo="Segurança do e-mail">
        @if (session('status'))
            <x-ui.alert>{{ session('status') }}</x-ui.alert>
        @endif
        @if ($errors->any())
            <x-ui.alert tom="danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach</ul
            ></x-ui.alert>
        @endif
        <p>{{
            $usuario->email_verificado_em
                ? 'Seu endereço atual foi confirmado.'
                : 'Seu endereço atual ainda precisa ser confirmado.'
        }}</p>
        @if ($usuario->email_pendente)
            <p>Endereço aguardando confirmação: {{ $usuario->email_pendente }}.</p>
        @endif
        @if (app(\App\Services\Auth\RecoverySettings::class)->available('email'))
            @if ($usuario->email_pendente || !$usuario->email_verificado_em)
                <form action="{{ route('identity.resend') }}" method="post">
                    @csrf
                    <button class="btn btn-outline-primary" type="submit">Enviar confirmação</button>
                </form>
            @endif
            <form action="{{ route('identity.changeEmail') }}" method="post" class="mt-3" data-loading-form>
                @csrf
                <label for="account-email" class="form-label">Novo e-mail</label>
                <input
                    id="account-email"
                    class="form-control"
                    type="email"
                    name="email"
                    maxlength="254"
                    autocomplete="email"
                    value="{{ old('email', $usuario->email_pendente ?: $usuario->email) }}"
                    required
                >
                <x-forms.password nome="senha_atual" rotulo="Senha atual" autocomplete="current-password" />
                <p class="form-text">Confirme o novo endereço pelo link individual. Esta alteração encerra suas sessões atuais.</p>
                <button class="btn btn-primary" type="submit">Solicitar alteração de e-mail</button><x-ui.loading />
            </form>
        @else
            <p>A confirmação por e-mail está indisponível no momento. Solicite a revisão à administração.</p>
        @endif
    </x-ui.panel>
    <x-ui.panel titulo="Sessões da sua conta">
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabela com rolagem">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Criada</th>
                        <th scope="col">Última atividade</th>
                        <th scope="col">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sessoes as $sessao)
                        <tr>
                            <td>{{ $leituras->format($sessao->criado_em, 'datetime') }}</td>
                            <td>
                                {{
                                    $leituras->format(
                                        $sessao->ultima_atividade_em,
                                        'datetime',
                                    )
                                }}
                            </td>
                            <td>
                                {{
                                    $sessao->encerrada_em
                                        ? 'Encerrada · ' . str_replace('_', ' ', $sessao->motivo_encerramento)
                                        : (\Carbon\CarbonImmutable::parse($sessao->expira_em, 'UTC')->isPast()
                                            ? 'Prazo encerrado'
                                            : ($sessao->id === session('fleet_session.id')
                                                ? 'Sessão atual'
                                                : 'Aberta'))
                                }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div
    ></x-ui.panel>
</x-layouts.authenticated>
