"use server";

import { redirect } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { signOut } from "./service";

/** Signs the current user out and sends them back to the login screen. */
export async function logout(): Promise<void> {
  const db = await createClient();
  await signOut(db);
  redirect("/login");
}
