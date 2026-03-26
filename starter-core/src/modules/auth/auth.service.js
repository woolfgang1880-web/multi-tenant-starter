import bcrypt from 'bcryptjs';
import {
  signAccessToken,
  signRefreshToken,
  verifyRefreshToken,
} from '../../shared/utils/jwt.js';
import * as userRepository from '../users/user.repository.js';

const SALT_ROUNDS = 10;

function issueTokens(userId) {
  const sub = String(userId);
  return {
    accessToken: signAccessToken({ sub }),
    refreshToken: signRefreshToken({ sub }),
    tokenType: 'Bearer',
  };
}

/**
 * @param {{ name: string; email: string; password: string; age?: number }} input
 */
export async function register(input) {
  const emailN = userRepository.normalizeEmail(input.email);
  if (userRepository.findByEmail(emailN)) {
    const err = new Error('Email already registered');
    err.code = 'EMAIL_ALREADY_EXISTS';
    throw err;
  }

  const passwordHash = await bcrypt.hash(input.password, SALT_ROUNDS);
  const age =
    input.age !== undefined && input.age !== null ? input.age : null;

  const record = userRepository.insertUser({
    name: input.name,
    email: emailN,
    age,
    passwordHash,
  });

  const user = userRepository.toPublicUser(record);
  const tokens = issueTokens(record.id);
  return { ...tokens, user };
}

/**
 * @param {{ email: string; password: string }} input
 */
export async function login(input) {
  const emailN = userRepository.normalizeEmail(input.email);
  const record = userRepository.findByEmail(emailN);

  if (!record?.passwordHash) {
    const err = new Error('Invalid credentials');
    err.code = 'INVALID_CREDENTIALS';
    throw err;
  }

  const match = await bcrypt.compare(input.password, record.passwordHash);
  if (!match) {
    const err = new Error('Invalid credentials');
    err.code = 'INVALID_CREDENTIALS';
    throw err;
  }

  const user = userRepository.toPublicUser(record);
  return { ...issueTokens(record.id), user };
}

/**
 * @param {{ refreshToken: string }} input
 */
export async function refresh(input) {
  let decoded;
  try {
    decoded = verifyRefreshToken(input.refreshToken);
  } catch {
    const err = new Error('Invalid or expired refresh token');
    err.code = 'INVALID_TOKEN';
    throw err;
  }

  const userId = Number.parseInt(String(decoded.sub), 10);
  if (Number.isNaN(userId)) {
    const err = new Error('Invalid or expired refresh token');
    err.code = 'INVALID_TOKEN';
    throw err;
  }

  const record = userRepository.findById(userId);
  if (!record) {
    const err = new Error('Invalid or expired refresh token');
    err.code = 'INVALID_TOKEN';
    throw err;
  }

  const accessToken = signAccessToken({ sub: String(userId) });
  return {
    accessToken,
    tokenType: 'Bearer',
  };
}
