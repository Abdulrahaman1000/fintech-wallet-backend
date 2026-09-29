import { createContext, useContext, useEffect, useState, ReactNode } from "react";
import * as authApi from "../api/auth";
import type { User } from "../api/auth";

interface AuthContextValue {
  user: User | null;
  loading: boolean;
  login: (data: { email: string; password: string }) => Promise<void>;
  register: (data: { name: string; email: string; password: string; password_confirmation: string }) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(() => {
    const stored = localStorage.getItem("auth_user");
    return stored ? JSON.parse(stored) : null;
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem("auth_token");
    if (!token) {
      setLoading(false);
      return;
    }
    authApi
      .me()
      .then((res) => setUser(res.user))
      .catch(() => {
        localStorage.removeItem("auth_token");
        localStorage.removeItem("auth_user");
        setUser(null);
      })
      .finally(() => setLoading(false));
  }, []);

  async function login(data: { email: string; password: string }) {
    const res = await authApi.login(data);
    localStorage.setItem("auth_token", res.token);
    localStorage.setItem("auth_user", JSON.stringify(res.user));
    setUser(res.user);
  }

  async function register(data: { name: string; email: string; password: string; password_confirmation: string }) {
    const res = await authApi.register(data);
    localStorage.setItem("auth_token", res.token);
    localStorage.setItem("auth_user", JSON.stringify(res.user));
    setUser(res.user);
  }

  async function logout() {
    try {
      await authApi.logout();
    } finally {
      localStorage.removeItem("auth_token");
      localStorage.removeItem("auth_user");
      setUser(null);
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}