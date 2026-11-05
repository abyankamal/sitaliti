import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', 'Fira Code', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Deep Government Navy Theme
                gov: {
                    navy: {
                        50: '#F0F4F8',
                        100: '#D9E2EC',
                        200: '#BCCCDC',
                        300: '#9FB3C8',
                        400: '#829AB1',
                        500: '#627D98',
                        600: '#486581',
                        700: '#334E68',
                        800: '#1E3E62', // Secondary dark
                        900: '#0F2537', // Deep Navy Base
                        950: '#071520', // Ultra dark sidebar/header
                    },
                    gold: {
                        50: '#FFFDF5',
                        100: '#FFF8E1',
                        200: '#FFECB3',
                        300: '#FFE082',
                        400: '#FFD54F',
                        500: '#EAB308',
                        600: '#D97706',
                        700: '#B45309',
                    },
                    surface: {
                        DEFAULT: '#FFFFFF',
                        subtle: '#F8FAFC',
                        muted: '#F1F5F9',
                        border: '#E2E8F0',
                    }
                }
            }
        },
    },

    plugins: [forms],
};
