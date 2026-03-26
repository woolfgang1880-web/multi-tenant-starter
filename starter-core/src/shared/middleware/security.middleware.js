import { rateLimit } from 'express-rate-limit';
import { logger } from '../utils/logger.js';

/**
 * Rate limit para endpoints de auth (login, register).
 * En desarrollo: más permisivo; en producción: más estricto.
 */
const authLimit = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: process.env.NODE_ENV === 'production' ? 10 : 100,
  message: {
    message: 'Too many authentication attempts',
    code: 'RATE_LIMIT_EXCEEDED',
  },
  standardHeaders: true,
  legacyHeaders: false,
  handler: (req, res, next, opts) => {
    logger.security('auth.rate_limit', {
      ip: req.ip ?? req.socket?.remoteAddress,
      path: req.path,
    });
    res.status(429).json(opts.message);
  },
});

/**
 * Headers básicos de seguridad (sin sobreingeniería).
 */
export function securityHeaders(req, res, next) {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'DENY');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  next();
}

export { authLimit };
