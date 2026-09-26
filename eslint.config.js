import { defineConfig } from 'eslint/config';
import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';
import prettier from 'eslint-config-prettier';

/**
 * Keeps the storefront and admin apps independent: shared code belongs in resources/js/shared.
 *
 * @param {string} forbiddenApp
 */
const forbidImportsFrom = (forbiddenApp) => ({
    'no-restricted-imports': [
        'error',
        {
            patterns: [
                {
                    group: [`@/${forbiddenApp}/*`, `**/${forbiddenApp}/*`],
                    message: 'The storefront and admin apps must not import from each other. Move shared code to @/shared.',
                },
            ],
        },
    ],
});

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
    },
    {
        files: ['resources/js/storefront/**/*.{ts,vue}'],
        rules: forbidImportsFrom('admin'),
    },
    {
        files: ['resources/js/admin/**/*.{ts,vue}'],
        rules: forbidImportsFrom('storefront'),
    },
    {
        files: ['resources/js/shared/**/*.{ts,vue}'],
        rules: {
            'no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            group: ['@/admin/*', '@/storefront/*', '**/admin/*', '**/storefront/*'],
                            message: 'Shared code must not depend on the storefront or admin apps.',
                        },
                    ],
                },
            ],
        },
    },
    prettier,
);
