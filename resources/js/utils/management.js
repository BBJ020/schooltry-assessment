export function tenantUserPayload(input = {}) {
    return {
        name: input.name ?? '',
        email: input.email ?? '',
        role: input.role ?? '',
    };
}
