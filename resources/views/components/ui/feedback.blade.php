@if (session('status'))
    <x-ui.alert>{{ session('status') }}</x-ui.alert>
@endif
@if ($errors->any())
    <x-ui.alert tom="danger"><p class="fw-semibold">Revise os campos indicados.</p><ul class="mb-0">
        @foreach ($errors->all() as $erro)
            <li>{{ $erro }}</li>
        @endforeach
    </ul></x-ui.alert>
@endif
