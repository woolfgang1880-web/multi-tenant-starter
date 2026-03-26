export const logger = {
  info: (...args) => {
    console.log('[INFO]', new Date().toISOString(), ...args);
  },
  error: (...args) => {
    console.error('[ERROR]', new Date().toISOString(), ...args);
  },
  /** Logs de seguridad (auth, rate-limit, etc.) — no incluir datos sensibles. */
  security: (event, meta = {}) => {
    const payload = { event, ...meta, ts: new Date().toISOString() };
    console.warn('[SECURITY]', JSON.stringify(payload));
  },
};
