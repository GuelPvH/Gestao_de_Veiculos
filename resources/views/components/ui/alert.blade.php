@props(['tom' => 'info'])
<div role="{{ $tom === 'danger' ? 'alert' : 'status' }}" {{ $attributes->class(['alert', 'alert-'.(in_array($tom, ['info', 'warning', 'danger', 'success'], true) ? $tom : 'info')]) }}>{{ $slot }}</div>
