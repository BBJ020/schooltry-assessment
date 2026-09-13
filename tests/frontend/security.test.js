import assert from 'node:assert/strict';
import test from 'node:test';
import { apiError } from '../../resources/js/services/api.js';
import { safeFilename } from '../../resources/js/utils/filename.js';

test('safeFilename removes paths and control characters', () => {
    assert.equal(safeFilename('../private/report\u0000.pdf'), 'report.pdf');
    assert.equal(safeFilename(''), 'Attachment');
});

test('apiError exposes normalized messages and validation fields, not backend debug details', () => {
    const normalized = apiError({
        response: {
            status: 422,
            data: {
                message: 'SQLSTATE secret',
                trace: ['internal stack'],
                errors: { title: ['The title field is required.'] },
            },
        },
    });

    assert.equal(normalized.message, 'Please correct the highlighted fields.');
    assert.deepEqual(normalized.validation, { title: ['The title field is required.'] });
    assert.equal('trace' in normalized, false);
});
