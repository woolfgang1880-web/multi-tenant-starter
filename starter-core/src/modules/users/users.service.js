import * as userRepository from './user.repository.js';

/**
 * @param {string | undefined} search
 */
function matchesSearch(user, search) {
  if (!search) {
    return true;
  }
  const q = search.trim().toLowerCase();
  return (
    user.name.toLowerCase().includes(q) ||
    user.email.toLowerCase().includes(q)
  );
}

/**
 * @param {{ page: number; limit: number; search?: string }} params
 */
export function listUsers({ page, limit, search }) {
  const all = userRepository.getAllUsers();
  const filtered = all.filter((u) => matchesSearch(u, search));
  const total = filtered.length;
  const totalPages = limit > 0 ? Math.ceil(total / limit) : 0;
  const offset = (page - 1) * limit;
  const slice = filtered.slice(offset, offset + limit);
  const data = slice.map((u) => userRepository.toPublicUser(u));

  return {
    data,
    page,
    limit,
    total,
    totalPages,
  };
}

/**
 * @param {{ name: string; email: string; age?: number }} input
 */
export function createUser(input) {
  const age =
    input.age !== undefined && input.age !== null ? input.age : null;
  const record = userRepository.insertUser({
    name: input.name,
    email: input.email,
    age,
    passwordHash: null,
  });
  return userRepository.toPublicUser(record);
}

/**
 * @param {number} id
 */
export function getUserById(id) {
  const record = userRepository.findById(id);
  return userRepository.toPublicUser(record);
}

/**
 * @param {number} id
 * @param {{ name: string; email: string; age?: number }} input
 */
export function updateUser(id, input) {
  const age =
    input.age !== undefined && input.age !== null ? input.age : null;
  const record = userRepository.updateUserRecord(id, {
    name: input.name,
    email: input.email,
    age,
  });
  if (!record) {
    return null;
  }
  return userRepository.toPublicUser(record);
}

/**
 * @param {number} id
 */
export function deleteUser(id) {
  return userRepository.deleteUserRecord(id);
}
