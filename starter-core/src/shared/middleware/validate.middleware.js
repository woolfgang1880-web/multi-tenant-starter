/**
 * Ejecuta un validador síncrono. Si devuelve { ok: false, error }, responde 400.
 * Si { ok: true, value }, asigna req.validated[key] = value (opcional).
 *
 * @param {(input: unknown) => { ok: boolean; value?: unknown; error?: object }} validator
 * @param {{ source?: 'body' | 'query' | 'params'; key?: string }} [opts]
 */
export function validate(validator, opts = {}) {
  const source = opts.source ?? 'body';
  const key = opts.key ?? 'payload';

  return (req, res, next) => {
    const input = req[source];
    const result = validator(input);
    if (!result.ok) {
      return res.status(400).json(result.error);
    }
    if (!req.validated) {
      req.validated = {};
    }
    req.validated[key] = result.value;
    return next();
  };
}
