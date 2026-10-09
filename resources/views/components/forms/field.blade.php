@props(['nome', 'rotulo', 'tipo' => 'text', 'valor' => '', 'obrigatorio' => false, 'opcoes' => [], 'nota' => '', 'id' => null, 'placeholder' => 'Selecione'])
@php($identificador = $id ?? 'field-'.str_replace(['[', ']'], ['-', ''], $nome))
<div class="mb-3">
    <label class="form-label" for="{{ $identificador }}">{{ $rotulo }} @if($obrigatorio) <span aria-hidden="true">*</span> @endif</label>
    @if ($tipo === 'select')
        <select id="{{ $identificador }}" name="{{ $nome }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($nome)]) }} @required($obrigatorio) aria-invalid="{{ $errors->has($nome) ? 'true' : 'false' }}" aria-describedby="{{ $identificador }}-help {{ $identificador }}-error">
            <option value="">{{ $placeholder }}</option>
            @foreach ($opcoes as $chave => $rotuloOpcao)
                <option value="{{ $chave }}" @selected((string) old($nome, $valor) === (string) $chave)>{{ $rotuloOpcao }}</option>
            @endforeach
        </select>
    @elseif ($tipo === 'textarea')
        <textarea id="{{ $identificador }}" name="{{ $nome }}" rows="4" {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($nome)]) }} @required($obrigatorio) aria-invalid="{{ $errors->has($nome) ? 'true' : 'false' }}" aria-describedby="{{ $identificador }}-help {{ $identificador }}-error">{{ old($nome, $valor) }}</textarea>
    @else
        <input id="{{ $identificador }}" name="{{ $nome }}" type="{{ $tipo }}" @if(!in_array($tipo, ['password', 'file'], true)) value="{{ old($nome, $valor) }}" @endif {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($nome)]) }} @required($obrigatorio) aria-invalid="{{ $errors->has($nome) ? 'true' : 'false' }}" aria-describedby="{{ $identificador }}-help {{ $identificador }}-error" />
    @endif
    <div class="form-text" id="{{ $identificador }}-help">{{ $nota }}</div>
    <div class="invalid-feedback" id="{{ $identificador }}-error">@error($nome) {{ $message }} @enderror</div>
</div>
