@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-esat-green']) }}>
        {{ $status }}
    </div>
@endif
