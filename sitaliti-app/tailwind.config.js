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
                // Light Graphite Neutral Ramp (Unslop Design System)
                canvas: '#FAFAFA',
                surface: '#FFFFFF',
                raised: '#F4F4F5',
                hairline: '#E5E5E5',
                ink: {
                    DEFAULT: '#171717',
                    secondary: '#525252',
                    muted: '#737373',
                },
                // Institutional Navy Accent
                accent: {
                    DEFAULT: '#15324C',
                    hover: '#0F2537',
                    soft: '#EEF2F6',
                    border: '#CBD5E1',
                },
                // Calibrated Semantic Colors
                semantic: {
                    success: '#15803D',
                    warning: '#B45309',
                    danger: '#DC2626',
                    info: '#0369A1',
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
