<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-5 py-2.5 bg-esat-orange border border-transparent rounded-full font-display font-bold text-sm text-white hover:bg-esat-orange-dark focus:bg-esat-orange-dark active:bg-esat-orange-dark focus:outline-none focus:ring-2 focus:ring-esat-orange focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
