import express from 'express';
import cors from 'cors';
import healthRoutes from './modules/health/health.routes.js';
import authRoutes from './modules/auth/auth.routes.js';
import usersRoutes from './modules/users/users.routes.js';
import { notFoundHandler, errorHandler } from './shared/middleware/error.middleware.js';
import { securityHeaders, authLimit } from './shared/middleware/security.middleware.js';

const app = express();

app.use(securityHeaders);
app.use(cors());
app.use(express.json({ limit: '100kb' }));

app.use('/api/health', healthRoutes);
app.use('/api/auth', authRoutes);
app.use('/api/users', usersRoutes);

app.use(notFoundHandler);
app.use(errorHandler);

export default app;
