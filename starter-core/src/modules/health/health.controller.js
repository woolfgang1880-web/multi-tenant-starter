/**
 * GET /api/health — público (OpenAPI HealthResponse).
 *
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 */
export function getHealth(req, res) {
  res.status(200).json({
    status: 'ok',
    uptime: process.uptime(),
  });
}
