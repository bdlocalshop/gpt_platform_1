"use client";

import React, { useState, useEffect, useCallback } from "react";
import { useAuth } from "@/context/AuthContext";
import { api } from "@/lib/api";
import {
  Wallet,
  Coins,
  ArrowUpRight,
  ArrowDownLeft,
  RefreshCw,
  Lock,
  CheckCircle2,
  AlertCircle,
  Clock,
  Sparkles,
  ExternalLink,
  ChevronLeft,
  ChevronRight,
  Filter,
  DollarSign,
  TrendingUp,
} from "lucide-react";
import Link from "next/link";

interface TransactionItem {
  id: number;
  account_type: string;
  direction: "credit" | "debit";
  amount: string;
  amount_usd: string;
  balance_after: string;
  transaction: {
    uuid: string;
    type: string;
    description: string;
    reference_type?: string;
    reference_id?: string;
    created_at: string;
  };
  created_at: string;
}

interface WalletBalanceData {
  currency: string;
  points: {
    available: string;
    locked: string;
    total: string;
    lifetime_earned: string;
    lifetime_withdrawn: string;
  };
  fiat_equivalent: {
    currency: string;
    rate: string;
    available: string;
    locked: string;
    total: string;
    lifetime_earned: string;
    lifetime_withdrawn: string;
  };
}

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export default function WalletPage() {
  const { user, isAuthenticated, refreshBalance } = useAuth();

  const [balanceData, setBalanceData] = useState<WalletBalanceData | null>(null);
  const [transactions, setTransactions] = useState<TransactionItem[]>([]);
  const [pagination, setPagination] = useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  });

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [selectedType, setSelectedType] = useState<string>("all");
  const [page, setPage] = useState<number>(1);

  const fetchWalletData = useCallback(async () => {
    try {
      setError(null);
      const balanceRes = await api.get<WalletBalanceData>("/api/v1/wallet/balance");
      if (balanceRes.data) {
        setBalanceData(balanceRes.data);
      }

      const typeQuery = selectedType !== "all" ? `&type=${selectedType}` : "";
      const txRes = await api.get<{
        transactions: TransactionItem[];
        pagination: PaginationMeta;
      }>(`/api/v1/wallet/transactions?page=${page}&per_page=15${typeQuery}`);

      if (txRes.data) {
        setTransactions(txRes.data.transactions || []);
        setPagination(txRes.data.pagination);
      }
    } catch (err: unknown) {
      if (err instanceof Error) {
        setError(err.message);
      } else {
        setError("Failed to fetch wallet data.");
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [page, selectedType]);

  useEffect(() => {
    if (isAuthenticated) {
      fetchWalletData();
    } else {
      setLoading(false);
    }
  }, [isAuthenticated, fetchWalletData]);

  const handleManualRefresh = async () => {
    setRefreshing(true);
    await refreshBalance();
    await fetchWalletData();
  };

  const getTransactionTypeBadge = (type?: string) => {
    switch (type) {
      case "offer_reward":
        return {
          label: "Offer Reward",
          className: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20",
        };
      case "signup_bonus":
        return {
          label: "Signup Bonus",
          className: "bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20",
        };
      case "referral_bonus":
        return {
          label: "Referral Bonus",
          className: "bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20",
        };
      case "payout_lock":
        return {
          label: "Payout In Escrow",
          className: "bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20",
        };
      case "payout_complete":
        return {
          label: "Payout Settled",
          className: "bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20",
        };
      case "payout_reject":
        return {
          label: "Payout Refunded",
          className: "bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-500/20",
        };
      case "reversal":
        return {
          label: "Chargeback Clawback",
          className: "bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20",
        };
      default:
        return {
          label: type || "System Entry",
          className: "bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20",
        };
    }
  };

  if (!isAuthenticated && !loading) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-20 text-center">
        <div className="w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center mx-auto mb-6">
          <Wallet className="w-8 h-8" />
        </div>
        <h1 className="text-3xl font-extrabold text-slate-900 dark:text-white mb-3">
          Sign In to Access Your Wallet
        </h1>
        <p className="text-slate-600 dark:text-slate-400 max-w-md mx-auto mb-8">
          Manage your verified points, inspect your immutable ledger entries, and request crypto cashouts.
        </p>
        <div className="flex justify-center gap-4">
          <Link
            href="/login"
            className="px-6 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-900 dark:text-white font-medium transition"
          >
            Sign In
          </Link>
          <Link
            href="/register"
            className="px-6 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-medium shadow-md shadow-emerald-500/20 transition"
          >
            Create Free Account
          </Link>
        </div>
      </div>
    );
  }

  const availablePoints = balanceData ? parseFloat(balanceData.points.available) : 0;
  const lockedPoints = balanceData ? parseFloat(balanceData.points.locked) : 0;
  const totalPoints = balanceData ? parseFloat(balanceData.points.total) : 0;
  const lifetimeEarned = balanceData ? parseFloat(balanceData.points.lifetime_earned) : 0;

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-semibold mb-2 border border-emerald-500/20">
            <Coins className="w-3.5 h-3.5" />
            Double-Entry Ledger Accounting
          </div>
          <h1 className="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
            User Wallet & Ledger
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Exchange Rate: <span className="font-semibold text-emerald-500">1,000 Points = $1.00 USD</span> (1 Pt = $0.001)
          </p>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={handleManualRefresh}
            disabled={refreshing}
            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60 shadow-sm transition disabled:opacity-50"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${refreshing ? "animate-spin text-emerald-500" : ""}`} />
            Refresh Balances
          </button>

          <Link
            href="/wallet/cashout"
            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-600 text-white shadow-md shadow-amber-500/20 transition"
          >
            <Coins className="w-3.5 h-3.5" />
            Cash Out Crypto
          </Link>

          <Link
            href="/offers"
            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-600 text-white shadow-md shadow-emerald-500/20 transition"
          >
            <Sparkles className="w-3.5 h-3.5" />
            Earn Points
          </Link>
        </div>
      </div>

      {error && (
        <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-sm flex items-center justify-between">
          <span>{error}</span>
          <button
            onClick={fetchWalletData}
            className="px-3 py-1 rounded-lg bg-rose-500 text-white text-xs font-semibold"
          >
            Retry
          </button>
        </div>
      )}

      {/* Balance Summary Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {/* Available Balance */}
        <div className="relative rounded-3xl bg-gradient-to-br from-emerald-500/10 via-emerald-500/5 to-transparent border border-emerald-500/20 p-6 flex flex-col justify-between shadow-sm">
          <div>
            <div className="flex items-center justify-between mb-3">
              <span className="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                Available Balance
              </span>
              <div className="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-500 flex items-center justify-center">
                <Coins className="w-4 h-4" />
              </div>
            </div>
            <p className="text-3xl font-black text-slate-900 dark:text-white">
              {availablePoints.toLocaleString()}{" "}
              <span className="text-sm font-semibold text-emerald-500">PTS</span>
            </p>
          </div>
          <div className="pt-4 border-t border-emerald-500/10 mt-4 flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">USD Value:</span>
            <span className="text-sm font-bold text-slate-900 dark:text-white">
              ${(availablePoints * 0.001).toFixed(2)} USD
            </span>
          </div>
        </div>

        {/* Locked / Escrow Balance */}
        <div className="relative rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between shadow-sm">
          <div>
            <div className="flex items-center justify-between mb-3">
              <span className="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                In Escrow / Pending
              </span>
              <div className="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center">
                <Lock className="w-4 h-4" />
              </div>
            </div>
            <p className="text-3xl font-black text-slate-900 dark:text-white">
              {lockedPoints.toLocaleString()}{" "}
              <span className="text-sm font-semibold text-amber-500">PTS</span>
            </p>
          </div>
          <div className="pt-4 border-t border-slate-100 dark:border-slate-800 mt-4 flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">USD Value:</span>
            <span className="text-sm font-bold text-slate-900 dark:text-white">
              ${(lockedPoints * 0.001).toFixed(2)} USD
            </span>
          </div>
        </div>

        {/* Total Accumulated Balance */}
        <div className="relative rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between shadow-sm">
          <div>
            <div className="flex items-center justify-between mb-3">
              <span className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                Total Wallet Net
              </span>
              <div className="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
                <Wallet className="w-4 h-4" />
              </div>
            </div>
            <p className="text-3xl font-black text-slate-900 dark:text-white">
              {totalPoints.toLocaleString()}{" "}
              <span className="text-sm font-semibold text-indigo-500">PTS</span>
            </p>
          </div>
          <div className="pt-4 border-t border-slate-100 dark:border-slate-800 mt-4 flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total USD:</span>
            <span className="text-sm font-bold text-slate-900 dark:text-white">
              ${(totalPoints * 0.001).toFixed(2)} USD
            </span>
          </div>
        </div>

        {/* Lifetime Earned */}
        <div className="relative rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between shadow-sm">
          <div>
            <div className="flex items-center justify-between mb-3">
              <span className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                Lifetime Earned
              </span>
              <div className="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center">
                <TrendingUp className="w-4 h-4" />
              </div>
            </div>
            <p className="text-3xl font-black text-slate-900 dark:text-white">
              {lifetimeEarned.toLocaleString()}{" "}
              <span className="text-sm font-semibold text-purple-500">PTS</span>
            </p>
          </div>
          <div className="pt-4 border-t border-slate-100 dark:border-slate-800 mt-4 flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Gross Value:</span>
            <span className="text-sm font-bold text-slate-900 dark:text-white">
              ${(lifetimeEarned * 0.001).toFixed(2)} USD
            </span>
          </div>
        </div>
      </div>

      {/* Quick Action Banner */}
      <div className="rounded-3xl bg-slate-900 text-white p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-6 border border-slate-800 shadow-xl">
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-emerald-400 text-xs font-bold uppercase">
            <CheckCircle2 className="w-4 h-4" />
            Instant Crypto Withdrawals Active
          </div>
          <h3 className="text-xl sm:text-2xl font-black">
            Ready to Cash Out Your Points?
          </h3>
          <p className="text-xs sm:text-sm text-slate-400 max-w-xl">
            Redeem points for USDT (TRC-20/BEP-20), Litecoin (LTC), or Bitcoin (BTC). Minimum cashout is 5,000 Points ($5.00 USD).
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <Link
            href="/referrals"
            className="px-5 py-2.5 rounded-xl font-bold text-xs bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 transition"
          >
            Invite & Earn 500 PTS
          </Link>
          <Link
            href="/wallet/cashout"
            className="px-5 py-2.5 rounded-xl font-bold text-xs bg-emerald-500 hover:bg-emerald-600 text-white shadow-md shadow-emerald-500/20 transition"
          >
            Cash Out to Crypto
          </Link>
        </div>
      </div>

      {/* Transaction History Section */}
      <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        {/* Table Controls */}
        <div className="p-6 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h2 className="text-lg font-black text-slate-900 dark:text-white">
              Immutable Ledger Activity
            </h2>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Every balance movement is backed by balanced debits and credits recorded atomically.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Filter className="w-3.5 h-3.5 text-slate-400" />
            <select
              value={selectedType}
              onChange={(e) => {
                setSelectedType(e.target.value);
                setPage(1);
              }}
              className="px-3 py-1.5 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white border-0 focus:ring-2 focus:ring-emerald-500 cursor-pointer font-medium"
            >
              <option value="all">All Transactions</option>
              <option value="offer_reward">Offer Rewards</option>
              <option value="signup_bonus">Signup Bonuses</option>
              <option value="referral_bonus">Referral Bonuses</option>
              <option value="payout_lock">Payout Locks</option>
              <option value="payout_complete">Payout Disbursements</option>
              <option value="reversal">Reversals & Chargebacks</option>
            </select>
          </div>
        </div>

        {/* Ledger Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th className="py-3.5 px-6">Date & Time</th>
                <th className="py-3.5 px-6">Type</th>
                <th className="py-3.5 px-6">Description</th>
                <th className="py-3.5 px-6 text-right">Points Movement</th>
                <th className="py-3.5 px-6 text-right">USD Equivalent</th>
                <th className="py-3.5 px-6 text-right">Balance After</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
              {loading ? (
                <tr>
                  <td colSpan={6} className="py-12 text-center text-slate-400">
                    <RefreshCw className="w-6 h-6 animate-spin text-emerald-500 mx-auto mb-2" />
                    Loading ledger transactions...
                  </td>
                </tr>
              ) : transactions.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-12 text-center text-slate-400">
                    <AlertCircle className="w-8 h-8 text-slate-300 dark:text-slate-700 mx-auto mb-2" />
                    No ledger transactions found matching this filter.
                  </td>
                </tr>
              ) : (
                transactions.map((tx) => {
                  const badge = getTransactionTypeBadge(tx.transaction?.type);
                  const isCredit = tx.direction === "credit";
                  const formattedAmount = parseFloat(tx.amount).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 4,
                  });

                  return (
                    <tr
                      key={tx.id}
                      className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
                    >
                      <td className="py-4 px-6 whitespace-nowrap text-slate-500 dark:text-slate-400">
                        {new Date(tx.created_at).toLocaleDateString("en-US", {
                          month: "short",
                          day: "numeric",
                          year: "numeric",
                        })}{" "}
                        <span className="text-[10px] text-slate-400">
                          {new Date(tx.created_at).toLocaleTimeString("en-US", {
                            hour: "2-digit",
                            minute: "2-digit",
                          })}
                        </span>
                      </td>

                      <td className="py-4 px-6 whitespace-nowrap">
                        <span
                          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border ${badge.className}`}
                        >
                          {badge.label}
                        </span>
                      </td>

                      <td className="py-4 px-6 max-w-xs truncate font-medium">
                        {tx.transaction?.description || "Transaction"}
                      </td>

                      <td className="py-4 px-6 whitespace-nowrap text-right font-black">
                        <span
                          className={`inline-flex items-center gap-1 ${
                            isCredit
                              ? "text-emerald-600 dark:text-emerald-400"
                              : "text-rose-600 dark:text-rose-400"
                          }`}
                        >
                          {isCredit ? (
                            <ArrowDownLeft className="w-3.5 h-3.5" />
                          ) : (
                            <ArrowUpRight className="w-3.5 h-3.5" />
                          )}
                          {isCredit ? "+" : "-"}
                          {formattedAmount} PTS
                        </span>
                      </td>

                      <td className="py-4 px-6 whitespace-nowrap text-right text-slate-500 dark:text-slate-400 font-semibold">
                        {isCredit ? "+" : "-"}${parseFloat(tx.amount_usd).toFixed(2)} USD
                      </td>

                      <td className="py-4 px-6 whitespace-nowrap text-right font-bold text-slate-900 dark:text-white">
                        {parseFloat(tx.balance_after).toLocaleString()} PTS
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Footer */}
        {pagination.last_page > 1 && (
          <div className="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
            <span className="text-slate-500 dark:text-slate-400">
              Showing Page <span className="font-bold text-slate-900 dark:text-white">{pagination.current_page}</span> of{" "}
              <span className="font-bold text-slate-900 dark:text-white">{pagination.last_page}</span> ({pagination.total} total)
            </span>

            <div className="flex items-center gap-2">
              <button
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
                className="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition disabled:opacity-40"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
              <button
                onClick={() => setPage((p) => Math.min(pagination.last_page, p + 1))}
                disabled={page >= pagination.last_page}
                className="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition disabled:opacity-40"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
