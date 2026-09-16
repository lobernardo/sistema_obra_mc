/** Credentials were missing, invalid, or the resulting session has no usable profile. */
export class UnauthorizedError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "UnauthorizedError";
  }
}
