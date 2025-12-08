/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './admin/**/*.php',
    './views/**/*.php',
    './includes/**/*.php',
    './assets/js/**/*.js',
    './assets/css/**/*.css',
    './*.php'
  ],
  theme: {
    extend: {},
  },
  plugins: [],
  // Prefix to avoid conflicts with WordPress admin styles
  corePlugins: {
    preflight: false,
  }
}
