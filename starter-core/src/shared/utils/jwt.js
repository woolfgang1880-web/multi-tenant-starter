import jwt from 'jsonwebtoken';
import { env } from '../../config/env.js';

const ACCESS_EXPIRES = '15m';
const REFRESH_EXPIRES = '7d';

/**
 * @param {{ sub: string; type?: string }} payload
 */
export function signAccessToken(payload) {
  return jwt.sign(
    { ...payload, type: 'access' },
    env.accessTokenSecret,
    { expiresIn: ACCESS_EXPIRES },
  );
}

/**
 * @param {{ sub: string; type?: string }} payload
 */
export function signRefreshToken(payload) {
  return jwt.sign(
    { ...payload, type: 'refresh' },
    env.refreshTokenSecret,
    { expiresIn: REFRESH_EXPIRES },
  );
}

/**
 * @param {string} token
 */
export function verifyAccessToken(token) {
  const decoded = jwt.verify(token, env.accessTokenSecret);
  if (decoded.type && decoded.type !== 'access') {
    const err = new Error('Invalid token type');
    err.name = 'JsonWebTokenError';
    throw err;
  }
  return decoded;
}

/**
 * @param {string} token
 */
export function verifyRefreshToken(token) {
  const decoded = jwt.verify(token, env.refreshTokenSecret);
  if (decoded.type && decoded.type !== 'refresh') {
    const err = new Error('Invalid token type');
    err.name = 'JsonWebTokenError';
    throw err;
  }
  return decoded;
}
