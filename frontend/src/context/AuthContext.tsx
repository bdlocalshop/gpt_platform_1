"use client";

import React, { createContext, useContext, useEffect, useState, useCallback } from "react";
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

export interface WalletBalance {
  points: {
    available: string;
    locked: string;
    total: string;
  };
  fiat_equivalent: {
    currency: string;
    rate: string;
    available: string;
    locked: string;
    total: string;
  };
}

interface AuthResponseData {
  user: User;
  token: string;
  token_type: string;
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  walletBalance: WalletBalance | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (credentials: Record<string, unknown>) => Promise<void>;
  register: (data: Record<string, unknown>) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  refreshBalance: () => Promise<void>;
  resendVerification: () => Promise<string>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [walletBalance, setWalletBalance] = useState<WalletBalance | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();

  const fetchWalletBalance = useCallback(async (authToken?: string) => {
    try {
      const res = await api.get<WalletBalance>("/api/v1/wallet/balance", {
        headers: authToken ? { Authorization: `Bearer ${authToken}` } : undefined,
      });
      if (res.data) {
        setWalletBalance(res.data);
      }
    } catch {
      // Ignore wallet fetch errors
    }
  }, []);

  const fetchUserProfile = useCallback(async (authToken?: string) => {
    try {
      const res = await api.get<{ user: User }>("/api/v1/auth/me", {
        headers: authToken ? { Authorization: `Bearer ${authToken}` } : undefined,
      });
      if (res.data?.user) {
        setUser(res.data.user);
        await fetchWalletBalance(authToken);
      }
    } catch (err) {
      if (err instanceof ApiError && err.statusCode === 401) {
        localStorage.removeItem("gpt_auth_token");
        setToken(null);
        setUser(null);
        setWalletBalance(null);
      }
    } finally {
      setIsLoading(false);
    }
  }, [fetchWalletBalance]);

  useEffect(() => {
    const savedToken = localStorage.getItem("gpt_auth_token");
    if (savedToken) {
      setToken(savedToken);
      fetchUserProfile(savedToken);
    } else {
      setIsLoading(false);
    }
  }, [fetchUserProfile]);

  const login = async (credentials: Record<string, unknown>) => {
    const res = await api.post<AuthResponseData>("/api/v1/auth/login", credentials);
    if (res.data) {
      const newToken = res.data.token;
      localStorage.setItem("gpt_auth_token", newToken);
      setToken(newToken);
      setUser(res.data.user);
      await fetchWalletBalance(newToken);
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
      await fetchWalletBalance(newToken);
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
      setWalletBalance(null);
      router.push("/login");
    }
  };

  const refreshUser = async () => {
    await fetchUserProfile();
  };

  const refreshBalance = async () => {
    await fetchWalletBalance();
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
        walletBalance,
        isLoading,
        isAuthenticated: !!user,
        login,
        register,
        logout,
        refreshUser,
        refreshBalance,
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
