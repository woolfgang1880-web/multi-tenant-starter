/**
 * Almacén en memoria de usuarios (preparado para sustituir por Prisma/PostgreSQL).
 * Incluye passwordHash solo para autenticación; no exponer en respuestas API.
 *
 * @typedef {{ id: number; name: string; email: string; age: number | null; createdAt: string; passwordHash: string | null }} UserRecord
 */

let nextId = 3;

/** @type {UserRecord[]} */
const users = [
  {
    id: 1,
    name: 'Ana López',
    email: 'ana@example.com',
    age: 30,
    createdAt: '2025-01-15T10:00:00.000Z',
    passwordHash: null,
  },
  {
    id: 2,
    name: 'Luis Pérez',
    email: 'luis@example.com',
    age: 42,
    createdAt: '2025-02-01T14:30:00.000Z',
    passwordHash: null,
  },
];

export function normalizeEmail(email) {
  return email.trim().toLowerCase();
}

/**
 * @param {UserRecord} record
 */
export function toPublicUser(record) {
  if (!record) {
    return null;
  }
  return {
    id: record.id,
    name: record.name,
    email: record.email,
    age: record.age,
    createdAt: record.createdAt,
  };
}

export function getAllUsers() {
  return [...users];
}

/**
 * @param {string} emailNormalized
 */
export function findByEmail(emailNormalized) {
  return users.find((u) => u.email === emailNormalized) ?? null;
}

/**
 * @param {number} id
 */
export function findById(id) {
  return users.find((u) => u.id === id) ?? null;
}

/**
 * @param {{ name: string; email: string; age: number | null; passwordHash: string | null }} input
 */
export function insertUser(input) {
  const email = normalizeEmail(input.email);
  if (findByEmail(email)) {
    const err = new Error('Email already registered');
    err.code = 'EMAIL_ALREADY_EXISTS';
    throw err;
  }
  const id = nextId++;
  const createdAt = new Date().toISOString();
  const record = {
    id,
    name: input.name,
    email,
    age: input.age,
    createdAt,
    passwordHash: input.passwordHash,
  };
  users.push(record);
  return record;
}

/**
 * @param {number} id
 * @param {{ name: string; email: string; age: number | null }} input
 */
export function updateUserRecord(id, input) {
  const idx = users.findIndex((u) => u.id === id);
  if (idx === -1) {
    return null;
  }
  const nextEmail = normalizeEmail(input.email);
  const other = users.find(
    (u) => u.id !== id && u.email === nextEmail,
  );
  if (other) {
    const err = new Error('Email already registered');
    err.code = 'EMAIL_ALREADY_EXISTS';
    throw err;
  }
  users[idx] = {
    ...users[idx],
    name: input.name,
    email: nextEmail,
    age: input.age,
  };
  return users[idx];
}

/**
 * @param {number} id
 */
export function deleteUserRecord(id) {
  const idx = users.findIndex((u) => u.id === id);
  if (idx === -1) {
    return false;
  }
  users.splice(idx, 1);
  return true;
}
