import { env } from '../../config/env.js';
import { validationError } from '../../shared/utils/apiErrors.js';

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim());
}

/**
 * @param {unknown} body
 */
export function validateRegisterBody(body) {
  if (!body || typeof body !== 'object') {
    return {
      ok: false,
      error: validationError('body', 'must be a JSON object'),
    };
  }

  const name = body.name;
  const email = body.email;
  const password = body.password;
  const age = body.age;

  if (typeof name !== 'string' || name.trim().length < 2) {
    return {
      ok: false,
      error: validationError('name', 'must be a string with minLength 2'),
    };
  }

  if (typeof email !== 'string' || !isValidEmail(email)) {
    return {
      ok: false,
      error: validationError('email', 'must be a valid email address'),
    };
  }

  if (typeof password !== 'string' || password.length < env.passwordMinLength) {
    return {
      ok: false,
      error: validationError(
        'password',
        `must be at least ${env.passwordMinLength} characters`,
      ),
    };
  }

  if (age !== undefined && age !== null) {
    if (typeof age !== 'number' || !Number.isInteger(age) || age < 0) {
      return {
        ok: false,
        error: validationError('age', 'must be an integer >= 0 when provided'),
      };
    }
  }

  return {
    ok: true,
    value: {
      name: name.trim(),
      email: email.trim(),
      password,
      age: age !== undefined && age !== null ? age : undefined,
    },
  };
}

/**
 * @param {unknown} body
 */
export function validateLoginBody(body) {
  if (!body || typeof body !== 'object') {
    return {
      ok: false,
      error: validationError('body', 'must be a JSON object'),
    };
  }

  const email = body.email;
  const password = body.password;

  if (typeof email !== 'string' || !isValidEmail(email)) {
    return {
      ok: false,
      error: validationError('email', 'must be a valid email address'),
    };
  }

  if (typeof password !== 'string' || password.length < 1) {
    return {
      ok: false,
      error: validationError('password', 'must be a non-empty string'),
    };
  }

  return {
    ok: true,
    value: { email: email.trim(), password },
  };
}

/**
 * @param {unknown} body
 */
export function validateRefreshBody(body) {
  if (!body || typeof body !== 'object') {
    return {
      ok: false,
      error: validationError('body', 'must be a JSON object'),
    };
  }

  const refreshToken = body.refreshToken;

  if (typeof refreshToken !== 'string' || refreshToken.trim().length < 1) {
    return {
      ok: false,
      error: validationError('refreshToken', 'must be a non-empty string'),
    };
  }

  return { ok: true, value: { refreshToken: refreshToken.trim() } };
}
