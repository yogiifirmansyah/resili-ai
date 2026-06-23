"use client";

import { FeedbackForm } from "@/components/FeedbackForm";
import { FeedbackTable } from "@/components/FeedbackTable";
import { InsightPanel } from "@/components/InsightPanel";
import { ApiError, createFeedback, fetchFeedback } from "@/lib/api";
import type { CreateFeedbackPayload, FeedbackListItem } from "@/types/feedback";
import { useCallback, useEffect, useRef, useState } from "react";

const AI_STATUS_POLL_INTERVAL_MS = 2_000;
const AI_STATUS_MAX_POLLS = 15;

function needsAiStatusPolling(items: FeedbackListItem[]): boolean {
  return items.some(
    (item) =>
      item._optimistic === "pending-ai" ||
      item.status_ai === "pending" ||
      item.status_ai === "processing",
  );
}

function buildOptimisticItem(
  payload: CreateFeedbackPayload,
): FeedbackListItem {
  return {
    id: payload.id,
    customer_name: payload.customer_name?.trim() || null,
    feedback_text: payload.feedback_text,
    sentiment: null,
    category: null,
    status_ai: "pending",
    _optimistic: "sending",
  };
}

function upsertServerFeedback(
  current: FeedbackListItem[],
  feedback: FeedbackListItem,
): FeedbackListItem[] {
  const next = current.filter((item) => item.id !== feedback.id);
  return [{ ...feedback }, ...next];
}

export function Dashboard() {
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [infoMessage, setInfoMessage] = useState<string | null>(null);
  const [items, setItems] = useState<FeedbackListItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isError, setIsError] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isWatchingAi, setIsWatchingAi] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [degradedMessage, setDegradedMessage] = useState<string | null>(null);

  const pollCountRef = useRef(0);
  const pollIntervalRef = useRef<number | null>(null);
  const pollInFlightRef = useRef(false);
  const latestRequestIdRef = useRef(0);

  const stopWatchingAi = useCallback(() => {
    if (pollIntervalRef.current !== null) {
      window.clearInterval(pollIntervalRef.current);
      pollIntervalRef.current = null;
    }
    pollCountRef.current = 0;
    setIsWatchingAi(false);
  }, []);

  const [listVersion, setListVersion] = useState(0);

  const applyServerList = useCallback((data: FeedbackListItem[], message?: string | null) => {
    setItems(data.map((item) => ({ ...item })));
    setDegradedMessage(message ?? null);
    setListVersion((version) => version + 1);
    setIsError(false);
  }, []);

  const loadFeedback = useCallback(async (): Promise<FeedbackListItem[] | null> => {
    const requestId = ++latestRequestIdRef.current;

    try {
      const result = await fetchFeedback();

      if (requestId !== latestRequestIdRef.current) {
        return null;
      }

      applyServerList(result.items, result.degradedMessage);
      return result.items;
    } catch (error) {
      if (requestId !== latestRequestIdRef.current) {
        return null;
      }

      setDegradedMessage(null);
      setIsError(true);
      return null;
    }
  }, [applyServerList]);

  const refreshFeedback = useCallback(async (): Promise<FeedbackListItem[] | null> => {
    setIsRefreshing(true);
    try {
      return await loadFeedback();
    } finally {
      setIsRefreshing(false);
    }
  }, [loadFeedback]);

  const startWatchingAi = useCallback(() => {
    stopWatchingAi();
    setIsWatchingAi(true);

    const poll = async () => {
      if (pollInFlightRef.current) {
        return;
      }

      pollInFlightRef.current = true;
      pollCountRef.current += 1;

      try {
        const data = await loadFeedback();

        if (!data) {
          return;
        }

        if (!needsAiStatusPolling(data)) {
          stopWatchingAi();
          return;
        }

        if (pollCountRef.current >= AI_STATUS_MAX_POLLS) {
          stopWatchingAi();
        }
      } finally {
        pollInFlightRef.current = false;
      }
    };

    void poll();
    pollIntervalRef.current = window.setInterval(() => {
      void poll();
    }, AI_STATUS_POLL_INTERVAL_MS);
  }, [loadFeedback, stopWatchingAi]);

  useEffect(() => {
    void (async () => {
      setIsLoading(true);
      await loadFeedback();
      setIsLoading(false);
    })();

    return () => stopWatchingAi();
  }, [loadFeedback, stopWatchingAi]);

  async function handleSubmit(values: {
    customer_name: string;
    feedback_text: string;
  }) {
    const payload: CreateFeedbackPayload = {
      id: crypto.randomUUID(),
      feedback_text: values.feedback_text,
    };

    if (values.customer_name) {
      payload.customer_name = values.customer_name;
    }

    const previousItems = items;
    setErrorMessage(null);
    setInfoMessage(null);
    setIsSubmitting(true);
    setItems((current) => [buildOptimisticItem(payload), ...current]);

    try {
      const result = await createFeedback(payload);

      if (result.type === "queued") {
        setItems((current) =>
          current.map((item) =>
            item.id === payload.id
              ? { ...item, _optimistic: "pending-ai" as const }
              : item,
          ),
        );
        setInfoMessage(
          result.message ??
            "Feedback disimpan sementara di antrean fallback. Database sedang dalam pemulihan.",
        );
        startWatchingAi();
        return;
      }

      setItems((current) => upsertServerFeedback(current, result.feedback));
      await loadFeedback();
      startWatchingAi();
    } catch (error) {
      setItems(previousItems);
      const message =
        error instanceof ApiError
          ? error.message
          : "Gagal mengirim keluhan. Silakan coba lagi.";
      setErrorMessage(message);
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="min-h-full bg-zinc-100">
      <header className="border-b border-zinc-200 bg-white">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600">
              ResiliAI
            </p>
            <h1 className="mt-1 text-2xl font-bold text-zinc-900">
              Dashboard Keluhan
            </h1>
          </div>
          <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 ring-inset">
            API: localhost:8000
          </span>
        </div>
      </header>

      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        {infoMessage && (
          <div
            role="status"
            className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
          >
            <div className="flex items-start justify-between gap-4">
              <p>{infoMessage}</p>
              <button
                type="button"
                onClick={() => setInfoMessage(null)}
                className="shrink-0 text-amber-700 hover:text-amber-900"
                aria-label="Tutup notifikasi"
              >
                ×
              </button>
            </div>
          </div>
        )}

        {errorMessage && (
          <div
            role="alert"
            className="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
          >
            <div className="flex items-start justify-between gap-4">
              <p>{errorMessage}</p>
              <button
                type="button"
                onClick={() => setErrorMessage(null)}
                className="shrink-0 text-rose-600 hover:text-rose-800"
                aria-label="Tutup notifikasi"
              >
                ×
              </button>
            </div>
          </div>
        )}

        <div className="mb-8">
          <InsightPanel refreshToken={listVersion} />
        </div>

        <div className="grid gap-8 lg:grid-cols-[minmax(0,360px)_1fr]">
          <section>
            <FeedbackForm
              onSubmit={handleSubmit}
              isSubmitting={isSubmitting}
            />
          </section>

          <section className="min-w-0">
            <div className="mb-4 flex items-center justify-between">
              <div>
                <h2 className="text-lg font-semibold text-zinc-900">
                  Daftar Keluhan
                </h2>
                <p className="text-sm text-zinc-500">
                  Data dari GET /api/feedback
                  {isWatchingAi ? " · memantau hasil AI..." : ""}
                  {isRefreshing ? " · memperbarui..." : ""}
                </p>
              </div>
              <button
                type="button"
                onClick={() => void refreshFeedback()}
                disabled={isRefreshing}
                className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
              >
                Refresh
              </button>
            </div>

            <FeedbackTable
              key={listVersion}
              items={items}
              isLoading={isLoading}
              isError={isError}
              degradedMessage={degradedMessage}
            />
          </section>
        </div>
      </main>
    </div>
  );
}
