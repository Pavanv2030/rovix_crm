/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./app/Views/**/*.php",
    "./public/**/*.js",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif']
      },
      colors: {
        primary: {
          DEFAULT: '#25D366',
          dark: '#128C7E',
          hover: '#16A34A'
        },
      },
    },
  },
  plugins: [],
}
