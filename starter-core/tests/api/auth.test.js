/**
 * Tests de autenticación: login, register, refresh, auth/me.
 * Ejecutar: npm run test (con API corriendo) o npm run test:supertest
 */
import { after, before, describe, it } from 'node:test';
import assert from 'node:assert';
import request from 'supertest';
import app from '../../src/app.js';

const api = request(app);

describe('Auth API', () => {
  let accessToken;
  let refreshToken;
  const testEmail = `test-${Date.now()}@example.com`;
  const testPassword = 'TestPassword123!';

  it('POST /api/auth/register — crea usuario y devuelve tokens', async () => {
    const { status, body } = await api
      .post('/api/auth/register')
      .send({
        name: 'Test User',
        email: testEmail,
        password: testPassword,
      });
    assert.strictEqual(status, 201);
    assert.ok(body.accessToken);
    assert.ok(body.refreshToken);
    assert.strictEqual(body.tokenType, 'Bearer');
    assert.ok(body.user?.id);
    assert.strictEqual(body.user?.email, testEmail);
    assert.strictEqual(body.user?.name, 'Test User');
    assert.ok(!body.user?.passwordHash);
    accessToken = body.accessToken;
    refreshToken = body.refreshToken;
  });

  it('POST /api/auth/register — email duplicado retorna 409', async () => {
    const { status, body } = await api
      .post('/api/auth/register')
      .send({
        name: 'Other',
        email: testEmail,
        password: 'OtherPass123!',
      });
    assert.strictEqual(status, 409);
    assert.strictEqual(body.code, 'EMAIL_ALREADY_EXISTS');
  });

  it('POST /api/auth/login — credenciales correctas devuelven tokens', async () => {
    const { status, body } = await api
      .post('/api/auth/login')
      .send({ email: testEmail, password: testPassword });
    assert.strictEqual(status, 200);
    assert.ok(body.accessToken);
    assert.ok(body.refreshToken);
    accessToken = body.accessToken;
    refreshToken = body.refreshToken;
  });

  it('POST /api/auth/login — credenciales incorrectas retornan 401', async () => {
    const { status, body } = await api
      .post('/api/auth/login')
      .send({ email: testEmail, password: 'WrongPass' });
    assert.strictEqual(status, 401);
    assert.strictEqual(body.code, 'INVALID_CREDENTIALS');
  });

  it('POST /api/auth/login — usuario inexistente retorna 401', async () => {
    const { status, body } = await api
      .post('/api/auth/login')
      .send({
        email: 'noexiste@example.com',
        password: 'SomePass123!',
      });
    assert.strictEqual(status, 401);
    assert.strictEqual(body.code, 'INVALID_CREDENTIALS');
  });

  it('GET /api/auth/me — con Bearer válido devuelve usuario actual', async () => {
    const { status, body } = await api
      .get('/api/auth/me')
      .set('Authorization', `Bearer ${accessToken}`);
    assert.strictEqual(status, 200);
    assert.ok(body?.id);
    assert.strictEqual(body?.email, testEmail);
    assert.ok(!body?.passwordHash);
  });

  it('GET /api/auth/me — sin Bearer retorna 401', async () => {
    const { status, body } = await api.get('/api/auth/me');
    assert.strictEqual(status, 401);
    assert.strictEqual(body.code, 'UNAUTHORIZED');
  });

  it('GET /api/auth/me — token inválido retorna 401', async () => {
    const { status, body } = await api
      .get('/api/auth/me')
      .set('Authorization', 'Bearer invalid.jwt.here');
    assert.strictEqual(status, 401);
    assert.strictEqual(body.code, 'INVALID_TOKEN');
  });

  it('POST /api/auth/refresh — refresh válido devuelve nuevo access token', async () => {
    const { status, body } = await api
      .post('/api/auth/refresh')
      .send({ refreshToken });
    assert.strictEqual(status, 200);
    assert.ok(body.accessToken);
    assert.strictEqual(body.tokenType, 'Bearer');
    accessToken = body.accessToken;
  });

  it('POST /api/auth/refresh — refresh inválido retorna 401', async () => {
    const { status, body } = await api
      .post('/api/auth/refresh')
      .send({ refreshToken: 'invalid.refresh.token' });
    assert.strictEqual(status, 401);
    assert.strictEqual(body.code, 'INVALID_TOKEN');
  });
});
