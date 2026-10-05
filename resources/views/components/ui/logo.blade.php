<span {{ $attributes->class(['fleet-logo']) }} role="img" aria-label="Frota Pública RO — Gestão de veículos — Rondônia">
    <span class="logo-visible">
        <span class="logo-original" aria-hidden="true">
            @for ($parte = 0; $parte < 16; $parte++)
                <img src="{{ asset('images/logo/logo-strip-'.str_pad((string) $parte, 2, '0', STR_PAD_LEFT).'.png') }}" alt="" class="logo-strip logo-strip-{{ $parte }}" />
            @endfor
        </span>
    </span>
</span>
