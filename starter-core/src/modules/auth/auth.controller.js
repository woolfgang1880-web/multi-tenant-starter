import { logger } from '../../shared/utils/logger.js';
import * as userRepository from '../users/user.repository.js';
import * as authSchema from './auth.schema.js';
import * as authService from './auth.service.js';

/**
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 * @param {import('express').NextFunction} next
 */
export async function register(req, res, next) {
  const v = authSchema.validateRegisterBody(req.body);
  if (!v.ok) {
    return res.status(400).json(v.error);
  }

  try {
    const out = await authService.register(v.value);
    return res.status(201).json(out);
  } catch (e) {
    if (e && typeof e === 'object' && e.code === 'EMAIL_ALREADY_EXISTS') {
      return res.status(409).json({
        message: 'Email already registered',
        code: 'EMAIL_ALREADY_EXISTS',
      });
    }
    return next(e);
  }
}

/**
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 * @param {import('express').NextFunction} next
 */
export async function login(req, res, next) {
  const v = authSchema.validateLoginBody(req.body);
  if (!v.ok) {
    return res.status(400).json(v.error);
  }

  try {
    const out = await authService.login(v.value);
    return res.status(200).json(out);
  } catch (e) {
    if (e && typeof e === 'object' && e.code === 'INVALID_CREDENTIALS') {
      logger.security('auth.login.failed', {
        ip: req.ip ?? req.socket?.remoteAddress,
        path: req.path,
      });
      return res.status(401).json({
        message: 'Invalid credentials',
        code: 'INVALID_CREDENTIALS',
      });
    }
    return next(e);
  }
}

/**
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 * @param {import('express').NextFunction} next
 */
export async function refresh(req, res, next) {
  const v = authSchema.validateRefreshBody(req.body);
  if (!v.ok) {
    return res.status(400).json(v.error);
  }

  try {
    const out = await authService.refresh(v.value);
    return res.status(200).json(out);
  } catch (e) {
    if (e && typeof e === 'object' && e.code === 'INVALID_TOKEN') {
      return res.status(401).json({
        message: 'Invalid or expired refresh token',
        code: 'INVALID_TOKEN',
      });
    }
    return next(e);
  }
}

/**
 * GET /auth/me — devuelve usuario actual (req.user desde auth.middleware).
 *
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 */
export function me(req, res) {
  const sub = req.user?.sub;
  if (!sub) {
    return res.status(401).json({
      message: 'Authentication required',
      code: 'UNAUTHORIZED',
    });
  }
  const userId = Number.parseInt(String(sub), 10);
  if (Number.isNaN(userId)) {
    return res.status(401).json({
      message: 'Invalid token payload',
      code: 'INVALID_TOKEN',
    });
  }
  const record = userRepository.findById(userId);
  if (!record) {
    return res.status(401).json({
      message: 'User no longer exists',
      code: 'INVALID_TOKEN',
    });
  }
  return res.status(200).json(userRepository.toPublicUser(record));
}
