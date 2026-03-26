import 'dotenv/config';

const DEV_ACCESS_SECRET = 'dev-access-token-secret-change-me';
const DEV_REFRESH_SECRET = 'dev-refresh-token-secret-change-me';

function requireSecretInProduction(name, value, devFallback) {
  const isProd = process.env.NODE_ENV === 'production';
  if (isProd && (!value || value === devFallback)) {
    throw new Error(
      `[env] ${name} debe estar definido en producción. No usar secretos por defecto.`,
    );
  }
  return value || devFallback;
}

export const env = {
  nodeEnv: process.env.NODE_ENV ?? 'development',
  port: Number.parseInt(process.env.PORT ?? '8000', 10),
  accessTokenSecret: requireSecretInProduction(
    'ACCESS_TOKEN_SECRET',
    process.env.ACCESS_TOKEN_SECRET,
    DEV_ACCESS_SECRET,
  ),
  refreshTokenSecret: requireSecretInProduction(
    'REFRESH_TOKEN_SECRET',
    process.env.REFRESH_TOKEN_SECRET,
    DEV_REFRESH_SECRET,
  ),
  passwordMinLength: Number.parseInt(
    process.env.PASSWORD_MIN_LENGTH ?? '8',
    10,
  ),
};
