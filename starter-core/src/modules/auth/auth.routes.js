import { Router } from 'express';
import { authenticateToken } from '../../shared/middleware/auth.middleware.js';
import { authLimit } from '../../shared/middleware/security.middleware.js';
import * as authController from './auth.controller.js';

const router = Router();

router.post('/register', authLimit, authController.register);
router.post('/login', authLimit, authController.login);
router.post('/refresh', authController.refresh);
router.get('/me', authenticateToken, authController.me);

export default router;
