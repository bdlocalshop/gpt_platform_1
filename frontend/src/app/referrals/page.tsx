"use client";

import React, { useState, useEffect } from "react";
import { useAuth } from "@/context/AuthContext";
import { api } from "@/lib/api";
import {
  Users,
  Copy,
  Check,
  Gift,
  Coins,
  Sparkles,
  ArrowRight,
  TrendingUp,
  Clock,
  ShieldCheck,
  HelpCircle,
  AlertCircle,
  RefreshCw,
} from "lucide-react";
import Link from "next/link";

interface Referee {
  id: number;
  username: string;
  joined_at: string;
  verified_points_earned: string;
  points_needed: string;
  progress_percentage: number;
  is_qualified: boolean;
  bonus_awarded: boolean;
}

interface ReferralDashboardData {
  referral_code: string;
  referral_link: string;
  rules: {
    qualification_threshold_points: number;
    bonus_per_referral_points: number;
    bonus_per_referral_usd: string;
    description: string;
  };
  statistics: {
    total_referrals: number;
    qualified_referrals: number;
    pending_referrals: number;
    total_earned_points: string;
    total_earned_usd: string;
  };
  referees: Referee[];
}

export default function ReferralsPage() {
  const { user, isAuthenticated } = useAuth();

  const [data, setData] = useState<ReferralDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [copiedLink, setCopiedLink] = useState(false);
  const [copiedCode, setCopiedCode] = useState(false);

  useEffect(() => {
    async function loadReferralData() {
      try {
        setLoading(true);
        setError(null);
        const res = await api.get<ReferralDashboardData>("/api/v1/referrals");
        if (res.data) {
          setData(res.data);
        }
      } catch (err: unknown) {
        if (err instanceof Error) {
          setError(err.message);
        } else {
          setError("Failed to load referral program data.");
        }
      } finally {
        setLoading(false);
      }
    }

    if (isAuthenticated) {
      loadReferralData();
    } else {
      setLoading(false);
    }
  }, [isAuthenticated]);

  const copyToClipboard = async (text: string, type: "link" | "code") => {
    try {
      await navigator.clipboard.writeText(text);
      if (type === "link") {
        setCopiedLink(true);
        setTimeout(() => setCopiedLink(false), 2500);
      } else {
        setCopiedCode(true);
        setTimeout(() => setCopiedCode(false), 2500);
      }
    } catch {
      // Fallback
    }
  };

  if (!isAuthenticated && !loading) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-20 text-center">
        <div className="w-16 h-16 rounded-2xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center mx-auto mb-6">
          <Users className="w-8 h-8" />
        </div>
        <h1 className="text-3xl font-extrabold text-slate-900 dark:text-white mb-3">
          Join the Referral Rewards Program
        </h1>
        <p className="text-slate-600 dark:text-slate-400 max-w-md mx-auto mb-8">
          Sign in to invite friends, track referee conversion progress, and earn 500 Points ($0.50 USD) for every qualified friend.
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

  const referralLink = data?.referral_link || `http://localhost:3000/register?ref=${user?.referral_code || ""}`;
  const referralCode = data?.referral_code || user?.referral_code || "--------";

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      {/* Hero Banner */}
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-950 via-slate-900 to-indigo-950 border border-slate-800 p-8 text-white shadow-xl">
        <div className="absolute right-0 top-0 translate-x-12 -translate-y-12 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none" />

        <div className="relative z-10 max-w-2xl">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-400 text-xs font-semibold mb-3 border border-indigo-500/30">
            <Gift className="w-3.5 h-3.5" />
            Tier-Free Flat Referral Program
          </div>
          <h1 className="text-3xl sm:text-4xl font-extrabold tracking-tight">
            Invite Friends & Earn <span className="text-emerald-400">500 PTS</span> ($0.50 USD)
          </h1>
          <p className="text-slate-300 text-sm sm:text-base mt-2 leading-relaxed">
            Share your unique invite link. Once your referee completes verified tasks reaching 1,000 Points ($1.00 USD), you instantly receive a 500-point reward directly into your available balance.
          </p>
        </div>

        {/* Share Box Component */}
        <div className="relative z-10 grid grid-cols-1 md:grid-cols-3 gap-4 mt-8 pt-6 border-t border-slate-800">
          <div className="md:col-span-2 space-y-2">
            <label className="text-xs font-semibold uppercase tracking-wider text-slate-400">
              Your Unique Referral Link
            </label>
            <div className="flex items-center gap-2 bg-slate-950/80 border border-slate-700 rounded-2xl p-2 pl-4">
              <span className="font-mono text-xs sm:text-sm text-emerald-400 truncate flex-1 select-all">
                {referralLink}
              </span>
              <button
                onClick={() => copyToClipboard(referralLink, "link")}
                className="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition flex items-center gap-1.5 shrink-0"
              >
                {copiedLink ? <Check className="w-3.5 h-3.5 text-emerald-300" /> : <Copy className="w-3.5 h-3.5" />}
                {copiedLink ? "Copied Link!" : "Copy Link"}
              </button>
            </div>
          </div>

          <div className="space-y-2">
            <label className="text-xs font-semibold uppercase tracking-wider text-slate-400">
              Referral Code
            </label>
            <div className="flex items-center justify-between bg-slate-950/80 border border-slate-700 rounded-2xl p-2 px-4">
              <span className="font-mono text-base font-black tracking-widest text-amber-400">
                {referralCode}
              </span>
              <button
                onClick={() => copyToClipboard(referralCode, "code")}
                className="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition"
                title="Copy Code"
              >
                {copiedCode ? <Check className="w-4 h-4 text-emerald-400" /> : <Copy className="w-4 h-4" />}
              </button>
            </div>
          </div>
        </div>
      </div>

      {error && (
        <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-sm flex items-center justify-between">
          <span>{error}</span>
          <button
            onClick={() => window.location.reload()}
            className="px-3 py-1 rounded-lg bg-rose-500 text-white text-xs font-semibold"
          >
            Retry
          </button>
        </div>
      )}

      {/* Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
              Total Friends Invited
            </span>
            <div className="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
              <Users className="w-4 h-4" />
            </div>
          </div>
          <p className="text-3xl font-black text-slate-900 dark:text-white">
            {data?.statistics.total_referrals || 0}
          </p>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-2">Registered with your code</p>
        </div>

        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
              Qualified & Paid
            </span>
            <div className="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
              <ShieldCheck className="w-4 h-4" />
            </div>
          </div>
          <p className="text-3xl font-black text-emerald-600 dark:text-emerald-400">
            {data?.statistics.qualified_referrals || 0}
          </p>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-2">Earned 1,000+ points</p>
        </div>

        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
              Pending Maturation
            </span>
            <div className="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center">
              <Clock className="w-4 h-4" />
            </div>
          </div>
          <p className="text-3xl font-black text-amber-600 dark:text-amber-400">
            {data?.statistics.pending_referrals || 0}
          </p>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-2">Working towards 1,000 pts</p>
        </div>

        <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">
              Total Bonus Earned
            </span>
            <div className="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center">
              <TrendingUp className="w-4 h-4" />
            </div>
          </div>
          <p className="text-3xl font-black text-slate-900 dark:text-white">
            {parseFloat(data?.statistics.total_earned_points || "0").toLocaleString()}{" "}
            <span className="text-xs font-bold text-purple-500">PTS</span>
          </p>
          <p className="text-xs text-emerald-500 font-bold mt-2">
            ≈ ${data?.statistics.total_earned_usd || "0.00"} USD
          </p>
        </div>
      </div>

      {/* Referee Progress List */}
      <div className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="p-6 border-b border-slate-200 dark:border-slate-800">
          <h2 className="text-lg font-black text-slate-900 dark:text-white">
            Invited Friends & Progress Tracking
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Track your referees' verified offer conversions. When the progress bar hits 100%, 500 PTS is credited automatically.
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th className="py-3.5 px-6">User</th>
                <th className="py-3.5 px-6">Date Joined</th>
                <th className="py-3.5 px-6">Verified Points Earned</th>
                <th className="py-3.5 px-6">Threshold Progress</th>
                <th className="py-3.5 px-6 text-right">Referral Reward Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
              {loading ? (
                <tr>
                  <td colSpan={5} className="py-12 text-center text-slate-400">
                    <RefreshCw className="w-6 h-6 animate-spin text-indigo-500 mx-auto mb-2" />
                    Loading referees...
                  </td>
                </tr>
              ) : !data?.referees || data.referees.length === 0 ? (
                <tr>
                  <td colSpan={5} className="py-12 text-center text-slate-400">
                    <Users className="w-8 h-8 text-slate-300 dark:text-slate-700 mx-auto mb-2" />
                    You haven't invited any friends yet. Share your link above to get started!
                  </td>
                </tr>
              ) : (
                data.referees.map((referee) => (
                  <tr
                    key={referee.id}
                    className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
                  >
                    <td className="py-4 px-6 font-bold text-slate-900 dark:text-white">
                      @{referee.username}
                    </td>

                    <td className="py-4 px-6 text-slate-500 dark:text-slate-400">
                      {new Date(referee.joined_at).toLocaleDateString("en-US", {
                        month: "short",
                        day: "numeric",
                        year: "numeric",
                      })}
                    </td>

                    <td className="py-4 px-6 font-semibold">
                      {parseFloat(referee.verified_points_earned).toLocaleString()} / 1,000 PTS
                    </td>

                    <td className="py-4 px-6 min-w-[200px]">
                      <div className="flex items-center gap-3">
                        <div className="flex-1 h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                          <div
                            className={`h-full rounded-full transition-all duration-500 ${
                              referee.is_qualified
                                ? "bg-emerald-500"
                                : "bg-gradient-to-r from-amber-500 to-indigo-500"
                            }`}
                            style={{ width: `${referee.progress_percentage}%` }}
                          />
                        </div>
                        <span className="text-[11px] font-bold text-slate-500">
                          {referee.progress_percentage}%
                        </span>
                      </div>
                    </td>

                    <td className="py-4 px-6 text-right">
                      {referee.bonus_awarded ? (
                        <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                          <Check className="w-3.5 h-3.5" />
                          +500 PTS Credited
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                          <Clock className="w-3.5 h-3.5" />
                          {parseFloat(referee.points_needed).toLocaleString()} PTS Needed
                        </span>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
