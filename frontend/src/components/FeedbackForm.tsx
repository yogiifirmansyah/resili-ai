"use client";

import { useState } from "react";

interface FeedbackFormProps {
  onSubmit: (values: {
    customer_name: string;
    feedback_text: string;
  }) => void;
  isSubmitting: boolean;
}

export function FeedbackForm({ onSubmit, isSubmitting }: FeedbackFormProps) {
  const [customerName, setCustomerName] = useState("");
  const [feedbackText, setFeedbackText] = useState("");

  function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const trimmedText = feedbackText.trim();
    if (!trimmedText) {
      return;
    }

    onSubmit({
      customer_name: customerName.trim(),
      feedback_text: trimmedText,
    });

    setFeedbackText("");
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="flex h-full flex-col gap-5 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm"
    >
      <div>
        <h2 className="text-lg font-semibold text-zinc-900">Kirim Keluhan</h2>
        <p className="mt-1 text-sm text-zinc-500">
          Feedback dikirim ke API ResiliAI dengan optimistic UI.
        </p>
      </div>

      <div className="space-y-2">
        <label
          htmlFor="customer_name"
          className="block text-sm font-medium text-zinc-700"
        >
          Nama pelanggan{" "}
          <span className="font-normal text-zinc-400">(opsional)</span>
        </label>
        <input
          id="customer_name"
          type="text"
          value={customerName}
          onChange={(event) => setCustomerName(event.target.value)}
          placeholder="Contoh: Budi Santoso"
          className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          disabled={isSubmitting}
        />
      </div>

      <div className="space-y-2">
        <label
          htmlFor="feedback_text"
          className="block text-sm font-medium text-zinc-700"
        >
          Isi keluhan
        </label>
        <textarea
          id="feedback_text"
          value={feedbackText}
          onChange={(event) => setFeedbackText(event.target.value)}
          placeholder="Jelaskan keluhan pelanggan..."
          rows={6}
          required
          className="w-full resize-y rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
          disabled={isSubmitting}
        />
      </div>

      <button
        type="submit"
        disabled={isSubmitting || !feedbackText.trim()}
        className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-indigo-300"
      >
        {isSubmitting ? "Mengirim..." : "Kirim Keluhan"}
      </button>
    </form>
  );
}
