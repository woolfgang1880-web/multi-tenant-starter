import { logger } from '../utils/logger.js';

export function notFoundHandler(req, res) {
  res.status(404).json({
    message: 'Not found',
    code: 'NOT_FOUND',
  });
}

/**
 * @param {Error} err
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 * @param {import('express').NextFunction} next
 */
export function errorHandler(err, req, res, next) {
  if (res.headersSent) {
    return next(err);
  }
  logger.error(err.message, err.stack);
  return res.status(500).json({
    message: 'Internal server error',
    code: 'INTERNAL_ERROR',
  });
}
