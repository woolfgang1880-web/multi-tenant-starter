import * as usersService from './users.service.js';
import * as usersSchema from './users.schema.js';

/**
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 */
export function listUsers(req, res) {
  const parsed = usersSchema.parseListUsersQuery(req.query);
  if (!parsed.ok) {
    return res.status(400).json(parsed.error);
  }

  try {
    const result = usersService.listUsers(parsed.value);
    return res.status(200).json(result);
  } catch {
    return res.status(500).json({
      message: 'Internal server error',
      code: 'INTERNAL_ERROR',
    });
  }
}

/**
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 */
export function createUser(req, res) {
  const v = usersSchema.validateUserBody(req.body, { partial: false });
  if (!v.ok) {
    return res.status(400).json(v.error);
  }

  const { name, email, age } = v.value;
  if (typeof name !== 'string' || typeof email !== 'string') {
    return res.status(400).json(
      usersSchema.validationError('name', 'required fields missing'),
    );
  }

  try {
    const user = usersService.createUser({
      name,
      email,
      age: age !== undefined && age !== null ? age : undefined,
    });
    return res.status(201).json(user);
  } catch (e) {
    if (e && typeof e === 'object' && e.code === 'EMAIL_ALREADY_EXISTS') {
      return res.status(409).json({
        message: 'Email already registered',
        code: 'EMAIL_ALREADY_EXISTS',
      });
    }
    return res.status(500).json({
      message: 'Internal server error',
      code: 'INTERNAL_ERROR',
    });
  }
}

/**
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 */
export function getUserById(req, res) {
  const idParsed = usersSchema.parseUserIdParam(req.params.id);
  if (!idParsed.ok) {
    return res.status(400).json(idParsed.error);
  }

  const user = usersService.getUserById(idParsed.value);
  if (!user) {
    return res.status(404).json({
      message: 'User not found',
      code: 'USER_NOT_FOUND',
    });
  }
  return res.status(200).json(user);
}

/**
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 */
export function updateUser(req, res) {
  const idParsed = usersSchema.parseUserIdParam(req.params.id);
  if (!idParsed.ok) {
    return res.status(400).json(idParsed.error);
  }

  const v = usersSchema.validateUserBody(req.body, { partial: false });
  if (!v.ok) {
    return res.status(400).json(v.error);
  }

  const { name, email, age } = v.value;
  if (typeof name !== 'string' || typeof email !== 'string') {
    return res.status(400).json(
      usersSchema.validationError('name', 'required fields missing'),
    );
  }

  try {
    const updated = usersService.updateUser(idParsed.value, {
      name,
      email,
      age: age !== undefined && age !== null ? age : undefined,
    });
    if (!updated) {
      return res.status(404).json({
        message: 'User not found',
        code: 'USER_NOT_FOUND',
      });
    }
    return res.status(200).json(updated);
  } catch (e) {
    if (e && typeof e === 'object' && e.code === 'EMAIL_ALREADY_EXISTS') {
      return res.status(409).json({
        message: 'Email already registered',
        code: 'EMAIL_ALREADY_EXISTS',
      });
    }
    return res.status(500).json({
      message: 'Internal server error',
      code: 'INTERNAL_ERROR',
    });
  }
}

/**
 * @param {import('express').Request} req
 * @param {import('express').Response} res
 */
export function deleteUser(req, res) {
  const idParsed = usersSchema.parseUserIdParam(req.params.id);
  if (!idParsed.ok) {
    return res.status(400).json(idParsed.error);
  }

  const ok = usersService.deleteUser(idParsed.value);
  if (!ok) {
    return res.status(404).json({
      message: 'User not found',
      code: 'USER_NOT_FOUND',
    });
  }
  return res.status(204).send();
}
