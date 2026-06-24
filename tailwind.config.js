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
                    primary: '#146EDB',
                    secondary: '#16A34A',
                    navy: '#0F172A',
                    cloud: '#F8FAFC',
                    slate: '#64748B',
                    amber: '#F59E0B',
                    danger: '#DC2626',
                    success: '#16A34A',
                    warning: '#F59E0B',
                    info: '#146EDB',
                    gold: '#F59E0B',
                    background: '#F8FAFC',
                },
            },
        },
    },

    plugins: [forms],
};
