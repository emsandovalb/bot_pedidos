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
                sans: ['Poppins', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    primary: '#0A3D91',
                    navy: '#081F4D',
                    gold: '#F5B400',
                    background: '#F4F7FB',
                    secondary: '#6B7280',
                    success: '#22C55E',
                    warning: '#F59E0B',
                    danger: '#EF4444',
                    info: '#3B82F6',
                },
            },
        },
    },

    plugins: [forms],
};
