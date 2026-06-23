"use client";

import { ApiError, fetchFeedbackInsight } from "@/lib/api";
import { useCallback, useEffect, useState } from "react";

interface InsightPanelProps {
  refreshToken?: number;
}

export function InsightPanel({ refreshToken = 0 }: InsightPanelProps) {
  const [insight, setInsight] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const loadInsight = useCallback(async (isManualRefresh = false) => {
    if (isManualRefresh) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }

    setErrorMessage(null);

    try {
      const data = await fetchFeedbackInsight();
      setInsight(data.insight);
    } catch (error) {
      setInsight(null);
      const message =
        error instanceof ApiError
          ? error.message
          : "Gagal memuat executive summary dari Gemini AI.";
      setErrorMessage(message);
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  useEffect(() => {
    void loadInsight();
  }, [loadInsight, refreshToken]);

  return (
    <section className="rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 via-white to-violet-50 p-6 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600">
            Gemini AI
          </p>
          <h2 className="mt-1 text-lg font-semibold text-zinc-900">
            Executive Summary
          </h2>
          <p className="mt-1 text-sm text-zinc-500">
            Analisis 50–100 keluhan terbaru dari GET /api/feedback/insight
          </p>
        </div>
        <button
          type="button"
          onClick={() => void loadInsight(true)}
          disabled={isLoading || isRefreshing}
          className="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-sm font-medium text-indigo-700 transition hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {isRefreshing ? "Memperbarui..." : "Perbarui Insight"}
        </button>
      </div>

      <div className="mt-5">
        {isLoading && !insight && (
          <div className="space-y-3" aria-busy="true" aria-label="Memuat executive summary">
            <div className="h-3 w-full animate-pulse rounded bg-indigo-100" />
            <div className="h-3 w-5/6 animate-pulse rounded bg-indigo-100" />
            <div className="h-3 w-2/3 animate-pulse rounded bg-indigo-100" />
          </div>
        )}

        {!isLoading && errorMessage && (
          <div
            role="alert"
            className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
          >
            {errorMessage}
          </div>
        )}

        {!errorMessage && insight && (
          <p className="text-sm leading-7 text-zinc-800">{insight}</p>
        )}
      </div>
    </section>
  );
}
