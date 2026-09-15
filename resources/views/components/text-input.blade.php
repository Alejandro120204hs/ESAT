@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-navy/20 focus:border-esat-orange focus:ring-esat-orange rounded-lg shadow-sm']) }}>
