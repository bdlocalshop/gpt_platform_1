"use client";

import React, { useEffect, useState, Suspense } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useAuth } from "@/context/AuthContext";
import { api, ApiError } from "@/lib/api";
import { Mail, CheckCircle, AlertCircle, RefreshCw, ArrowRight, ShieldCheck, Coins } from "lucide-react";

function VerifyEmailContent() {
  const { user, resendVerification, refreshUser } = useAuth();
  const searchParams = useSearchParams();

  const [verifying, setVerifying] = useState(false);
  const [verifiedSuccess, setVerifiedSuccess] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [resending, setResending] = useState(false);
  const [resendStatus, setResendStatus] = useState<string | null>(null);

  useEffect(() => {
    const verifyUrl = searchParams.get("verify_url");
    if (verifyUrl) {
      handleVerification(verifyUrl);
    }
  }, [searchParams]);

  const handleVerification = async (url: string) => {
    setVerifying(true);
    setErrorMessage(null);

    try {
      const res = await api.get(url);
      if (res.success) {
        setVerifiedSuccess(true);
        await refreshUser();
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage("Failed to verify email. The link may have expired.");
      }
    } finally {
      setVerifying(false);
    }
  };

  const handleResend = async () => {
    setResending(true);
    setResendStatus(null);
    setErrorMessage(null);

    try {
      const msg = await resendVerification();
      setResendStatus(msg);
    } catch (err) {
      if (err instanceof ApiError) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage("Failed to resend verification link.");
      }
    } finally {
      setResending(false);
    }
  };

  return (
    <div className="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-50 dark:bg-slate-950 transition-colors">
      <div className="max-w-md w-full space-y-6">
        <div className="bg-white dark:bg-slate-900 py-10 px-6 sm:px-10 shadow-xl rounded-2xl border border-slate-200 dark:border-slate-800 text-center">
          {verifying ? (
            <div className="py-6 space-y-4">
              <RefreshCw className="w-12 h-12 text-emerald-500 animate-spin mx-auto" />
              <h3 className="text-xl font-bold text-slate-900 dark:text-white">
                Verifying your email...
              </h3>
              <p className="text-sm text-slate-500">Please wait while we confirm your signature.</p>
            </div>
          ) : verifiedSuccess || user?.is_email_verified ? (
            <div className="space-y-4">
              <div className="w-14 h-14 bg-emerald-100 dark:bg-emerald-950/50 rounded-full flex items-center justify-center text-emerald-500 mx-auto">
                <CheckCircle className="w-8 h-8" />
              </div>
              <h2 className="text-2xl font-bold text-slate-900 dark:text-white">
                Email Verified!
              </h2>
              <p className="text-sm text-slate-600 dark:text-slate-400">
                Your email has been verified. Welcome bonus points will be applied to your ledger account.
              </p>

              <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/40 text-left flex items-center gap-3">
                <Coins className="w-6 h-6 text-emerald-500 flex-shrink-0" />
                <div>
                  <p className="text-xs font-bold text-slate-900 dark:text-white">Signup Bonus Active</p>
                  <p className="text-[11px] text-slate-500">100 Points ($0.10 USD) welcome reward</p>
                </div>
              </div>

              <Link
                href="/dashboard"
                className="mt-6 w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 shadow-md shadow-emerald-500/20 transition-all"
              >
                <span>Continue to Dashboard</span>
                <ArrowRight className="w-4 h-4" />
              </Link>
            </div>
          ) : (
            <div className="space-y-4">
              <div className="w-14 h-14 bg-amber-100 dark:bg-amber-950/50 rounded-full flex items-center justify-center text-amber-500 mx-auto">
                <Mail className="w-8 h-8" />
              </div>
              <h2 className="text-2xl font-bold text-slate-900 dark:text-white">
                Verify your email
              </h2>
              <p className="text-sm text-slate-600 dark:text-slate-400">
                We sent a confirmation link to:
              </p>
              <p className="font-semibold text-sm text-slate-900 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 py-1.5 px-3 rounded-lg inline-block">
                {user?.email || "your registered email"}
              </p>

              {errorMessage && (
                <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 flex items-start gap-2 text-rose-700 dark:text-rose-400 text-xs text-left">
                  <AlertCircle className="w-4 h-4 flex-shrink-0 mt-0.5" />
                  <span>{errorMessage}</span>
                </div>
              )}

              {resendStatus && (
                <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/50 flex items-start gap-2 text-emerald-700 dark:text-emerald-400 text-xs text-left">
                  <ShieldCheck className="w-4 h-4 flex-shrink-0 mt-0.5" />
                  <span>{resendStatus}</span>
                </div>
              )}

              <div className="pt-4 space-y-3">
                <button
                  onClick={handleResend}
                  disabled={resending}
                  className="w-full flex items-center justify-center gap-2 py-2 px-4 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-200 transition"
                >
                  <RefreshCw className={`w-4 h-4 ${resending ? "animate-spin" : ""}`} />
                  <span>{resending ? "Sending..." : "Resend Verification Email"}</span>
                </button>

                <Link
                  href="/dashboard"
                  className="block text-xs text-slate-500 hover:text-emerald-500 transition"
                >
                  Skip for now and go to dashboard
                </Link>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default function VerifyEmailPage() {
  return (
    <Suspense
      fallback={
        <div className="flex-1 flex items-center justify-center p-12">
          <RefreshCw className="w-8 h-8 animate-spin text-emerald-500" />
        </div>
      }
    >
      <VerifyEmailContent />
    </Suspense>
  );
}
