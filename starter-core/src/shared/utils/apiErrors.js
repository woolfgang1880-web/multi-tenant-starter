export function validationError(field, issue) {
  return {
    message: 'Validation failed',
    code: 'VALIDATION_ERROR',
    details: { field, issue },
  };
}
