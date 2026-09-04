import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                // A warm, literary palette — deliberately not a generic
                // blue/purple SaaS or AI-gradient look.
                paper: {
                    DEFAULT: '#FAF6EF',
                    raised: '#FFFFFF',
                    dark: '#1B1815',
                    'dark-raised': '#242019',
                },
                ink: {
                    DEFAULT: '#241F19',
                    muted: '#5B5347',
                    faint: '#8C8272',
                    dark: '#EDE7DA',
                    'dark-muted': '#B8AF9D',
                    'dark-faint': '#867C6C',
                },
                line: {
                    DEFAULT: '#E4DCC9',
                    dark: '#39332A',
                },
                accent: {
                    DEFAULT: '#A8531B',
                    hover: '#8F4415',
                    dark: '#D98C3F',
                    'dark-hover': '#E7A462',
                },
                danger: {
                    DEFAULT: '#B3311F',
                    dark: '#E2765F',
                },
                success: {
                    DEFAULT: '#3F6C4E',
                    dark: '#7EBB92',
                },
            },
        },
    },

    plugins: [forms],
};
