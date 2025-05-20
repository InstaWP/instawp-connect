/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./assets/**/*.{html,js,php}",
        "./admin/**/*.{html,js,php}",
        "./includes/**/*.{html,js,php}",
        "./migrate/**/*.{html,js,php}",
    ],
    theme: {
        extend: {
            animation: {
                'spin-reverse': 'spin-reverse 1s linear infinite',
            },
            keyframes: {
                'spin-reverse': {
                    '0%': { transform: 'rotate(360deg)' },
                    '100%': { transform: 'rotate(0deg)' },
                },
            },
            colors: {
                grayCust: {
                    50: '#6B7280',
                    100: '#E5E7EB',
                    150: '#333333',
                    200: '#111827',
                    250: '#F9FAFB',
                    300: '#1F2937',
                    350: '#D1D5DB',
                    400: '#F9FAFB',
                    450: '#A1A1AA',
                    500: '#D93F21',
                    550: '#71717A',
                    600: '#E0E0E0',
                    700: '#D4D4D8',
                    750: '#18181B',
                    800: '#27272A',
                    850: '#343541',
                    900: '#4B5563',
                    1000: '#005E54',
                },
                primary: {
                    600: '#D1FAE5',
                    700: '#11BF85',
                    800: '#0B6C63',
                    900: '#005E54',
                },
                redCust: {
                    50: '#FEE2E2',
                    100: '#991B1B',
                },
                purpleCust: {
                    50: '#DBEAFE',
                    100: '#1E40AF',
                },
                yellowCust: {
                    50: '#FEF3C7',
                    100: '#92400E',
                    150: '#FEFCE8',
                    200: '#A16207',
                },
                blueCust: {
                    50: 'rgba(107,47,173,0.05)',
                    100: 'rgba(107,47,173,0.6)',
                    200: '#6B2FAD',
                },
                secondary: '#15B881',
            },
        },
    },
    plugins: [],
}

