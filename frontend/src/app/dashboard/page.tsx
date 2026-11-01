"use client";

import React from "react";
import Link from "next/link";
import { useAuth } from "@/context/AuthContext";
import {
  Coins,
  ShieldAlert,
  ArrowRight,
  TrendingUp,
  Clock,
  Sparkles,
  ExternalLink,
  Wallet,
  Users,
  CheckCircle2,
} from "lucide-react";

export default function DashboardPage() {
  const { user, isLoading, walletBalance } = useAuth();

  if (isLoading) {
    return (
      <div className="flex-1 flex items-center justify-center p-8">
        <div className="animate-spin w-8 h-8 border-4 border-emerald-500 border-t-transparent rounded-full"></div>
      </div>
    );
  }

  const offerwalls = [
    {
      name: "BitLabs",
      type: "Surveys & Rewards",
      badge: "High Yield",
      description: "Complete targeted consumer opinion surveys for fast points.",
      color: "from-blue-500 to-indigo-600",
      avgPayout: "1,200 pts ($1.20)",
    },
    {
      name: "CPX Research",
      type: "Opinion Surveys",
      badge: "Fast Crediting",
      description: "Dynamic matching with global survey networks with disqualified compensation.",
      color: "from-amber-500 to-orange-600",
      avgPayout: "850 pts ($0.85)",
    },
    {
      name: "Offerwall.gg",
      type: "CPA Tasks & Apps",
      badge: "Mobile Apps",
      description: "Install tested mobile applications and achieve milestone rewards.",
      color: "from-emerald-500 to-teal-600",
      avgPayout: "3,500 pts ($3.50)",
    },
    {
      name: "Offerwall.com",
      type: "High-Value Gaming",
      badge: "Top Payouts",
      description: "Engage in tier-1 PC and mobile gaming offers with big payouts.",
      color: "from-purple-500 to-pink-600",
      avgPayout: "15,000 pts ($15.00)",
    },
  ];

  return (
    <div className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      {/* Unverified Email Alert Banner */}
      {user && !user.is_email_verified && (
        <div className="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-amber-100 dark:bg-amber-900/50 rounded-xl text-amber-600 dark:text-amber-400">
              <ShieldAlert className="w-5 h-5" />
            </div>
            <div>
              <p className="text-sm font-bold text-amber-900 dark:text-amber-200">
                Action Required: Verify Your Email Address
              </p>
              <p className="text-xs text-amber-700 dark:text-amber-400">
                Verify your email to unlock your 100 Points ($0.10) signup bonus and enable crypto withdrawals.
              </p>
            </div>
          </div>
          <Link
            href="/verify-email"
            className="px-4 py-2 text-xs font-semibold rounded-xl text-white bg-amber-600 hover:bg-amber-500 shadow-sm transition"
          >
            Verify Now
          </Link>
        </div>
      )}

      {/* Welcome & Stats Row */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
            Welcome back, {user?.name || "Member"}!
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Standard Exchange Rate: <span className="font-semibold text-emerald-600 dark:text-emerald-400">1,000 Points = $1.00 USD</span>
          </p>
        </div>

        <div className="flex items-center gap-3">
          <Link
            href="/wallet"
            className="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 shadow-md shadow-emerald-500/20 transition"
          >
            <Wallet className="w-4 h-4" />
            <span>Withdraw Crypto</span>
          </Link>
          <Link
            href="/referrals"
            className="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
          >
            <Users className="w-4 h-4" />
            <span>Invite Friends</span>
          </Link>
        </div>
      </div>

      {/* Balances Overview Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Available Balance */}
        <div className="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden group">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
              Available Balance
            </span>
            <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
              <Coins className="w-4 h-4" />
            </div>
          </div>
          <p className="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
            {Number(walletBalance?.points.available || 0).toLocaleString()} <span className="text-sm font-semibold text-slate-400">PTS</span>
          </p>
          <p className="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-1">
            ≈ ${Number(walletBalance?.fiat_equivalent.available || 0).toFixed(2)} USD
          </p>
        </div>

        {/* Locked Balance */}
        <div className="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
              Locked / In Review
            </span>
            <div className="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">
              <Clock className="w-4 h-4" />
            </div>
          </div>
          <p className="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
            {Number(walletBalance?.points.locked || 0).toLocaleString()} <span className="text-sm font-semibold text-slate-400">PTS</span>
          </p>
          <p className="text-xs text-slate-500 mt-1">
            ≈ ${Number(walletBalance?.fiat_equivalent.locked || 0).toFixed(2)} USD in review
          </p>
        </div>

        {/* Lifetime Earnings */}
        <div className="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
              Lifetime Earned
            </span>
            <div className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400">
              <TrendingUp className="w-4 h-4" />
            </div>
          </div>
          <p className="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
            0 <span className="text-sm font-semibold text-slate-400">PTS</span>
          </p>
          <p className="text-xs text-slate-500 mt-1">
            0 offers completed
          </p>
        </div>

        {/* Referral Earnings */}
        <div className="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
              Referral Code
            </span>
            <div className="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400">
              <Sparkles className="w-4 h-4" />
            </div>
          </div>
          <p className="text-xl font-black tracking-wider text-slate-900 dark:text-white uppercase">
            {user?.referral_code || "N/A"}
          </p>
          <p className="text-xs text-purple-600 dark:text-purple-400 font-semibold mt-1">
            500 PTS per matured friend
          </p>
        </div>
      </div>

      {/* Offerwalls Grid Section */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-xl font-bold text-slate-900 dark:text-white">
              Featured Offerwall Providers
            </h2>
            <p className="text-xs text-slate-500">
              Verified server-to-server postback integrations with zero duplicate credit loss.
            </p>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {offerwalls.map((wall) => (
            <div
              key={wall.name}
              className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 flex flex-col justify-between hover:shadow-lg hover:border-slate-300 dark:hover:border-slate-700 transition"
            >
              <div>
                <div className="flex items-center justify-between mb-3">
                  <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                    {wall.badge}
                  </span>
                  <div className={`w-3 h-3 rounded-full bg-gradient-to-r ${wall.color}`}></div>
                </div>

                <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                  {wall.name}
                </h3>
                <p className="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5">
                  {wall.type}
                </p>
                <p className="text-xs text-slate-500 mt-2 leading-relaxed">
                  {wall.description}
                </p>
              </div>

              <div className="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                  <p className="text-[10px] text-slate-400">Average</p>
                  <p className="text-xs font-bold text-slate-900 dark:text-white">{wall.avgPayout}</p>
                </div>
                <button
                  onClick={() => alert(`Starting session for ${wall.name} (Available in Phase 4)`)}
                  className="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-500 hover:text-white transition"
                >
                  <ExternalLink className="w-4 h-4" />
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
