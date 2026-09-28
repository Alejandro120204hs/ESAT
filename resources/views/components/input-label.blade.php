@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-display font-semibold text-sm text-navy-3']) }}>
    {{ $value ?? $slot }}
</label>
