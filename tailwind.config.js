/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './includes/admin/ui/**/*.php',
    './includes/admin/ui/**/*.js',
  ],
  theme: {
    extend: {
      // Custom theme extensions can go here
      // For now, using default Tailwind theme
    },
  },
  plugins: [],
  // Important: Ensure Tailwind only affects the iframe UI
  // The iframe is isolated, so no need for prefix or important
  corePlugins: {
    preflight: true, // Reset styles (safe in iframe isolation)
  },
}

