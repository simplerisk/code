/**
 * Helpers Unit Tests
 * ===================
 */
var assert = require('assert'),
    lib = require('./');

describe('#.extend', function() {
  it('should correctly extend the given array.', function() {
    var A = [1, 2, 3],
        B = [4, 5, 6];

    lib.extend(A, B);

    assert.strictEqual(A.length, 6);
    assert.strictEqual(B.length, 3);
    assert.deepEqual(A, [1, 2, 3, 4, 5, 6]);
  });
});
