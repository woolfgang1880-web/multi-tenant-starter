function normalizeSlug(role) {
  if (!role) return ''
  if (typeof role === 'string') return role.trim().toLowerCase()
  if (typeof role.slug === 'string') return role.slug.trim().toLowerCase()
  return ''
}

function hasAnyRole(user, allowedSlugs) {
  const slugs = new Set(getRoleSlugs(user))
  return allowedSlugs.some((slug) => slugs.has(slug))
}

function hasAnyPermission(user, allowedPermissions) {
  const perms = getPermissions(user)
  return allowedPermissions.some((p) => perms.has(p))
}

export function getRoleSlugs(user) {
  const roles = Array.isArray(user?.roles) ? user.roles : []
  return roles.map(normalizeSlug).filter(Boolean)
}

export function getPermissions(user) {
  const direct = Array.isArray(user?.permissions) ? user.permissions : []
  const abilities = Array.isArray(user?.abilities) ? user.abilities : []
  const fromRoles = (Array.isArray(user?.roles) ? user.roles : []).flatMap((r) =>
    Array.isArray(r?.abilities) ? r.abilities : [],
  )

  return new Set([...direct, ...abilities, ...fromRoles].filter((p) => typeof p === 'string' && p.trim()))
}

/**
 * UX-only: controla visibilidad de opciones; backend sigue siendo el enforcement real.
 */
export function canManageUsers(user) {
  return canViewUsers(user)
}

export function canViewUsers(user) {
  return hasAnyRole(user, ['admin', 'super_admin']) || hasAnyPermission(user, ['manage-users'])
}

export function canCreateUser(user) {
  return canViewUsers(user)
}

export function canEditUser(user) {
  return canViewUsers(user)
}

export function canDeactivateUser(user) {
  return canViewUsers(user)
}

