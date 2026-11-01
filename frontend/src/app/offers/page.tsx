"use client";

import React, { useState, useEffect } from "react";
import { useAuth } from "@/context/AuthContext";
import { api } from "@/lib/api";
import {
  Compass,
  Coins,
  ExternalLink,
  Maximize2,
  Minimize2,
  RefreshCw,
  X,
  Search,
  Star,
  Clock,
  Layers,
  Sparkles,
  Gamepad2,
  FileQuestion,
  TrendingUp,
  ShieldCheck,
  Smartphone,
  Monitor,
} from "lucide-react";
import Link from "next/link";

interface Provider {
  id: string;
  name: string;
  description: string;
  category: string;
  tag: string;
  color: string;
  rating: number;
  iframe_url: string;
  supported_types: string[];
}

interface Offer {
  id: string;
  provider: string;
  title: string;
  description: string;
  category: string;
  device: string;
  payout_points: number;
  payout_usd: number;
  badge: string;
  icon: string;
  click_url: string;
  difficulty: string;
  estimated_time: string;
}

export default function OffersPage() {
  const { user, isAuthenticated, walletBalance } = useAuth();

  const [providers, setProviders] = useState<Provider[]>([]);
  const [offers, setOffers] = useState<Offer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Active view states
  const [activeTab, setActiveTab] = useState<"featured" | "walls">("featured");
  const [selectedCategory, setSelectedCategory] = useState<string>("All");
  const [searchQuery, setSearchQuery] = useState("");
  const [sortBy, setSortBy] = useState<"payout" | "time">("payout");

  // iFrame modal states
  const [activeIframeProvider, setActiveIframeProvider] = useState<Provider | null>(null);
  const [iframeFullscreen, setIframeFullscreen] = useState(false);
  const [iframeKey, setIframeKey] = useState(0);

  useEffect(() => {
    async function loadOfferData() {
      try {
        setLoading(true);
        setError(null);

        const [providersRes, offersRes] = await Promise.all([
          api.get<{ providers: Provider[] }>("/api/v1/offers/providers"),
          api.get<{ offers: Offer[] }>("/api/v1/offers/featured"),
        ]);

        if (providersRes.data?.providers) {
          setProviders(providersRes.data.providers);
        }
        if (offersRes.data?.offers) {
          setOffers(offersRes.data.offers);
        }
      } catch (err: unknown) {
        if (err instanceof Error) {
          setError(err.message);
        } else {
          setError("Failed to load offerwalls and campaigns.");
        }
      } finally {
        setLoading(false);
      }
    }

    if (isAuthenticated) {
      loadOfferData();
    } else {
      setLoading(false);
    }
  }, [isAuthenticated]);

  // Filtered featured offers
  const filteredOffers = offers
    .filter((offer) => {
      const matchesCat =
        selectedCategory === "All" ||
        offer.category.toLowerCase() === selectedCategory.toLowerCase();
      const matchesSearch =
        offer.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
        offer.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
        offer.provider.toLowerCase().includes(searchQuery.toLowerCase());
      return matchesCat && matchesSearch;
    })
    .sort((a, b) => {
      if (sortBy === "payout") {
        return b.payout_points - a.payout_points;
      }
      return a.estimated_time.localeCompare(b.estimated_time);
    });

  const categories = ["All", "Gaming", "Surveys", "Finance"];

  if (!isAuthenticated && !loading) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-20 text-center">
        <div className="w-16 h-16 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center mx-auto mb-6">
          <Compass className="w-8 h-8" />
        </div>
        <h1 className="text-3xl font-extrabold text-slate-900 dark:text-white mb-3">
          Explore Monetization Offerwalls
        </h1>
        <p className="text-slate-600 dark:text-slate-400 max-w-md mx-auto mb-8">
          Sign in or create an account to start completing verified tasks, surveys, and mobile games to earn crypto points.
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

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      {/* Header Banner */}
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-slate-800 p-8 text-white shadow-xl">
        <div className="absolute right-0 top-0 translate-x-10 -translate-y-10 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none" />
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-400 text-xs font-semibold mb-3 border border-emerald-500/30">
              <Sparkles className="w-3.5 h-3.5" />
              Verified S2S Offerwalls
            </div>
            <h1 className="text-3xl sm:text-4xl font-extrabold tracking-tight">
              Earn Points & Real Crypto
            </h1>
            <p className="text-slate-400 text-sm sm:text-base mt-2 max-w-xl">
              Complete surveys, test new mobile games, and try out verified products. 1,000 Points = $1.00 USD cashout.
            </p>
          </div>

          {/* Current balance card */}
          <div className="bg-slate-800/80 backdrop-blur border border-slate-700 rounded-2xl p-4 sm:p-5 flex items-center gap-4 min-w-[240px]">
            <div className="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
              <Coins className="w-6 h-6" />
            </div>
            <div>
              <p className="text-xs text-slate-400 uppercase font-semibold">Your Balance</p>
              <p className="text-2xl font-black text-white">
                {Math.floor(parseFloat(walletBalance?.points.available || "0")).toLocaleString()}{" "}
                <span className="text-xs font-normal text-amber-400">PTS</span>
              </p>
              <p className="text-xs text-emerald-400 font-medium">
                ≈ ${parseFloat(walletBalance?.fiat_equivalent.available || "0.00").toFixed(2)} USD
              </p>
            </div>
          </div>
        </div>

        {/* Tab Switcher */}
        <div className="flex gap-2 mt-8 border-t border-slate-800/80 pt-6">
          <button
            onClick={() => setActiveTab("featured")}
            className={`px-5 py-2.5 rounded-xl text-sm font-semibold transition-all ${
              activeTab === "featured"
                ? "bg-emerald-500 text-white shadow-md shadow-emerald-500/20"
                : "bg-slate-800/60 text-slate-400 hover:text-white hover:bg-slate-800"
            }`}
          >
            Featured Direct Campaigns
          </button>
          <button
            onClick={() => setActiveTab("walls")}
            className={`px-5 py-2.5 rounded-xl text-sm font-semibold transition-all ${
              activeTab === "walls"
                ? "bg-emerald-500 text-white shadow-md shadow-emerald-500/20"
                : "bg-slate-800/60 text-slate-400 hover:text-white hover:bg-slate-800"
            }`}
          >
            Offerwall Networks ({providers.length})
          </button>
        </div>
      </div>

      {/* Error state */}
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

      {/* Loading state */}
      {loading && (
        <div className="py-20 text-center">
          <RefreshCw className="w-8 h-8 animate-spin text-emerald-500 mx-auto mb-3" />
          <p className="text-sm text-slate-500 dark:text-slate-400">Loading verified offerwalls & campaigns...</p>
        </div>
      )}

      {/* TAB 1: FEATURED CAMPAIGNS */}
      {!loading && activeTab === "featured" && (
        <div className="space-y-6">
          {/* Filters & Search */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="flex flex-wrap items-center gap-2">
              {categories.map((cat) => (
                <button
                  key={cat}
                  onClick={() => setSelectedCategory(cat)}
                  className={`px-4 py-1.5 rounded-xl text-xs font-semibold transition ${
                    selectedCategory === cat
                      ? "bg-emerald-500 text-white"
                      : "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700"
                  }`}
                >
                  {cat}
                </button>
              ))}
            </div>

            <div className="flex items-center gap-3">
              <div className="relative">
                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  placeholder="Search offers..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-9 pr-4 py-1.5 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white border-0 focus:ring-2 focus:ring-emerald-500 w-44 sm:w-56"
                />
              </div>

              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value as "payout" | "time")}
                className="px-3 py-1.5 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white border-0 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
              >
                <option value="payout">Highest Payout</option>
                <option value="time">Fastest Completion</option>
              </select>
            </div>
          </div>

          {/* Cards Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {filteredOffers.map((offer) => (
              <div
                key={offer.id}
                className="group relative rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 shadow-sm hover:shadow-xl hover:border-emerald-500/40 dark:hover:border-emerald-500/40 transition-all flex flex-col justify-between"
              >
                <div>
                  {/* Badge & Provider */}
                  <div className="flex items-center justify-between mb-4">
                    <span className="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                      {offer.badge}
                    </span>
                    <span className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                      {offer.provider.replace("_", ".")}
                    </span>
                  </div>

                  {/* Icon & Title */}
                  <div className="flex items-start gap-3.5 mb-3">
                    <img
                      src={offer.icon}
                      alt={offer.title}
                      className="w-14 h-14 rounded-2xl object-cover shadow-sm group-hover:scale-105 transition-transform"
                    />
                    <div>
                      <h3 className="font-bold text-base text-slate-900 dark:text-white group-hover:text-emerald-500 transition-colors line-clamp-1">
                        {offer.title}
                      </h3>
                      <div className="flex items-center gap-2 mt-1">
                        <span className="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                          {offer.device.includes("PC") ? (
                            <Monitor className="w-3.5 h-3.5" />
                          ) : (
                            <Smartphone className="w-3.5 h-3.5" />
                          )}
                          {offer.device}
                        </span>
                        <span className="text-slate-300 dark:text-slate-700">•</span>
                        <span className="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                          <Clock className="w-3.5 h-3.5" />
                          {offer.estimated_time}
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Description */}
                  <p className="text-xs text-slate-600 dark:text-slate-400 line-clamp-2 mb-4 leading-relaxed">
                    {offer.description}
                  </p>
                </div>

                {/* Footer Action */}
                <div className="pt-4 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                  <div>
                    <span className="text-lg font-black text-emerald-600 dark:text-emerald-400">
                      +{offer.payout_points.toLocaleString()}
                    </span>
                    <span className="text-xs font-semibold text-slate-400 ml-1">pts</span>
                    <p className="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                      (${offer.payout_usd.toFixed(2)} USD)
                    </p>
                  </div>

                  <a
                    href={offer.click_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-600 text-white shadow-md shadow-emerald-500/20 transition group-hover:translate-x-0.5"
                  >
                    Start Task
                    <ExternalLink className="w-3.5 h-3.5" />
                  </a>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 2: EMBEDDED OFFERWALL NETWORKS */}
      {!loading && activeTab === "walls" && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {providers.map((provider) => (
              <div
                key={provider.id}
                className="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm hover:shadow-xl transition-all flex flex-col justify-between"
              >
                <div>
                  <div className="flex items-center justify-between mb-4">
                    <span className="px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                      {provider.tag}
                    </span>
                    <div className="flex items-center gap-1 text-amber-500 text-xs font-bold">
                      <Star className="w-3.5 h-3.5 fill-amber-500" />
                      {provider.rating} / 5.0
                    </div>
                  </div>

                  <h3 className="text-2xl font-black text-slate-900 dark:text-white mb-2">
                    {provider.name}
                  </h3>
                  <p className="text-sm text-slate-600 dark:text-slate-400 mb-4 leading-relaxed">
                    {provider.description}
                  </p>

                  <div className="flex flex-wrap gap-2 mb-6">
                    {provider.supported_types.map((type) => (
                      <span
                        key={type}
                        className="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 capitalize"
                      >
                        {type.replace("_", " ")}
                      </span>
                    ))}
                  </div>
                </div>

                <div className="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center gap-3">
                  <button
                    onClick={() => setActiveIframeProvider(provider)}
                    className="flex-1 py-3 px-4 rounded-xl font-bold text-sm bg-emerald-500 hover:bg-emerald-600 text-white shadow-md shadow-emerald-500/20 transition flex items-center justify-center gap-2"
                  >
                    <Gamepad2 className="w-4 h-4" />
                    Open Offerwall
                  </button>

                  <a
                    href={provider.iframe_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="p-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition"
                    title="Open in new window"
                  >
                    <ExternalLink className="w-4 h-4" />
                  </a>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* IFRAME MODAL VIEWER */}
      {activeIframeProvider && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-2 sm:p-4 animate-in fade-in duration-200">
          <div
            className={`w-full bg-slate-900 border border-slate-700 rounded-3xl overflow-hidden shadow-2xl flex flex-col transition-all duration-300 ${
              iframeFullscreen
                ? "fixed inset-0 rounded-none border-none"
                : "max-w-5xl h-[88vh]"
            }`}
          >
            {/* Modal Top Bar */}
            <div className="px-6 py-4 bg-slate-950 border-b border-slate-800 flex items-center justify-between text-white">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">
                  {activeIframeProvider.name.charAt(0)}
                </div>
                <div>
                  <h3 className="font-bold text-sm sm:text-base leading-tight">
                    {activeIframeProvider.name} Wall
                  </h3>
                  <p className="text-xs text-slate-400">
                    User ID: <span className="text-emerald-400 font-mono">#{user?.id}</span> • S2S Instant Credit
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-2">
                <button
                  onClick={() => setIframeKey((prev) => prev + 1)}
                  className="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition"
                  title="Reload Frame"
                >
                  <RefreshCw className="w-4 h-4" />
                </button>

                <button
                  onClick={() => setIframeFullscreen(!iframeFullscreen)}
                  className="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition"
                  title="Toggle Fullscreen"
                >
                  {iframeFullscreen ? (
                    <Minimize2 className="w-4 h-4" />
                  ) : (
                    <Maximize2 className="w-4 h-4" />
                  )}
                </button>

                <button
                  onClick={() => setActiveIframeProvider(null)}
                  className="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-rose-500/20 hover:text-rose-400 transition"
                  title="Close Frame"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* iFrame Content Container */}
            <div className="relative flex-1 bg-slate-950">
              <iframe
                key={iframeKey}
                src={activeIframeProvider.iframe_url}
                title={`${activeIframeProvider.name} Offerwall`}
                className="w-full h-full border-0"
                sandbox="allow-scripts allow-same-origin allow-popups allow-forms allow-downloads"
                allow="camera; microphone; geolocation"
              />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
