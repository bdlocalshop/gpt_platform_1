"use client";

import React from "react";
import Link from "next/link";
import { Coins, ShieldCheck, Zap, Lock } from "lucide-react";

export function Footer() {
  return (
    <footer className="mt-auto border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-slate-600 dark:text-slate-400 text-sm transition-colors">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
          {/* Brand Col */}
          <div className="space-y-4">
            <div className="flex items-center gap-2 font-bold text-lg text-slate-900 dark:text-white">
              <div className="w-8 h-8 rounded-lg bg-gradient-to-tr from-amber-500 to-emerald-500 flex items-center justify-center text-white">
                <Coins className="w-4 h-4" />
              </div>
              <span>GPT<span className="text-emerald-500">Rewards</span></span>
            </div>
            <p className="text-xs text-slate-500 leading-relaxed">
              High-throughput rewards platform powered by immutable double-entry accounting and instant cryptocurrency payouts.
            </p>
            <div className="flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              All Systems Operational
            </div>
          </div>

          {/* Earning */}
          <div>
            <h4 className="font-semibold text-slate-900 dark:text-slate-200 mb-3 text-xs tracking-wider uppercase">
              Earn Points
            </h4>
            <ul className="space-y-2 text-xs">
              <li><Link href="/offers" className="hover:text-emerald-500 transition">BitLabs Surveys</Link></li>
              <li><Link href="/offers" className="hover:text-emerald-500 transition">CPX Research</Link></li>
              <li><Link href="/offers" className="hover:text-emerald-500 transition">Offerwall.gg CPA</Link></li>
              <li><Link href="/offers" className="hover:text-emerald-500 transition">Offerwall.com High Yield</Link></li>
            </ul>
          </div>

          {/* Cashout */}
          <div>
            <h4 className="font-semibold text-slate-900 dark:text-slate-200 mb-3 text-xs tracking-wider uppercase">
              Redemptions
            </h4>
            <ul className="space-y-2 text-xs">
              <li><Link href="/wallet" className="hover:text-emerald-500 transition">Tether USDT (TRC-20)</Link></li>
              <li><Link href="/wallet" className="hover:text-emerald-500 transition">Litecoin (LTC)</Link></li>
              <li><Link href="/wallet" className="hover:text-emerald-500 transition">Bitcoin (BTC)</Link></li>
              <li><Link href="/referrals" className="hover:text-emerald-500 transition">Referral Program</Link></li>
            </ul>
          </div>

          {/* Security & Invariants */}
          <div>
            <h4 className="font-semibold text-slate-900 dark:text-slate-200 mb-3 text-xs tracking-wider uppercase">
              Platform Integrity
            </h4>
            <div className="space-y-2 text-xs">
              <div className="flex items-center gap-2 text-slate-500">
                <Lock className="w-4 h-4 text-emerald-500" />
                <span>Double-Entry Immutable Ledger</span>
              </div>
              <div className="flex items-center gap-2 text-slate-500">
                <ShieldCheck className="w-4 h-4 text-emerald-500" />
                <span>Anti-Fraud S2S Verification</span>
              </div>
              <div className="flex items-center gap-2 text-slate-500">
                <Zap className="w-4 h-4 text-emerald-500" />
                <span>1,000 Points = $1.00 USD</span>
              </div>
            </div>
          </div>
        </div>

        <div className="pt-8 border-t border-slate-100 dark:border-slate-800 text-center text-xs text-slate-500">
          <p>© 2026 GPT Rewards. All rights reserved. Strict single-device policy enforced.</p>
        </div>
      </div>
    </footer>
  );
}
