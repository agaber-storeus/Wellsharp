import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/proctor-class-modal-laravel.js', import.meta.url), 'utf8');
const context = { window: {}, document: { addEventListener() {} } };
vm.runInNewContext(source.replace(/\}\)\(\);\s*$/, 'globalThis.rendering = { classScoresMarkup, printRows, renderReport }; })();'), context);
const { classScoresMarkup, printRows, renderReport } = context.rendering;

test('Well Servicing roster omits the entire skills column and editor', () => {
    const html = classScoresMarkup([], false);
    assert.doesNotMatch(html, /<th>Skills Score<\/th>|class="score-input"|class="edit-score"/);
    assert.match(html, /colspan="3"/);
    assert.match(html, /Knowledge Exam/);
    assert.match(html, /Certificate/);
});

test('Drilling and legacy roster retain existing skills controls', () => {
    for (const applicable of [true, undefined]) {
        const html = classScoresMarkup([], applicable);
        assert.match(html, /<th>Skills Score<\/th>/);
        assert.match(html, /class="score-input"/);
        assert.match(html, /colspan="4"/);
    }
});

test('Well Servicing exports show N/A even when a historical score exists', () => {
    const row = { name: 'Trainee', skillsScore: 82, state: 'complete', certificateNumber: 'WS-HISTORY' };
    const html = printRows('results', { skillsApplicable: false, scoreRows: [row] });
    assert.match(html, />N\/A<\/td>/);
    assert.doesNotMatch(html, /82%/);
    assert.match(html, /WS-HISTORY/);
    assert.match(printRows('results', { scoreRows: [row] }), /82%/);
});

test('Well Servicing reports do not condition certification on practical assessment', () => {
    const report = { name: 'Trainee', score: 90, passed: true, skillsApplicable: false };
    const html = renderReport(report);
    assert.match(html, /Skills Assessment is not applicable/);
    assert.doesNotMatch(html, /If you passed your skills assessment/);
    assert.match(renderReport({ ...report, skillsApplicable: true }), /If you passed your skills assessment/);
});
