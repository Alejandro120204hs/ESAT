import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Public Sans', ...defaultTheme.fontFamily.sans],
                display: ['Archivo', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Misma paleta de marca que public/assets/css/css_website.css
                navy: {
                    DEFAULT: '#10284A',
                    2: '#173B67',
                    3: '#0A1B33',
                },
                esat: {
                    orange: '#D97B2E',
                    'orange-dark': '#BE661F',
                    green: '#40763A',
                },
            },
        },
    },

    plugins: [forms],
};
