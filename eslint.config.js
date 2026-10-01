import { defineConfig } from 'eslint/config';
import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';
import prettier from 'eslint-config-prettier';

export default defineConfig(
    {
        ignores: ['vendor/**', 'node_modules/**', 'public/**', 'storage/**', 'bootstrap/**'],
    },
    {
        files: ['resources/js/**/*.{ts,vue}'],
        extends: [tseslint.configs.recommended, pluginVue.configs['flat/recommended']],
        languageOptions: {
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
                sourceType: 'module',
            },
        },
        rules: {
            // Optional TypeScript props are intentionally `undefined` when omitted.
            'vue/require-default-prop': 'off',
        },
    },
    {
        // Shared code (UI kit, HTTP client, i18n) stays reusable: it must not depend on the admin app.
        files: ['resources/js/shared/**/*.{ts,vue}'],
        rules: {
            'no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            group: ['@/admin/*', '**/admin/*'],
                            message: 'Shared code must not depend on the admin app.',
                        },
                    ],
                },
            ],
        },
    },
    prettier,
);
