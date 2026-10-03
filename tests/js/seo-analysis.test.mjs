// Runs the shared SEO cases against the TypeScript analyzer (Node strips the types itself).
// The same cases run against App\Support\Seo\SeoAnalyzer in tests/Feature/SeoAnalyzerTest.php.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
    analyzeReadability,
    analyzeSeo,
    slugify,
} from '../../resources/js/admin/modules/seo/analysis.ts';

const fixture = JSON.parse(readFileSync(new URL('../Fixtures/seo-analysis.json', import.meta.url), 'utf8'));

describe('SEO checks', () => {
    for (const { name, input, expected } of fixture.seo) {
        it(name, () => assert.deepEqual(analyzeSeo(input), expected));
    }
});

describe('readability checks', () => {
    for (const { name, text, expected } of fixture.readability) {
        it(name, () => assert.deepEqual(analyzeReadability(text), expected));
    }
});

describe('slugify', () => {
    for (const [input, expected] of fixture.slugify) {
        it(JSON.stringify(input), () => assert.equal(slugify(input), expected));
    }
});
