import { toast } from "sonner";

const GENERIC_ERROR_MESSAGE = "Não foi possível concluir a ação. Tente novamente.";

/** Success feedback for any mutation (criação, alteração, cancelamento). */
export function notifySuccess(message: string): void {
  toast.success(message);
}

/**
 * Error feedback for any mutation. `message` must already be a safe,
 * user-facing string — never pass a caught error's `.message` straight
 * through, since that can leak internal/database detail. Omit it to fall
 * back to a generic message.
 */
export function notifyError(message: string = GENERIC_ERROR_MESSAGE): void {
  toast.error(message);
}
