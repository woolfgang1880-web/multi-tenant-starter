import { verifyAccessToken } from '../utils/jwt.js';

function getBearerToken(req) {
  const header = req.headers.authorization;
  if (!header || typeof header !== 'string') {
    return null;
  }
  const [scheme, token] = header.split(/\s+/);
  if (!scheme || scheme.toLowerCase() !== 'bearer' || !token) {
    return null;
  }
  return token;
}

/**
 * Verifica JWT de acceso (ACCESS_TOKEN_SECRET). Asigna req.user al payload.
 */
export function authenticateToken(req, res, next) {
  const token = getBearerToken(req);
  if (!token) {
    return res.status(401).json({
      message: 'Authentication required',
      code: 'UNAUTHORIZED',
    });
  }

  try {
    const payload = verifyAccessToken(token);
    req.user = payload;
    return next();
  } catch {
    return res.status(401).json({
      message: 'Invalid or expired access token',
      code: 'INVALID_TOKEN',
    });
  }
}
