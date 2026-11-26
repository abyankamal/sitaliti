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
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Warm Civic Ledger Neutral Ramp (Archival Stone & Ivory Paper)
                canvas: '#F5F3EE',
                surface: '#FDFCFA',
                raised: '#ECE8DF',
                hairline: '#DDD7CA',
                ink: {
                    DEFAULT: '#1C1917',
                    secondary: '#44403C',
                    muted: '#57534E',
                },
                // Primary Institutional Accent (Deep Civic Navy — Single Deliberate Accent)
                accent: {
                    DEFAULT: '#162C46',
                    hover: '#0F1F33',
                    soft: '#EAF0F6',
                    border: '#C5D3E2',
                },
                // Calibrated Semantic Colors (Emerald reserved exclusively for success/realization)
                semantic: {
                    success: '#047857',
                    warning: '#B45309',
                    danger: '#B91C1C',
                    info: '#1D4ED8',
                },
                // Deep Government Navy Theme (dipertahankan untuk kompatibilitas transisi bertahap)
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
            },
            boxShadow: {
                subtle: '0 1px 2px rgba(0, 0, 0, 0.04)',
                floating: '0 8px 24px rgba(0, 0, 0, 0.06)',
            },
            transitionTimingFunction: {
                unslop: 'cubic-bezier(0.22, 1, 0.36, 1)',
            },
        },
    },

    plugins: [forms],
};
