"use client";

import { useActionState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { login, type LoginState } from "./actions";

const initialState: LoginState = {};

export default function LoginPage() {
  const router = useRouter();
  const [state, formAction, isPending] = useActionState(login, initialState);

  useEffect(() => {
    if (state.redirectTo) {
      router.push(state.redirectTo);
    }
  }, [state.redirectTo, router]);

  return (
    <div className="flex flex-1 items-center justify-center p-8">
      <form
        action={formAction}
        className="border-border bg-card flex w-full max-w-sm flex-col gap-4 rounded-lg border p-6"
      >
        <div className="flex flex-col gap-1">
          <h1 className="text-lg font-semibold tracking-tight">Entrar</h1>
          <p className="text-muted-foreground text-sm">
            Acesse com o e-mail e senha fornecidos pela sua organização.
          </p>
        </div>

        <div className="flex flex-col gap-1.5">
          <label htmlFor="email" className="text-sm font-medium">
            E-mail
          </label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="email"
            required
            className="border-input bg-background h-9 rounded-md border px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
          />
        </div>

        <div className="flex flex-col gap-1.5">
          <label htmlFor="password" className="text-sm font-medium">
            Senha
          </label>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            required
            className="border-input bg-background h-9 rounded-md border px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
          />
        </div>

        {state.error ? (
          <p role="alert" className="text-destructive text-sm">
            {state.error}
          </p>
        ) : null}

        <Button type="submit" disabled={isPending} className="mt-2 w-full">
          {isPending ? "Entrando..." : "Entrar"}
        </Button>
      </form>
    </div>
  );
}
