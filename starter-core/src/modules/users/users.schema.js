import { validationError } from '../../shared/utils/apiErrors.js';

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim());
}

export { validationError };

/**
 * @param {unknown} value
 * @param {{ min?: number; max?: number; default?: number; name: string }} opts
 */
export function parseIntegerQuery(value, opts) {
  const { min = 1, max, default: def, name } = opts;
  if (value === undefined || value === '') {
    return { ok: true, value: def };
  }
  const n = Number.parseInt(String(value), 10);
  if (Number.isNaN(n) || n < min || (max !== undefined && n > max)) {
    return {
      ok: false,
      error: validationError(
        name,
        `must be a valid integer${max !== undefined ? ` between ${min} and ${max}` : ` >= ${min}`}`,
      ),
    };
  }
  return { ok: true, value: n };
}

/**
 * @param {import('express').Request['query']} query
 */
export function parseListUsersQuery(query) {
  const pageParsed = parseIntegerQuery(query.page, {
    name: 'page',
    min: 1,
    default: 1,
  });
  if (!pageParsed.ok) {
    return pageParsed;
  }

  const limitParsed = parseIntegerQuery(query.limit, {
    name: 'limit',
    min: 1,
    max: 100,
    default: 10,
  });
  if (!limitParsed.ok) {
    return limitParsed;
  }

  const rawSearch = query.search;
  let search;
  if (rawSearch !== undefined && rawSearch !== null && String(rawSearch) !== '') {
    search = String(rawSearch).trim();
    if (search.length < 1) {
      return {
        ok: false,
        error: validationError('search', 'must have minLength 1 when provided'),
      };
    }
  }

  return {
    ok: true,
    value: {
      page: pageParsed.value,
      limit: limitParsed.value,
      search,
    },
  };
}

/**
 * @param {unknown} body
 * @param {{ partial?: boolean }} [opts]
 */
export function validateUserBody(body, { partial = false } = {}) {
  if (!body || typeof body !== 'object') {
    return {
      ok: false,
      error: validationError('body', 'must be a JSON object'),
    };
  }

  const name = body.name;
  const email = body.email;
  const age = body.age;

  if (!partial || name !== undefined) {
    if (typeof name !== 'string' || name.length < 2) {
      return {
        ok: false,
        error: validationError('name', 'must be a string with minLength 2'),
      };
    }
  }

  if (!partial || email !== undefined) {
    if (typeof email !== 'string' || !isValidEmail(email)) {
      return {
        ok: false,
        error: validationError('email', 'must be a valid email address'),
      };
    }
  }

  if (age !== undefined && age !== null) {
    if (typeof age !== 'number' || !Number.isInteger(age) || age < 0) {
      return {
        ok: false,
        error: validationError('age', 'must be an integer >= 0'),
      };
    }
  }

  return { ok: true, value: { name, email, age } };
}

/**
 * @param {string} raw
 */
export function parseUserIdParam(raw) {
  const id = Number.parseInt(String(raw), 10);
  if (Number.isNaN(id) || id < 1) {
    return {
      ok: false,
      error: validationError('id', 'must be an integer >= 1'),
    };
  }
  return { ok: true, value: id };
}
