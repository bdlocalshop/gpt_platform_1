import React from "react";
import Link from "next/link";
import {
  Coins,
  ShieldCheck,
  Zap,
  Lock,
  ArrowRight,
  CheckCircle2,
  TrendingUp,
  Award,
  Wallet,
} from "lucide-react";

export default function HomePage() {
  return (
    <div className="flex-1 flex flex-col">
      {/* Hero Section */}
      <section className="relative overflow-hidden py-20 lg:py-32 bg-slate-50 dark:bg-slate-950 transition-colors">
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(16,185,129,0.15),rgba(255,255,255,0))] dark:bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(16,185,129,0.25),rgba(0,0,0,0))]"></div>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
          <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold mb-6">
            <span className="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Next-Gen Rewards Ecosystem with Crypto Payouts
          </div>

          <h1 className="text-4xl sm:text-6xl font-black text-slate-900 dark:text-white tracking-tight max-w-4xl mx-auto leading-tight">
            Get Paid to Complete Surveys, Games & CPA Offers
          </h1>

          <p className="mt-6 text-lg sm:text-xl text-slate-600 dark:text-slate-400 max-w-2xl mx-auto leading-relaxed">
            Instant point crediting with immutable double-entry accounting. Cash out directly to USDT, LTC, or Bitcoin.
          </p>

          <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
            <Link
              href="/register"
              className="w-full sm:w-auto flex items-center justify-center gap-2.5 px-8 py-3.5 rounded-xl font-bold text-base text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 shadow-xl shadow-emerald-500/25 transition-all hover:scale-[1.02]"
            >
              <span>Create Free Account</span>
              <ArrowRight className="w-5 h-5" />
            </Link>
            <Link
              href="/login"
              className="w-full sm:w-auto flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl font-semibold text-base border border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-900 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition"
            >
              Sign In
            </Link>
          </div>

          <div className="mt-8 flex items-center justify-center gap-6 text-xs text-slate-500">
            <span className="flex items-center gap-1.5">
              <CheckCircle2 className="w-4 h-4 text-emerald-500" /> 1,000 Pts = $1.00 USD
            </span>
            <span className="flex items-center gap-1.5">
              <CheckCircle2 className="w-4 h-4 text-emerald-500" /> Minimum $5 Cashout
            </span>
            <span className="flex items-center gap-1.5">
              <CheckCircle2 className="w-4 h-4 text-emerald-500" /> Instant S2S Postbacks
            </span>
          </div>
        </div>
      </section>

      {/* Feature Value Props */}
      <section className="py-20 bg-white dark:bg-slate-900/50 border-y border-slate-200 dark:border-slate-800/80 transition-colors">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center max-w-2xl mx-auto mb-16">
            <h2 className="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
              Engineered for Fairness & Instant Liquidity
            </h2>
            <p className="mt-3 text-slate-500 text-sm">
              We never manipulate balances. Every transaction is backed by cryptographic postbacks and double-entry ledgers.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {/* Card 1 */}
            <div className="p-8 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 relative group hover:border-emerald-500/50 transition">
              <div className="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center mb-6">
                <Lock className="w-6 h-6" />
              </div>
              <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">
                Double-Entry Ledger Integrity
              </h3>
              <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                Points are stored with DECIMAL(16,4) precision. No lost fractions, zero silent deductions, and fully auditable accounting.
              </p>
            </div>

            {/* Card 2 */}
            <div className="p-8 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 relative group hover:border-emerald-500/50 transition">
              <div className="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center mb-6">
                <Zap className="w-6 h-6" />
              </div>
              <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">
                Real-Time S2S Webhooks
              </h3>
              <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                BitLabs, CPX Research, Offerwall.gg, and Offerwall.com directly notify our backend for instantaneous reward credits.
              </p>
            </div>

            {/* Card 3 */}
            <div className="p-8 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 relative group hover:border-emerald-500/50 transition">
              <div className="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center mb-6">
                <Wallet className="w-6 h-6" />
              </div>
              <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">
                Pure Crypto Cashouts
              </h3>
              <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                Withdraw to Tether USDT (TRC-20/BEP-20), Litecoin (LTC), or native Bitcoin (BTC). Low fees and swift manual/automated reviews.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Network Providers Section */}
      <section className="py-20 bg-slate-50 dark:bg-slate-950 transition-colors">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col md:flex-row items-center justify-between gap-8 mb-12">
            <div>
              <h2 className="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                Integrated Offer Networks
              </h2>
              <p className="mt-2 text-slate-500 text-sm">
                4 industry-leading providers serving thousands of high-converting tasks daily.
              </p>
            </div>
            <Link
              href="/register"
              className="flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:underline"
            >
              <span>Explore all offers</span>
              <ArrowRight className="w-4 h-4" />
            </Link>
          </div>

          <div className="grid grid-cols-2 lg:grid-cols-4 gap-6">
            {["BitLabs", "CPX Research", "Offerwall.gg", "Offerwall.com"].map((network) => (
              <div
                key={network}
                className="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center shadow-sm"
              >
                <div className="w-12 h-12 rounded-xl bg-gradient-to-tr from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 flex items-center justify-center font-black text-slate-800 dark:text-white text-lg mx-auto mb-3">
                  {network.substring(0, 2).toUpperCase()}
                </div>
                <h4 className="font-bold text-slate-900 dark:text-white text-base">{network}</h4>
                <p className="text-xs text-emerald-500 font-medium mt-1">Verified Partner</p>
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}
