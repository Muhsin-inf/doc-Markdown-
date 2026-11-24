/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './*.php',
    './components/**/*.php',
    './*.html'
  ],
  theme: {
    extend: {
      borderRadius: {
        'none': '0px',
        'xs': '1px',
        'sm': '2px',
        DEFAULT: '2px',
        'md': '2px',
        'lg': '3px',
        'xl': '3px',
        '2xl': '3px',
        '3xl': '3px',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'monospace']
      }
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
  ],
}
