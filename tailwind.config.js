/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./**/*.{html,js,php}"],
    theme: {
        extend: {
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
                    500: '#D93F21',
                    900: '#4B5563'
                },
                primary: {
                    600: '#D1FAE5',
                    700: '#11BF85',
                    800: '#0B6C63',
                    900: '#005E54',
                },
                redCust: {
                    50: '#FEE2E2',
                    100: '#991B1B'
                },
                purpleCust: {
                    50: '#DBEAFE',
                    100: '#1E40AF'
                },
                yellowCust: {
                    50: '#FEF3C7',
                    100: '#92400E'
                }
            }
        },
    },
    plugins: [],
}
