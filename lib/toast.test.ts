import { describe, expect, it, vi } from "vitest";
import { toast } from "sonner";
import { notifyError, notifySuccess } from "./toast";

vi.mock("sonner", () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

describe("notifySuccess", () => {
  it("shows a success toast with the given message", () => {
    notifySuccess("Pedido criado com sucesso.");
    expect(toast.success).toHaveBeenCalledWith("Pedido criado com sucesso.");
  });
});

describe("notifyError", () => {
  it("shows an error toast with the given message", () => {
    notifyError("Não foi possível salvar a alteração.");
    expect(toast.error).toHaveBeenCalledWith("Não foi possível salvar a alteração.");
  });

  it("falls back to a generic message that never echoes technical detail", () => {
    notifyError();
    expect(toast.error).toHaveBeenCalledWith("Não foi possível concluir a ação. Tente novamente.");
  });
});
