"use client";

import React, { useState, useEffect, useCallback } from "react";
import { useAuth } from "@/context/AuthContext";
import { api, ApiError } from "@/lib/api";
import {
  Wallet,
  Coins,
  ArrowRight,
  ShieldCheck,
  AlertTriangle,
  Clock,
  CheckCircle2,
  XCircle,
  ExternalLink,
  ChevronLeft,
  DollarSign,
  Sparkles,
  Info,
  RefreshCw,
} from "lucide-react";
import Link from "next/link";

interface PayoutItem {
  id: number;
  uuid: string;
  asset: string;
  crypto_address: string;
  points_amount: string;
  usd_amount: string;
  crypto_amount?: string;
  status: "pending" | "processing" | "completed" | "rejected" | "cancelled";
  tx_hash?: string;
  admin_notes?: string;
  created_at: string;
}

const CRYPTO_ASSETS = [
  {
    id: "USDT_TRC20",
    name: "Tether (USDT)",
    network: "TRON (TRC-20)",
    placeholder: "T...",
    minPoints: 5000,
    hint: "Starts with 'T' (34 characters)",
    color: "from-emerald-500/20 to-teal-500/20 text-emerald-500 border-emerald-500/30",
  },
  {
    id: "USDT_BEP20",
    name: "Tether (USDT)",
    network: "BNB Smart Chain (BEP-20)",
    placeholder: "0x...",
    minPoints: 5000,
    hint: "Starts with '0x' (42 characters)",
    color: "from-amber-500/20 to-yellow-500/20 text-amber-500 border-amber-500/30",
  },
  {
    id: "LTC",
    name: "Litecoin (LTC)",
    network: "Litecoin Network",
    placeholder: "L... / M... / ltc1...",
    minPoints: 5000,
    hint: "Starts with 'L', 'M', or 'ltc1'",
    color: "from-slate-500/20 to-indigo-500/20 text-indigo-400 border-indigo-500/30",
  },
  {
    id: "BTC",
    name: "Bitcoin (BTC)",
    network: "Bitcoin Native SegWit",
    placeholder: "1... / 3... / bc1...",
    minPoints: 5000,
    hint: "Starts with '1', '3', or 'bc1'",
    color: "from-orange-500/20 to-amber-500/20 text-orange-500 border-orange-500/30",
  },
];

export default function CashoutPage() {
  const { user, isAuthenticated, walletBalance, refreshBalance } = useAuth();

  const [selectedAsset, setSelectedAsset] = useState<string>("USDT_TRC20");
  const [address, setAddress] = useState<string>("");
  const [pointsInput, setPointsInput] = useState<string>("5000");

  const [payouts, setPayouts] = useState<PayoutItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  const availablePoints = walletBalance ? parseFloat(walletBalance.points.available) : 0;

  const fetchPayouts = useCallback(async () => {
    try {
      const res = await api.get<{ payouts: PayoutItem[] }>("/api/v1/wallet/payouts");
      if (res.data?.payouts) {
        setPayouts(res.data.payouts);
      }
    } catch {
      // Ignored
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isAuthenticated) {
      fetchPayouts();
    } else {
      setLoading(false);
    }
  }, [isAuthenticated, fetchPayouts]);

  const handleQuickPercent = (percent: number) => {
    const calculated = Math.floor(availablePoints * (percent / 100));
    setPointsInput(Math.max(5000, calculated).toString());
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccessMsg(null);

    const parsedPoints = parseFloat(pointsInput);
    if (isNaN(parsedPoints) || parsedPoints < 5000) {
      setError("Minimum withdrawal amount is 5,000 Points ($5.00 USD).");
      return;
    }

    if (parsedPoints > availablePoints) {
      setError("Insufficient available points balance.");
      return;
    }

    if (!address.trim()) {
      setError("Destination wallet address is required.");
      return;
    }

    try {
      setSubmitting(true);
      const res = await api.post<PayoutItem>("/api/v1/wallet/payouts", {
        asset: selectedAsset,
        crypto_address: address.trim(),
        points_amount: parsedPoints,
      });

      setSuccessMsg(res.message || "Withdrawal request submitted successfully!");
      setAddress("");
      await refreshBalance();
      await fetchPayouts();
    } catch (err: unknown) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Failed to submit withdrawal request.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "completed":
        return {
          icon: CheckCircle2,
          label: "Completed",
          className: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20",
        };
      case "pending":
      case "processing":
        return {
          icon: Clock,
          label: "In Review",
          className: "bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20",
        };
      case "rejected":
        return {
          icon: XCircle,
          label: "Rejected & Refunded",
          className: "bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20",
        };
      default:
        return {
          icon: Info,
          label: status,
          className: "bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20",
        };
    }
  };

  if (!isAuthenticated && !loading) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-20 text-center">
        <div className="w-16 h-16 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center mx-auto mb-6">
          <Wallet className="w-8 h-8" />
        </div>
        <h1 className="text-3xl font-extrabold text-slate-900 dark:text-white mb-3">
          Sign In to Request Crypto Payouts
        </h1>
        <p className="text-slate-600 dark:text-slate-400 max-w-md mx-auto mb-8">
          Sign in or create an account to redeem your accumulated points for cryptocurrency withdrawals.
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

  const selectedAssetMeta = CRYPTO_ASSETS.find((a) => a.id === selectedAsset) || CRYPTO_ASSETS[0];
  const pointsVal = parseFloat(pointsInput) || 0;
  const usdEquivalent = (pointsVal * 0.001).toFixed(2);

  return (
    <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      {/* Back button */}
      <div>
        <Link
          href="/wallet"
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 dark:hover:text-white transition"
        >
          <ChevronLeft className="w-4 h-4" />
          Back to Wallet & Ledger
        </Link>
      </div>

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-semibold mb-2 border border-emerald-500/20">
            <Coins className="w-3.5 h-3.5" />
            Zero Platform Fees
          </div>
          <h1 className="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
            Crypto Cashout Engine
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Redeem points directly to your non-custodial or exchange wallet.
          </p>
        </div>

        {/* Current Available Balance */}
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl px-5 py-3 shadow-sm flex items-center gap-4">
          <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
            <Coins className="w-5 h-5" />
          </div>
          <div>
            <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Available to Cash Out</span>
            <p className="text-xl font-black text-slate-900 dark:text-white">
              {availablePoints.toLocaleString()} <span className="text-xs font-semibold text-emerald-500">PTS</span>
            </p>
            <span className="text-xs text-slate-500 font-semibold">≈ ${(availablePoints * 0.001).toFixed(2)} USD</span>
          </div>
        </div>
      </div>

      {/* Feedback Alerts */}
      {error && (
        <div className="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs sm:text-sm flex items-center gap-3">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <span className="font-semibold">{error}</span>
        </div>
      )}

      {successMsg && (
        <div className="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs sm:text-sm flex items-center gap-3">
          <CheckCircle2 className="w-5 h-5 shrink-0" />
          <span className="font-semibold">{successMsg}</span>
        </div>
      )}

      {/* Main Cashout Form Card */}
      <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 sm:p-8 shadow-sm space-y-6">
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Step 1: Asset Selector */}
          <div>
            <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">
              1. Select Cryptocurrency & Network
            </label>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
              {CRYPTO_ASSETS.map((asset) => (
                <button
                  key={asset.id}
                  type="button"
                  onClick={() => setSelectedAsset(asset.id)}
                  className={`p-4 rounded-2xl border text-left transition-all flex flex-col justify-between ${
                    selectedAsset === asset.id
                      ? "border-emerald-500 bg-emerald-500/5 ring-2 ring-emerald-500/20"
                      : "border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40"
                  }`}
                >
                  <div>
                    <span className="text-xs font-bold text-slate-900 dark:text-white block">
                      {asset.name}
                    </span>
                    <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400 mt-0.5 block">
                      {asset.network}
                    </span>
                  </div>
                  <span className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-3 block">
                    Min: {asset.minPoints.toLocaleString()} PTS
                  </span>
                </button>
              ))}
            </div>
          </div>

          {/* Step 2: Amount Input */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                2. Points to Withdraw
              </label>
              <div className="flex items-center gap-1.5">
                {[25, 50, 75, 100].map((pct) => (
                  <button
                    key={pct}
                    type="button"
                    onClick={() => handleQuickPercent(pct)}
                    className="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition"
                  >
                    {pct}%
                  </button>
                ))}
              </div>
            </div>

            <div className="relative">
              <Coins className="w-5 h-5 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2" />
              <input
                type="number"
                min="5000"
                step="100"
                value={pointsInput}
                onChange={(e) => setPointsInput(e.target.value)}
                placeholder="5000"
                className="w-full pl-12 pr-28 py-3.5 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white text-base font-bold border-0 focus:ring-2 focus:ring-emerald-500"
              />
              <div className="absolute right-4 top-1/2 -translate-y-1/2 text-right">
                <span className="text-xs font-black text-emerald-500 block">
                  ≈ ${usdEquivalent} USD
                </span>
              </div>
            </div>
          </div>

          {/* Step 3: Destination Address Input */}
          <div>
            <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
              3. {selectedAssetMeta.name} ({selectedAssetMeta.network}) Destination Address
            </label>
            <input
              type="text"
              value={address}
              onChange={(e) => setAddress(e.target.value)}
              placeholder={selectedAssetMeta.placeholder}
              className="w-full px-4 py-3.5 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white text-xs sm:text-sm font-mono border-0 focus:ring-2 focus:ring-emerald-500"
            />
            <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-2 flex items-center gap-1.5">
              <Info className="w-3.5 h-3.5" />
              {selectedAssetMeta.hint}. Double-check your address before confirming.
            </p>
          </div>

          {/* Anti-Fraud Warning Box */}
          <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 text-xs space-y-1">
            <div className="font-bold flex items-center gap-1.5">
              <ShieldCheck className="w-4 h-4" />
              Strict Single-Owner Wallet Address Policy:
            </div>
            <p className="leading-relaxed">
              Crypto addresses cannot be shared across multiple accounts. Reusing addresses automatically triggers a permanent fraud hold. First-time withdrawals require manual administrative approval.
            </p>
          </div>

          {/* Submit Action */}
          <button
            type="submit"
            disabled={submitting || availablePoints < 5000}
            className="w-full py-4 rounded-2xl font-black text-sm bg-emerald-500 hover:bg-emerald-600 text-white shadow-lg shadow-emerald-500/25 transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {submitting ? (
              <>
                <RefreshCw className="w-4 h-4 animate-spin" />
                Validating & Locking Balance...
              </>
            ) : (
              <>
                Confirm Cashout of {pointsVal.toLocaleString()} PTS (${usdEquivalent} USD)
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>
        </form>
      </div>

      {/* Historical Cashouts Table */}
      <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="p-6 border-b border-slate-200 dark:border-slate-800">
          <h2 className="text-lg font-black text-slate-900 dark:text-white">
            Payout History & Blockchain Disbursements
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Inspect the status, transaction hashes, and timestamps of your previous cashout requests.
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th className="py-3.5 px-6">Date</th>
                <th className="py-3.5 px-6">Asset</th>
                <th className="py-3.5 px-6">Destination Address</th>
                <th className="py-3.5 px-6 text-right">Amount</th>
                <th className="py-3.5 px-6 text-right">Status</th>
                <th className="py-3.5 px-6 text-right">Transaction Hash</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
              {loading ? (
                <tr>
                  <td colSpan={6} className="py-12 text-center text-slate-400">
                    <RefreshCw className="w-6 h-6 animate-spin text-emerald-500 mx-auto mb-2" />
                    Loading withdrawal history...
                  </td>
                </tr>
              ) : payouts.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-12 text-center text-slate-400">
                    <Wallet className="w-8 h-8 text-slate-300 dark:text-slate-700 mx-auto mb-2" />
                    No cashout requests found. Submit your first payout above!
                  </td>
                </tr>
              ) : (
                payouts.map((item) => {
                  const statusBadge = getStatusBadge(item.status);
                  const Icon = statusBadge.icon;
                  return (
                    <tr
                      key={item.id}
                      className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
                    >
                      <td className="py-4 px-6 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                        {new Date(item.created_at).toLocaleDateString("en-US", {
                          month: "short",
                          day: "numeric",
                          year: "numeric",
                        })}
                      </td>

                      <td className="py-4 px-6 font-bold whitespace-nowrap">
                        {item.asset.replace("_", " ")}
                      </td>

                      <td className="py-4 px-6 font-mono text-[11px] max-w-xs truncate">
                        {item.crypto_address}
                      </td>

                      <td className="py-4 px-6 text-right whitespace-nowrap">
                        <span className="font-black text-slate-900 dark:text-white block">
                          {parseFloat(item.points_amount).toLocaleString()} PTS
                        </span>
                        <span className="text-[11px] font-semibold text-emerald-500 block">
                          ${parseFloat(item.usd_amount).toFixed(2)} USD
                        </span>
                      </td>

                      <td className="py-4 px-6 text-right whitespace-nowrap">
                        <span
                          className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-bold border ${statusBadge.className}`}
                        >
                          <Icon className="w-3.5 h-3.5" />
                          {statusBadge.label}
                        </span>
                      </td>

                      <td className="py-4 px-6 text-right whitespace-nowrap font-mono text-[11px]">
                        {item.tx_hash ? (
                          <span className="text-emerald-500 font-semibold truncate max-w-[140px] inline-block" title={item.tx_hash}>
                            {item.tx_hash.substring(0, 10)}...{item.tx_hash.substring(item.tx_hash.length - 8)}
                          </span>
                        ) : (
                          <span className="text-slate-400">Queued</span>
                        )}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
