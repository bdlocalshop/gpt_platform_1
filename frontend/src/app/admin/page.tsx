"use client";

import React, { useState, useEffect, useCallback } from "react";
import { useAuth } from "@/context/AuthContext";
import { api, ApiError } from "@/lib/api";
import {
  ShieldAlert,
  ShieldCheck,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Clock,
  Coins,
  Users,
  Wallet,
  Search,
  Filter,
  RefreshCw,
  Ban,
  Check,
  ChevronRight,
  Sparkles,
  Lock,
} from "lucide-react";
import Link from "next/link";

interface AdminStats {
  users: {
    total: number;
    active: number;
    suspended: number;
    banned: number;
  };
  payouts: {
    pending_count: number;
    pending_usd: string;
    completed_count: number;
    completed_usd: string;
  };
  security: {
    unresolved_fraud_flags: number;
  };
  ledger_integrity: {
    is_reconciled: boolean;
    total_debits: string;
    total_credits: string;
    discrepancy: string;
  };
}

interface AdminPayout {
  id: number;
  uuid: string;
  asset: string;
  crypto_address: string;
  points_amount: string;
  usd_amount: string;
  status: "pending" | "completed" | "rejected";
  tx_hash?: string;
  created_at: string;
  user: {
    id: number;
    username: string;
    email: string;
    status: string;
    fraud_flags?: unknown[];
  };
}

interface AdminUser {
  id: number;
  name: string;
  username: string;
  email: string;
  role: string;
  status: string;
  points_available: string;
  points_locked: string;
  is_email_verified: boolean;
  registration_ip: string;
  unresolved_fraud_count: number;
  created_at: string;
}

interface FraudFlagItem {
  id: number;
  flag_type: string;
  severity: string;
  details: Record<string, unknown>;
  is_resolved: boolean;
  created_at: string;
  user: {
    id: number;
    username: string;
    email: string;
    status: string;
  };
}

export default function AdminPage() {
  const { user, isAuthenticated } = useAuth();

  const [stats, setStats] = useState<AdminStats | null>(null);
  const [payouts, setPayouts] = useState<AdminPayout[]>([]);
  const [usersList, setUsersList] = useState<AdminUser[]>([]);
  const [fraudFlags, setFraudFlags] = useState<FraudFlagItem[]>([]);

  const [activeTab, setActiveTab] = useState<"payouts" | "users" | "fraud">("payouts");
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [message, setMessage] = useState<{ text: string; type: "success" | "error" } | null>(null);

  const [userSearch, setUserSearch] = useState("");

  const loadAdminData = useCallback(async () => {
    try {
      setLoading(true);
      const [statsRes, payoutsRes, usersRes, flagsRes] = await Promise.all([
        api.get<AdminStats>("/api/v1/admin/stats"),
        api.get<{ payouts: AdminPayout[] }>("/api/v1/admin/payouts?status=pending"),
        api.get<{ users: AdminUser[] }>("/api/v1/admin/users"),
        api.get<{ fraud_flags: FraudFlagItem[] }>("/api/v1/admin/fraud-flags"),
      ]);

      if (statsRes.data) setStats(statsRes.data);
      if (payoutsRes.data?.payouts) setPayouts(payoutsRes.data.payouts);
      if (usersRes.data?.users) setUsersList(usersRes.data.users);
      if (flagsRes.data?.fraud_flags) setFraudFlags(flagsRes.data.fraud_flags);
    } catch (err: unknown) {
      if (err instanceof Error) {
        setMessage({ text: err.message, type: "error" });
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isAuthenticated && user?.role === "admin") {
      loadAdminData();
    } else {
      setLoading(false);
    }
  }, [isAuthenticated, user, loadAdminData]);

  const handleApprovePayout = async (id: number) => {
    try {
      setActionLoading(id);
      const res = await api.post(`/api/v1/admin/payouts/${id}/approve`, {
        notes: "Approved via Admin Dashboard",
      });
      setMessage({ text: res.message || "Payout approved and disbursed successfully.", type: "success" });
      await loadAdminData();
    } catch (err: unknown) {
      if (err instanceof Error) {
        setMessage({ text: err.message, type: "error" });
      }
    } finally {
      setActionLoading(null);
    }
  };

  const handleRejectPayout = async (id: number) => {
    const reason = prompt("Enter rejection reason for this refund:");
    if (!reason || !reason.trim()) return;

    try {
      setActionLoading(id);
      const res = await api.post(`/api/v1/admin/payouts/${id}/reject`, {
        reason: reason.trim(),
      });
      setMessage({ text: res.message || "Payout rejected and points refunded.", type: "success" });
      await loadAdminData();
    } catch (err: unknown) {
      if (err instanceof Error) {
        setMessage({ text: err.message, type: "error" });
      }
    } finally {
      setActionLoading(null);
    }
  };

  const handleToggleUserStatus = async (id: number, currentStatus: string) => {
    const nextStatus = currentStatus === "banned" ? "active" : "banned";
    if (!confirm(`Are you sure you want to change user status to '${nextStatus}'?`)) return;

    try {
      setActionLoading(id);
      const res = await api.post(`/api/v1/admin/users/${id}/status`, {
        status: nextStatus,
      });
      setMessage({ text: res.message || `User status changed to ${nextStatus}.`, type: "success" });
      await loadAdminData();
    } catch (err: unknown) {
      if (err instanceof Error) {
        setMessage({ text: err.message, type: "error" });
      }
    } finally {
      setActionLoading(null);
    }
  };

  const handleResolveFlag = async (id: number) => {
    try {
      setActionLoading(id);
      const res = await api.post(`/api/v1/admin/fraud-flags/${id}/resolve`);
      setMessage({ text: res.message || "Fraud flag marked as resolved.", type: "success" });
      await loadAdminData();
    } catch (err: unknown) {
      if (err instanceof Error) {
        setMessage({ text: err.message, type: "error" });
      }
    } finally {
      setActionLoading(null);
    }
  };

  // Filtered Users
  const filteredUsers = usersList.filter((u) => {
    const q = userSearch.toLowerCase();
    return u.username.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
  });

  if (!isAuthenticated || user?.role !== "admin") {
    return (
      <div className="max-w-4xl mx-auto px-4 py-24 text-center">
        <div className="w-16 h-16 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center mx-auto mb-6">
          <ShieldAlert className="w-8 h-8" />
        </div>
        <h1 className="text-3xl font-extrabold text-slate-900 dark:text-white mb-3">
          Restricted Administrator Access
        </h1>
        <p className="text-slate-600 dark:text-slate-400 max-w-md mx-auto mb-8">
          This portal is reserved strictly for platform administrators. Sign in with an authorized admin account.
        </p>
        <Link
          href="/login"
          className="inline-flex px-6 py-2.5 rounded-xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold transition"
        >
          Return to Login
        </Link>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-xs font-semibold mb-2 border border-indigo-500/20">
            <ShieldCheck className="w-3.5 h-3.5" />
            Platform Control Center
          </div>
          <h1 className="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
            Compliance & Operations Panel
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Signed in as Admin: <span className="font-bold text-slate-900 dark:text-white">@{user.username}</span>
          </p>
        </div>

        <button
          onClick={loadAdminData}
          disabled={loading}
          className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60 shadow-sm transition disabled:opacity-50"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin text-emerald-500" : ""}`} />
          Refresh Stats & Queues
        </button>
      </div>

      {message && (
        <div
          className={`p-4 rounded-2xl text-xs sm:text-sm font-semibold flex items-center justify-between ${
            message.type === "success"
              ? "bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400"
              : "bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400"
          }`}
        >
          <span>{message.text}</span>
          <button onClick={() => setMessage(null)} className="text-slate-400 hover:text-white">
            Dismiss
          </button>
        </div>
      )}

      {/* Metrics Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
              Pending Payout Queue
            </span>
            <div className="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center">
              <Clock className="w-4 h-4" />
            </div>
          </div>
          <p className="text-3xl font-black text-slate-900 dark:text-white">
            {stats?.payouts.pending_count || 0}
          </p>
          <p className="text-xs font-bold text-amber-500 mt-2">
            ${stats?.payouts.pending_usd || "0.00"} USD awaiting approval
          </p>
        </div>

        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
              Disbursed Volume
            </span>
            <div className="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
              <Coins className="w-4 h-4" />
            </div>
          </div>
          <p className="text-3xl font-black text-emerald-600 dark:text-emerald-400">
            ${stats?.payouts.completed_usd || "0.00"}
          </p>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-2">
            {stats?.payouts.completed_count || 0} settled disbursements
          </p>
        </div>

        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
              Registered Accounts
            </span>
            <div className="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
              <Users className="w-4 h-4" />
            </div>
          </div>
          <p className="text-3xl font-black text-slate-900 dark:text-white">
            {stats?.users.total || 0}
          </p>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-2">
            {stats?.users.active || 0} active • {stats?.users.banned || 0} banned
          </p>
        </div>

        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">
              Ledger & Fraud State
            </span>
            <div className="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-500 flex items-center justify-center">
              <ShieldAlert className="w-4 h-4" />
            </div>
          </div>
          <p className="text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2">
            {stats?.ledger_integrity.is_reconciled ? (
              <span className="text-emerald-500 text-lg font-bold flex items-center gap-1">
                <Check className="w-5 h-5" /> Reconciled
              </span>
            ) : (
              <span className="text-rose-500 text-lg font-bold">Imbalanced</span>
            )}
          </p>
          <p className="text-xs text-rose-500 font-bold mt-2">
            {stats?.security.unresolved_fraud_flags || 0} unresolved fraud flags
          </p>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="flex gap-2 border-b border-slate-200 dark:border-slate-800 pb-4">
        <button
          onClick={() => setActiveTab("payouts")}
          className={`px-5 py-2.5 rounded-xl text-xs font-bold transition ${
            activeTab === "payouts"
              ? "bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-md"
              : "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700"
          }`}
        >
          Pending Payout Queue ({payouts.length})
        </button>
        <button
          onClick={() => setActiveTab("users")}
          className={`px-5 py-2.5 rounded-xl text-xs font-bold transition ${
            activeTab === "users"
              ? "bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-md"
              : "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700"
          }`}
        >
          User Account Directory ({usersList.length})
        </button>
        <button
          onClick={() => setActiveTab("fraud")}
          className={`px-5 py-2.5 rounded-xl text-xs font-bold transition ${
            activeTab === "fraud"
              ? "bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-md"
              : "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700"
          }`}
        >
          Fraud Flags & Holds ({fraudFlags.length})
        </button>
      </div>

      {/* TAB 1: PENDING PAYOUTS QUEUE */}
      {activeTab === "payouts" && (
        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
          <div className="p-6 border-b border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-white">
              Withdrawals Awaiting Administrative Sign-Off
            </h2>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Verify compliance, address integrity, and conversion history before approving disbursements.
            </p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="py-3.5 px-6">User</th>
                  <th className="py-3.5 px-6">Asset & Network</th>
                  <th className="py-3.5 px-6">Destination Address</th>
                  <th className="py-3.5 px-6 text-right">Points / USD</th>
                  <th className="py-3.5 px-6 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                {payouts.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="py-12 text-center text-slate-400">
                      <CheckCircle2 className="w-8 h-8 text-emerald-500 mx-auto mb-2" />
                      All pending cashouts have been cleared!
                    </td>
                  </tr>
                ) : (
                  payouts.map((payout) => (
                    <tr key={payout.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                      <td className="py-4 px-6 whitespace-nowrap">
                        <span className="font-bold text-slate-900 dark:text-white block">
                          @{payout.user.username}
                        </span>
                        <span className="text-[11px] text-slate-400">{payout.user.email}</span>
                      </td>

                      <td className="py-4 px-6 font-bold whitespace-nowrap">
                        {payout.asset.replace("_", " ")}
                      </td>

                      <td className="py-4 px-6 font-mono text-[11px] max-w-xs truncate">
                        {payout.crypto_address}
                      </td>

                      <td className="py-4 px-6 text-right whitespace-nowrap">
                        <span className="font-black text-slate-900 dark:text-white block">
                          {parseFloat(payout.points_amount).toLocaleString()} PTS
                        </span>
                        <span className="text-xs font-bold text-emerald-500">
                          ${parseFloat(payout.usd_amount).toFixed(2)} USD
                        </span>
                      </td>

                      <td className="py-4 px-6 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            onClick={() => handleApprovePayout(payout.id)}
                            disabled={actionLoading === payout.id}
                            className="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-600 text-white shadow-sm transition disabled:opacity-50"
                          >
                            Approve & Disburse
                          </button>
                          <button
                            onClick={() => handleRejectPayout(payout.id)}
                            disabled={actionLoading === payout.id}
                            className="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-500 hover:bg-rose-600 text-white shadow-sm transition disabled:opacity-50"
                          >
                            Reject & Refund
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 2: USERS DIRECTORY */}
      {activeTab === "users" && (
        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
          <div className="p-6 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h2 className="text-lg font-black text-slate-900 dark:text-white">User Accounts & Access Control</h2>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Inspect user ledger balances, fraud flags, and toggle account bans.
              </p>
            </div>

            <div className="relative">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Search username or email..."
                value={userSearch}
                onChange={(e) => setUserSearch(e.target.value)}
                className="pl-9 pr-4 py-2 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white border-0 focus:ring-2 focus:ring-indigo-500 w-64"
              />
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="py-3.5 px-6">User</th>
                  <th className="py-3.5 px-6">Role</th>
                  <th className="py-3.5 px-6">Available Balance</th>
                  <th className="py-3.5 px-6">Locked Balance</th>
                  <th className="py-3.5 px-6">Status</th>
                  <th className="py-3.5 px-6 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                {filteredUsers.map((u) => (
                  <tr key={u.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                    <td className="py-4 px-6 whitespace-nowrap">
                      <span className="font-bold text-slate-900 dark:text-white block">@{u.username}</span>
                      <span className="text-[11px] text-slate-400">{u.email}</span>
                    </td>

                    <td className="py-4 px-6 whitespace-nowrap font-semibold capitalize">
                      {u.role}
                    </td>

                    <td className="py-4 px-6 whitespace-nowrap font-bold text-emerald-600 dark:text-emerald-400">
                      {parseFloat(u.points_available).toLocaleString()} PTS
                    </td>

                    <td className="py-4 px-6 whitespace-nowrap font-bold text-amber-500">
                      {parseFloat(u.points_locked).toLocaleString()} PTS
                    </td>

                    <td className="py-4 px-6 whitespace-nowrap">
                      <span
                        className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold capitalize ${
                          u.status === "active"
                            ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                            : "bg-rose-500/10 text-rose-600 dark:text-rose-400"
                        }`}
                      >
                        {u.status}
                      </span>
                    </td>

                    <td className="py-4 px-6 text-right whitespace-nowrap">
                      {u.role !== "admin" && (
                        <button
                          onClick={() => handleToggleUserStatus(u.id, u.status)}
                          disabled={actionLoading === u.id}
                          className={`px-3 py-1.5 rounded-xl text-xs font-bold transition ${
                            u.status === "banned"
                              ? "bg-emerald-500 hover:bg-emerald-600 text-white"
                              : "bg-rose-500 hover:bg-rose-600 text-white"
                          }`}
                        >
                          {u.status === "banned" ? "Unban Account" : "Ban Account"}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: FRAUD & SECURITY LOGS */}
      {activeTab === "fraud" && (
        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
          <div className="p-6 border-b border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-white">Security Flags & Account Holds</h2>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Inspect automated flags generated by multi-accounting detection, proxy/VPN filters, and negative balance holds.
            </p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="py-3.5 px-6">User</th>
                  <th className="py-3.5 px-6">Flag Type</th>
                  <th className="py-3.5 px-6">Severity</th>
                  <th className="py-3.5 px-6">Details</th>
                  <th className="py-3.5 px-6 text-right">Status / Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                {fraudFlags.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="py-12 text-center text-slate-400">
                      <ShieldCheck className="w-8 h-8 text-emerald-500 mx-auto mb-2" />
                      Zero fraud flags on record!
                    </td>
                  </tr>
                ) : (
                  fraudFlags.map((flag) => (
                    <tr key={flag.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                      <td className="py-4 px-6 whitespace-nowrap">
                        <span className="font-bold text-slate-900 dark:text-white block">
                          @{flag.user?.username}
                        </span>
                      </td>

                      <td className="py-4 px-6 font-mono font-bold text-rose-500">
                        {flag.flag_type}
                      </td>

                      <td className="py-4 px-6 whitespace-nowrap">
                        <span
                          className={`inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                            flag.severity === "critical"
                              ? "bg-rose-500/20 text-rose-500"
                              : "bg-amber-500/20 text-amber-500"
                          }`}
                        >
                          {flag.severity}
                        </span>
                      </td>

                      <td className="py-4 px-6 max-w-sm font-mono text-[11px] text-slate-500 truncate">
                        {JSON.stringify(flag.details)}
                      </td>

                      <td className="py-4 px-6 text-right whitespace-nowrap">
                        {flag.is_resolved ? (
                          <span className="inline-flex items-center gap-1 text-emerald-500 font-bold">
                            <CheckCircle2 className="w-3.5 h-3.5" /> Resolved
                          </span>
                        ) : (
                          <button
                            onClick={() => handleResolveFlag(flag.id)}
                            disabled={actionLoading === flag.id}
                            className="px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition disabled:opacity-50"
                          >
                            Mark Resolved
                          </button>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
