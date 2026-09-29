import client from "./client";

export interface User {
  id: number;
  name: string;
  email: string;
}

export const register = (data: { name: string; email: string; password: string; password_confirmation: string }) =>
  client.post<{ message: string; user: User; token: string }>("/register", data).then((r) => r.data);

export const login = (data: { email: string; password: string }) =>
  client.post<{ message: string; user: User; token: string }>("/login", data).then((r) => r.data);

export const logout = () => client.post("/logout").then((r) => r.data);

export const me = () => client.get<{ user: User }>("/me").then((r) => r.data);