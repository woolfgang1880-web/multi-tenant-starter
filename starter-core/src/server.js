import { env } from './config/env.js';
import app from './app.js';
import { logger } from './shared/utils/logger.js';

app.listen(env.port, () => {
  logger.info(`User API (Express) listening on http://localhost:${env.port}/api`);
});
