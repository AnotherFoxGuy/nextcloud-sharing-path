import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	main: 'src/main.js',
	settings: 'src/settings.js',
}, {
	minify: false,
	nodePolyfills: {
		globals: {
			// Don't polyfill these globals
			process: false,
			Buffer: false,
		},
	}
})
