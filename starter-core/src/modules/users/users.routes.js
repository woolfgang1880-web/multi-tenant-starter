import { Router } from 'express';
import { authenticateToken } from '../../shared/middleware/auth.middleware.js';
import * as usersController from './users.controller.js';

const router = Router();

router.use(authenticateToken);

router.get('/', usersController.listUsers);
router.post('/', usersController.createUser);
router.get('/:id', usersController.getUserById);
router.put('/:id', usersController.updateUser);
router.delete('/:id', usersController.deleteUser);

export default router;
