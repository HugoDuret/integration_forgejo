module.exports = {
	globals: {
		appVersion: true
	},
	parserOptions: {
		requireConfigFile: false
	},
	extends: [
		'plugin:vue/vue3-recommended',
	],
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'jsdoc/tag-lines': 'off',
		'vue/first-attribute-linebreak': 'off',
		'vue/multi-word-component-names': 'off',
		'vue/no-deprecated-v-on-native-modifier': 'off',
		'import/extensions': 'off'
	}
}
