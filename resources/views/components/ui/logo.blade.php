@props ([
    'variante' => 'horizontal',
    'superficie' => 'padrao',
    'entrada' => false,
    'interativo' => true,
])
@php
    $nomeSistema = $sistemaConfig->nome_sistema ?? 'Frota · PF';
    $temLogoPersonalizado = ! empty($sistemaConfig->logo_arquivo_id) && Route::has('configuration.logo');
    $variantesPermitidas = ['horizontal', 'horizontal-sem-subtitulo', 'empilhada', 'icone'];
    $varianteSegura = in_array($variante, $variantesPermitidas, true) ? $variante : 'horizontal';
    $superficieSegura = in_array($superficie, ['padrao', 'azul'], true) ? $superficie : 'padrao';
    [$largura, $altura] = match ($varianteSegura) {
        'empilhada' => [432, 368],
        'icone' => [128, 128],
        default => [744, 176],
    };
    $caminhosAnimados = ! $temLogoPersonalizado && in_array($varianteSegura, ['horizontal', 'horizontal-sem-subtitulo'], true)
        ? \App\Support\BrandLogo::horizontalPaths()
        : ['logotipo' => null, 'subtitulo' => null];
    $mostrarSubtitulo = $varianteSegura === 'horizontal';
    $usarSvgAnimado = $caminhosAnimados['logotipo'] !== null
        && (! $mostrarSubtitulo || $caminhosAnimados['subtitulo'] !== null);
    $uid = $usarSvgAnimado ? 'fp-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)) : null;
    $srcClaro = $superficieSegura === 'azul'
        ? asset('images/logo/'.$varianteSegura.'-branca.svg')
        : asset('images/logo/'.$varianteSegura.'-claro.svg');
    $srcNoturno = asset('images/logo/'.$varianteSegura.'-noturno.svg');
@endphp
<span
    {{ $attributes->class([
        'fleet-logo',
        'fleet-logo--'.$varianteSegura,
        'fleet-logo--superficie-'.$superficieSegura,
        'fleet-logo--custom' => $temLogoPersonalizado,
    ]) }}
>
    <span class="logo-visible">
        @if ($temLogoPersonalizado)
            <img
                src="{{ route('configuration.logo') }}"
                alt="{{ $nomeSistema }}"
                width="200"
                height="45"
                decoding="async"
                class="logo-image logo-image-custom"
            />
        @elseif ($usarSvgAnimado)
            @if ($interativo)
                <button type="button" class="logo-trigger" aria-label="{{ $nomeSistema }} — Gestão de veículos">
            @endif
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="marca-frota frota-motion"
                width="744"
                height="176"
                viewBox="0 0 744 176"
                role="img"
                aria-busy="false"
                aria-labelledby="{{ $uid }}-titulo {{ $uid }}-descricao"
                data-state="idle"
                @if ($entrada) data-motion-intro @endif
            >
                <title id="{{ $uid }}-titulo">{{ $nomeSistema }}</title>
                <desc id="{{ $uid }}-descricao">
                    Gestão de veículos · Rondônia. Pin de localização integrado a um veículo.
                </desc>
                <g transform="translate(16 21)">
                    <defs>
                        <clipPath id="{{ $uid }}-road-clip" clipPathUnits="userSpaceOnUse">
                            <rect x="58" y="80" width="12" height="30" />
                        </clipPath>
                    </defs>
                    <g class="mf-hover">
                        <g class="mf-pulse">
                            <g class="mf-pin-fill">
                                <path
                                    fill="rgb(11, 60, 140)"
                                    style="fill: var(--marca-pin)"
                                    d="M64 8C37.5 8 16 28.8 16 54.6C16 84.5 64 122 64 122S112 84.5 112 54.6C112 28.8 90.5 8 64 8Z"
                                />
                            </g>
                            <g class="mf-car">
                                <path
                                    fill="rgb(255, 255, 255)"
                                    style="fill: var(--marca-carro)"
                                    d="M35 51L40 36Q42 30 48 30H80Q86 30 88 36L93 51V71Q93 76 88 76H40Q35 76 35 71Z"
                                />
                                <path
                                    fill="rgb(30, 136, 229)"
                                    style="fill: var(--marca-vidro)"
                                    d="M45 49L49 39H79L83 49Z"
                                />
                                <g>
                                    <rect
                                        fill="rgb(11, 60, 140)"
                                        style="fill: var(--marca-farol)"
                                        x="43"
                                        y="57"
                                        width="10"
                                        height="5"
                                        rx="1.5"
                                    />
                                    <rect
                                        fill="rgb(11, 60, 140)"
                                        style="fill: var(--marca-farol)"
                                        x="75"
                                        y="57"
                                        width="10"
                                        height="5"
                                        rx="1.5"
                                    />
                                </g>
                                <rect
                                    fill="rgb(46, 158, 79)"
                                    style="fill: var(--marca-detalhe)"
                                    x="56"
                                    y="67"
                                    width="16"
                                    height="4"
                                    rx="1"
                                />
                            </g>
                            <g class="mf-lanes" clip-path="url(#{{ $uid }}-road-clip)">
                                <rect
                                    fill="rgb(249, 200, 14)"
                                    style="fill: var(--marca-rota)"
                                    x="61"
                                    y="88"
                                    width="6"
                                    height="14"
                                    rx="2"
                                    class="mf-lane mf-lane-main"
                                />
                                <rect
                                    fill="rgb(249, 200, 14)"
                                    style="fill: var(--marca-rota)"
                                    x="61"
                                    y="88"
                                    width="6"
                                    height="14"
                                    rx="2"
                                    class="mf-lane mf-lane-copy"
                                />
                            </g>
                            <g aria-hidden="true">
                                <path
                                    class="mf-trace mf-trace-pin"
                                    pathLength="1"
                                    d="M64 8C37.5 8 16 28.8 16 54.6C16 84.5 64 122 64 122S112 84.5 112 54.6C112 28.8 90.5 8 64 8Z"
                                />
                                <path
                                    class="mf-trace mf-trace-car"
                                    pathLength="1"
                                    d="M35 51L40 36Q42 30 48 30H80Q86 30 88 36L93 51V71Q93 76 88 76H40Q35 76 35 71Z"
                                />
                                <path class="mf-trace mf-trace-road" pathLength="1" d="M64 88V102" />
                            </g>
                        </g>
                        <circle class="mf-ripple" cx="64" cy="72" r="45" aria-hidden="true" />
                    </g>
                </g>
                <g @if (! $mostrarSubtitulo) transform="translate(0 16)" @endif>
                    <g class="mf-name">
                        <path
                            fill="rgb(11, 60, 140)"
                            style="fill: var(--marca-texto)"
                            d="{{ $caminhosAnimados['logotipo'] }}"
                        />
                    </g>
                </g>
                @if ($mostrarSubtitulo)
                    <g class="mf-subtitle">
                        <path
                            fill="rgb(11, 60, 140)"
                            style="fill: var(--marca-texto)"
                            d="{{ $caminhosAnimados['subtitulo'] }}"
                        />
                    </g>
                @endif
            </svg>
            @if ($interativo)
                </button>
            @endif
        @else
            <img
                src="{{ $srcClaro }}"
                alt="{{ $nomeSistema }} — Gestão de veículos"
                width="{{ $largura }}"
                height="{{ $altura }}"
                decoding="async"
                class="logo-image logo-image-light"
            />
            <img
                src="{{ $srcNoturno }}"
                alt="{{ $nomeSistema }} — Gestão de veículos"
                width="{{ $largura }}"
                height="{{ $altura }}"
                decoding="async"
                class="logo-image logo-image-dark"
            />
        @endif
    </span>
</span>
