/**
 * Tests CRUD de usuarios (requiere token válido).
 * Ejecutar: npm run test
 */
import { describe, it } from 'node:test';
import assert from 'node:assert';
import request from 'supertest';
import app from '../../src/app.js';

const api = request(app);

describe('Users CRUD API', () => {
  let token;
  let createdId;
  const testEmail = `crud-${Date.now()}@example.com`;
  const testPass = 'CrudTest123!';

  it('obtener token vía register', async () => {
    const { status, body } = await api
      .post('/api/auth/register')
      .send({
        name: 'CRUD Tester',
        email: testEmail,
        password: testPass,
      });
    assert.strictEqual(status, 201);
    token = body.accessToken;
  });

  it('GET /api/users — sin token retorna 401', async () => {
    const { status, body } = await api.get('/api/users');
    assert.strictEqual(status, 401);
    assert.strictEqual(body.code, 'UNAUTHORIZED');
  });

  it('GET /api/users — con token devuelve lista paginada', async () => {
    const { status, body } = await api
      .get('/api/users')
      .set('Authorization', `Bearer ${token}`);
    assert.strictEqual(status, 200);
    assert.ok(Array.isArray(body.data));
    assert.strictEqual(typeof body.page, 'number');
    assert.strictEqual(typeof body.total, 'number');
    assert.ok(body.data.every((u) => u.id && u.name && u.email && !u.passwordHash));
  });

  it('POST /api/users — crea usuario (sin password, solo name/email)', async () => {
    const email = `new-${Date.now()}@example.com`;
    const { status, body } = await api
      .post('/api/users')
      .set('Authorization', `Bearer ${token}`)
      .send({ name: 'New User', email, age: 25 });
    assert.strictEqual(status, 201);
    assert.ok(body.id);
    assert.strictEqual(body.email, email);
    assert.strictEqual(body.name, 'New User');
    assert.strictEqual(body.age, 25);
    createdId = body.id;
  });

  it('POST /api/users — email duplicado retorna 409', async () => {
    const { status, body } = await api
      .post('/api/users')
      .set('Authorization', `Bearer ${token}`)
      .send({
        name: 'Duplicate',
        email: testEmail,
      });
    assert.strictEqual(status, 409);
    assert.strictEqual(body.code, 'EMAIL_ALREADY_EXISTS');
  });

  it('GET /api/users/:id — devuelve usuario existente', async () => {
    const { status, body } = await api
      .get(`/api/users/${createdId}`)
      .set('Authorization', `Bearer ${token}`);
    assert.strictEqual(status, 200);
    assert.strictEqual(body.id, createdId);
  });

  it('GET /api/users/:id — inexistente retorna 404', async () => {
    const { status, body } = await api
      .get('/api/users/999999')
      .set('Authorization', `Bearer ${token}`);
    assert.strictEqual(status, 404);
    assert.strictEqual(body.code, 'USER_NOT_FOUND');
  });

  it('PUT /api/users/:id — actualiza usuario', async () => {
    const { status, body } = await api
      .put(`/api/users/${createdId}`)
      .set('Authorization', `Bearer ${token}`)
      .send({
        name: 'Updated Name',
        email: `updated-${createdId}@example.com`,
        age: 30,
      });
    assert.strictEqual(status, 200);
    assert.strictEqual(body.name, 'Updated Name');
    assert.strictEqual(body.age, 30);
  });

  it('DELETE /api/users/:id — elimina usuario', async () => {
    const { status } = await api
      .delete(`/api/users/${createdId}`)
      .set('Authorization', `Bearer ${token}`);
    assert.strictEqual(status, 204);
  });

  it('GET /api/users/:id — tras eliminar retorna 404', async () => {
    const { status, body } = await api
      .get(`/api/users/${createdId}`)
      .set('Authorization', `Bearer ${token}`);
    assert.strictEqual(status, 404);
    assert.strictEqual(body.code, 'USER_NOT_FOUND');
  });
});
