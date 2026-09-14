export function landingRouteForRoles(roles = []) {
    if (roles.includes('superadmin')) return { name: 'superadmin-dashboard' };
    if (roles.includes('admin')) return { name: 'admin-dashboard' };
    if (roles.includes('lecturer')) return { name: 'lecturer-dashboard' };
    if (roles.includes('student')) return { name: 'student-dashboard' };
    return { name: 'login' };
}

export function canAccessRoles(userRoles = [], requiredRoles = []) {
    return requiredRoles.length === 0 || requiredRoles.some((role) => userRoles.includes(role));
}
