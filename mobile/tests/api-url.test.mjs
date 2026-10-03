import { test } from 'node:test';
import assert from 'node:assert/strict';
import { resolveApiUrl } from '../src/api/url.ts';

test('normalizes a Laravel HTTPS endpoint', () => {
  assert.equal(resolveApiUrl(' https://erp.example.com/api/v1/ ', false), 'https://erp.example.com/api/v1');
});
test('rejects Supabase URLs instead of calling nonexistent Laravel endpoints', () => {
  assert.throws(() => resolveApiUrl('https://pizpdvfdnlvasqvtjbiv.supabase.co/api/v1', false), /Supabase/);
});
test('allows emulators only during development', () => {
  assert.equal(resolveApiUrl('http://10.0.2.2:8000/api/v1', true), 'http://10.0.2.2:8000/api/v1');
  assert.throws(() => resolveApiUrl('http://10.0.2.2:8000/api/v1', false), /HTTPS/);
  assert.throws(() => resolveApiUrl('http://erp.example.com/api/v1', true), /HTTPS/);
});
test('rejects absent, malformed, secret-bearing and incorrect endpoints', () => {
  for (const raw of [undefined, '', 'https\\://example.com/api/v1', 'https://erp.example.com', 'https://user:password@erp.example.com/api/v1', 'https://erp.example.com/api/v1?token=secret', 'https://erp.example.com/api/v1#secret']) {
    assert.throws(() => resolveApiUrl(raw, false));
  }
});
