import defaultTheme from 'tailwindcss/defaultTheme';
import defaultColors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

/*
| Tema claro y oscuro con el sistema de OriA.
|
| Las familias de color que usan las vistas (slate, emerald, amber...) leen variables CSS en lugar de valores fijos,
| así `bg-white`, `text-slate-500` o `bg-emerald-50` cambian solos cuando <html data-theme="dark">. En modo claro
| las variables valen lo mismo que la paleta normal de Tailwind, así que nada cambia de color en claro.
|
| En oscuro, los fondos suaves (50-300) pasan a superficies oscuras y los textos (600-950) a tonos claros, con los
| mismos valores que `tokens.css` de OriA. Los tonos 400 y 500 (íconos, puntos, botones sólidos) no cambian.
*/

const hexToChannels = (hex) => {
    const value = hex.replace('#', '');
    const full = value.length === 3 ? value.split('').map((c) => c + c).join('') : value;

    return [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16)).join(' ');
};

const shades = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];

const darkSlate = {
    50: '#1b2842', 100: '#24303f', 200: '#324a67', 300: '#354d6d', 400: '#919fb1',
    500: '#a5b0c0', 600: '#b3bdca', 700: '#bfccdb', 800: '#d3dae7', 900: '#d3dae7',
    // 950 no cambia: se usa para fondos que oscurecen la pantalla (bg-slate-950/50 detrás del menú en celular).
};

const tone = (tints, texts) => ({
    50: tints[0], 100: tints[1], 200: tints[2], 300: tints[3],
    600: texts[0], 700: texts[1], 800: texts[2], 900: texts[3], 950: texts[4],
});

const successDark = tone(['#183a29', '#1f4a32', '#356f4e', '#3f8a60'], ['#4cc98f', '#7dddba', '#a6e8cf', '#bbdbcf', '#d6efe5']);
const warningDark = tone(['#3a2a19', '#46321d', '#6b4a24', '#8a5f2b'], ['#f2a33a', '#f8a363', '#f0c06b', '#ddd0bf', '#efe3d2']);
const orangeDark = tone(['#3d2618', '#4a331f', '#71452a', '#915833'], ['#f59866', '#f59866', '#f7b48e', '#ecd0bf', '#f5e2d6']);
const dangerDark = tone(['#361a19', '#421f1c', '#6e3b35', '#8a4840'], ['#ee766d', '#ee766d', '#f3a39c', '#dbc1bd', '#ecd9d6']);
const brandDark = tone(['#1b2740', '#1d2845', '#364870', '#4a62a0'], ['#638df8', '#7397e8', '#a3bcf3', '#c7d4ee', '#dfe7f8']);
const skyDark = tone(['#172f3b', '#1e3b49', '#2b5468', '#3a6e88'], ['#71bfea', '#71bfea', '#a5d8f2', '#c6e6f6', '#d6eef9']);
const violetDark = tone(['#211a3b', '#261d45', '#3e3270', '#54459a'], ['#937cdf', '#937cdf', '#b9a9ec', '#d4caf3', '#e6e0f8']);

const themed = {
    slate: darkSlate,
    gray: darkSlate,
    emerald: successDark,
    green: successDark,
    teal: successDark,
    amber: warningDark,
    yellow: warningDark,
    orange: orangeDark,
    rose: dangerDark,
    red: dangerDark,
    blue: brandDark,
    indigo: brandDark,
    sky: skyDark,
    cyan: skyDark,
    violet: violetDark,
};

const channel = (name) => `rgb(var(--tw-c-${name}) / <alpha-value>)`;

const themedColors = Object.fromEntries(
    Object.keys(themed).map((family) => [
        family,
        Object.fromEntries(shades.map((shade) => [shade, channel(`${family}-${shade}`)])),
    ]),
);

// Colores de marca: mismos nombres de siempre (brand-primary, brand-navy...), valores de OriA.
const brand = {
    primary: { light: '#2861f4', dark: '#2861f4' },
    secondary: { light: '#00a262', dark: '#3fd394' },
    navy: { light: '#0c1d3c', dark: '#d3dae7' },
    cloud: { light: '#f7f8fa', dark: '#0b1220' },
    slate: { light: '#6d7f98', dark: '#a5b0c0' },
    amber: { light: '#f2a33a', dark: '#f2a33a' },
    danger: { light: '#f04438', dark: '#f04438' },
    success: { light: '#00a262', dark: '#3fd394' },
    warning: { light: '#f2a33a', dark: '#f2a33a' },
    info: { light: '#2861f4', dark: '#2861f4' },
    gold: { light: '#f2a33a', dark: '#f2a33a' },
    background: { light: '#f7f8fa', dark: '#0b1220' },
};

const themeVariables = plugin(({ addBase }) => {
    const light = { '--tw-c-white': '255 255 255', '--tw-c-shadow': '15 23 42' };
    const dark = { '--tw-c-white': hexToChannels('#141d2f'), '--tw-c-shadow': '0 0 0' };

    for (const [family, darkShades] of Object.entries(themed)) {
        for (const shade of shades) {
            light[`--tw-c-${family}-${shade}`] = hexToChannels(defaultColors[family][shade]);

            if (darkShades[shade]) {
                dark[`--tw-c-${family}-${shade}`] = hexToChannels(darkShades[shade]);
            }
        }
    }

    for (const [name, value] of Object.entries(brand)) {
        light[`--tw-c-brand-${name}`] = hexToChannels(value.light);
        dark[`--tw-c-brand-${name}`] = hexToChannels(value.dark);
    }

    addBase({
        ':root': light,
        ':root[data-theme="dark"]': dark,
    });
});

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['selector', '[data-theme="dark"]'],

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Bricolage Grotesque"', '"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            // Sombras de tarjeta: en oscuro usan negro puro para que se sigan notando.
            boxShadow: {
                soft: '0 16px 36px -26px rgb(var(--tw-c-shadow) / 0.35)',
                card: '0 18px 52px -34px rgb(var(--tw-c-shadow) / 0.42)',
                raised: '0 24px 64px -36px rgb(var(--tw-c-shadow) / 0.48)',
            },
            colors: {
                white: channel('white'),
                ...themedColors,
                brand: Object.fromEntries(Object.keys(brand).map((name) => [name, channel(`brand-${name}`)])),
            },
        },
    },

    plugins: [forms, themeVariables],
};
