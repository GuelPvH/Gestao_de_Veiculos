@props (['nome', 'rotulo', 'valor' => '', 'latitude' => '', 'longitude' => ''])
<div class="location-picker mb-3" data-location-picker data-search-url="{{ config('geocoding.search_url') }}">
    <x-forms.field
        :nome="$nome"
        :rotulo="$rotulo"
        :valor="$valor"
        :obrigatorio="true"
        maxlength="255"
        autocomplete="off"
        data-location-input
        role="combobox"
        aria-autocomplete="list"
        aria-expanded="false"
        :aria-controls="'location-results-'.$nome"
        nota="Digite pelo menos 3 caracteres e selecione um local. Busca gratuita pelo Photon/OpenStreetMap, somente em Porto Velho."
    />
    <div
        id="location-results-{{ $nome }}"
        class="list-group mb-2"
        role="listbox"
        aria-label="Sugestões de locais"
        data-location-results
        hidden
    ></div>
    <p class="small text-body-secondary mb-2" data-location-status role="status"></p>
    <input
        type="hidden"
        name="{{ $nome }}_latitude"
        value="{{ old($nome.'_latitude', $latitude) }}"
        data-location-latitude
    />
    <input
        type="hidden"
        name="{{ $nome }}_longitude"
        value="{{ old($nome.'_longitude', $longitude) }}"
        data-location-longitude
    />
</div>
@once
    <script>
        (() => {
            const iniciar = () => {
                for (const picker of document.querySelectorAll('[data-location-picker]')) {
                    if (picker.dataset.initialized) continue;
                    picker.dataset.initialized = 'true';
                    const input = picker.querySelector('[data-location-input]'),
                        lat = picker.querySelector('[data-location-latitude]'),
                        lon = picker.querySelector('[data-location-longitude]');
                    const results = picker.querySelector('[data-location-results]'),
                        status = picker.querySelector('[data-location-status]');
                    let timer,
                        controller,
                        generation = 0;
                    const valid = () =>
                        input.setCustomValidity(
                            lat.value !== '' && lon.value !== ''
                                ? ''
                                : 'Selecione um local nas sugestões para gravar suas coordenadas.',
                        );
                    const close = () => {
                        results.hidden = true;
                        input.setAttribute('aria-expanded', 'false');
                    };
                    valid();
                    if (lat.value !== '' && lon.value !== '') status.textContent = 'Local selecionado.';
                    input.addEventListener('input', () => {
                        clearTimeout(timer);
                        controller?.abort();
                        generation++;
                        lat.value = '';
                        lon.value = '';
                        valid();
                        close();
                        results.replaceChildren();
                        const q = input.value.trim(),
                            requestGeneration = generation;
                        if (q.length < 3) {
                            status.textContent = 'Digite pelo menos 3 caracteres.';
                            return;
                        }
                        status.textContent = 'Buscando locais…';
                        timer = setTimeout(async () => {
                            const currentController = new AbortController();
                            controller = currentController;
                            const timeout = setTimeout(() => currentController.abort(), 10000);
                            try {
                                const url = new URL(picker.dataset.searchUrl);
                                url.searchParams.set('q', q);
                                url.searchParams.set('limit', '15');
                                url.searchParams.set('countrycode', 'BR');
                                url.searchParams.set('lat', '-8.7600');
                                url.searchParams.set('lon', '-63.9000');
                                const response = await fetch(url, {
                                    signal: currentController.signal,
                                    credentials: 'omit',
                                    referrerPolicy: 'no-referrer',
                                });
                                if (!response.ok) throw new Error('Busca indisponível');
                                const data = await response.json();
                                if (requestGeneration !== generation) return;
                                for (const item of data.features || []) {
                                    const coordinates = item.geometry?.coordinates;
                                    if (
                                        item.geometry?.type !== 'Point' ||
                                        !coordinates ||
                                        !Number.isFinite(coordinates[0]) ||
                                        !Number.isFinite(coordinates[1]) ||
                                        Math.abs(coordinates[0]) > 180 ||
                                        Math.abs(coordinates[1]) > 90
                                    )
                                        continue;
                                    const p = item.properties || {};
                                    const normalizar = (valor) =>
                                        String(valor || '')
                                            .normalize('NFD')
                                            .replace(/[\u0300-\u036f]/g, '')
                                            .trim()
                                            .toLowerCase();
                                    const cidade =
                                        p.city || p.town || p.village || (p.osm_value === 'city' ? p.name : '');
                                    if (
                                        normalizar(cidade) !== 'porto velho' ||
                                        normalizar(p.state) !== 'rondonia' ||
                                        normalizar(p.countrycode) !== 'br'
                                    )
                                        continue;
                                    if (results.children.length >= 5) break;
                                    const label = [
                                        ...new Set(
                                            [
                                                p.name,
                                                [p.street, p.housenumber].filter(Boolean).join(', '),
                                                p.city || p.town || p.village,
                                                p.state,
                                                p.country,
                                            ].filter(Boolean),
                                        ),
                                    ].join(' · ');
                                    if (!label || label.length > 255) continue;
                                    const button = document.createElement('button');
                                    button.type = 'button';
                                    button.className = 'list-group-item list-group-item-action';
                                    button.setAttribute('role', 'option');
                                    button.textContent = label;
                                    button.addEventListener('click', () => {
                                        generation++;
                                        controller?.abort();
                                        clearTimeout(timer);
                                        input.value = label;
                                        lon.value = coordinates[0];
                                        lat.value = coordinates[1];
                                        valid();
                                        close();
                                        status.textContent = 'Local selecionado.';
                                        lat.dispatchEvent(new Event('input', { bubbles: true }));
                                        input.focus();
                                    });
                                    button.addEventListener('keydown', (event) => {
                                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                                            event.preventDefault();
                                            (event.key === 'ArrowDown'
                                                ? button.nextElementSibling
                                                : button.previousElementSibling
                                            )?.focus();
                                        }
                                        if (event.key === 'Escape') {
                                            close();
                                            input.focus();
                                        }
                                    });
                                    results.append(button);
                                }
                                results.hidden = !results.children.length;
                                input.setAttribute('aria-expanded', String(!results.hidden));
                                status.textContent = results.children.length
                                    ? 'Selecione o local correto na lista.'
                                    : 'Nenhum local encontrado em Porto Velho. Informe o nome do local ou rua e número.';
                            } catch (error) {
                                if (requestGeneration === generation)
                                    status.textContent =
                                        'Não foi possível buscar os locais. Tente novamente em instantes.';
                            } finally {
                                clearTimeout(timeout);
                            }
                        }, 500);
                    });
                    input.addEventListener('keydown', (event) => {
                        if (event.key === 'ArrowDown' && !results.hidden) {
                            event.preventDefault();
                            results.firstElementChild?.focus();
                        }
                        if (event.key === 'Escape') close();
                    });
                    document.addEventListener('click', (event) => {
                        if (!picker.contains(event.target)) close();
                    });
                }
            };
            if (document.readyState === 'loading')
                document.addEventListener('DOMContentLoaded', iniciar, { once: true });
            else iniciar();
        })();
    </script>
@endonce
