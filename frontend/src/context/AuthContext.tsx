"use client";

import React, { createContext, useContext, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useRouter } from "next/navigation";

export interface User {
  id: number;
  name: string;
  username: string;
  email: string;
  role: string;
  status: string;
  referral_code: string | null;
  is_email_verified: boolean;
  email_verified_at?: string | null;
  created_at?: string;
}

interface AuthResponseData {
  user: User;
  token: string;
  token_type: string;
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (credentials: Record<string, unknown>) => Promise<void>;
  register: (data: Record<string, unknown>) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  resendVerification: () => Promise<string>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    const savedToken = localStorage.getItem("gpt_auth_token");
    if (savedToken) {
      setToken(savedToken);
      fetchUserProfile(savedToken);
    } else {
      setIsLoading(false);
    }
  }, []);

  const fetchUserProfile = async (authToken?: string) => {
    try {
      const res = await api.get<{ user: User }>("/api/v1/auth/me", {
        headers: authToken ? { Authorization: `Bearer ${authToken}` } : undefined,
      });
      if (res.data?.user) {
        setUser(res.data.user);
      }
    } catch (err) {
      if (err instanceof ApiError && err.statusCode === 401) {
        localStorage.removeItem("gpt_auth_token");
        setToken(null);
        setUser(null);
      }
    } finally {
      setIsLoading(false);
    }
  };

  const login = async (credentials: Record<string, unknown>) => {
    const res = await api.post<AuthResponseData>("/api/v1/auth/login", credentials);
    if (res.data) {
      const newToken = res.data.token;
      localStorage.setItem("gpt_auth_token", newToken);
      setToken(newToken);
      setUser(res.data.user);
      router.push("/dashboard");
    }
  };

  const register = async (data: Record<string, unknown>) => {
    const res = await api.post<AuthResponseData>("/api/v1/auth/register", data);
    if (res.data) {
      const newToken = res.data.token;
      localStorage.setItem("gpt_auth_token", newToken);
      setToken(newToken);
      setUser(res.data.user);
      router.push("/verify-email");
    }
  };

  const logout = async () => {
    try {
      await api.post("/api/v1/auth/logout");
    } catch {
      // Ignore network errors during logout
    } finally {
      localStorage.removeItem("gpt_auth_token");
      setToken(null);
      setUser(null);
      router.push("/login");
    }
  };

  const refreshUser = async () => {
    await fetchUserProfile();
  };

  const resendVerification = async (): Promise<string> => {
    const res = await api.post("/api/v1/auth/email/resend");
    return res.message || "Verification email sent.";
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        isLoading,
        isAuthenticated: !!user,
        login,
        register,
        logout,
        refreshUser,
        resendVerification,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
