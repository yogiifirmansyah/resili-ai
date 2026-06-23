import type { FeedbackListItem } from "@/types/feedback";

function statusLabel(item: FeedbackListItem): string {
  if (item._optimistic === "sending") {
    return "Sending...";
  }
  if (item._optimistic === "pending-ai") {
    return "Pending AI";
  }

  switch (item.status_ai) {
    case "pending":
      return "Pending AI";
    case "processing":
      return "Processing";
    case "completed":
      return "Completed";
    case "failed":
      return "Failed";
    default:
      return item.status_ai;
  }
}

function statusClass(item: FeedbackListItem): string {
  if (item._optimistic === "sending") {
    return "bg-amber-100 text-amber-800 ring-amber-200";
  }
  if (item._optimistic === "pending-ai" || item.status_ai === "pending") {
    return "bg-sky-100 text-sky-800 ring-sky-200";
  }
  if (item.status_ai === "processing") {
    return "bg-indigo-100 text-indigo-800 ring-indigo-200";
  }
  if (item.status_ai === "completed") {
    return "bg-emerald-100 text-emerald-800 ring-emerald-200";
  }
  if (item.status_ai === "failed") {
    return "bg-rose-100 text-rose-800 ring-rose-200";
  }
  return "bg-zinc-100 text-zinc-700 ring-zinc-200";
}

function sentimentClass(sentiment: FeedbackListItem["sentiment"]): string {
  switch (sentiment) {
    case "positive":
      return "text-emerald-700";
    case "negative":
      return "text-rose-700";
    case "neutral":
      return "text-amber-700";
    default:
      return "text-zinc-400";
  }
}

interface FeedbackTableProps {
  items: FeedbackListItem[];
  isLoading: boolean;
  isError: boolean;
}

export function FeedbackTable({ items, isLoading, isError }: FeedbackTableProps) {
  if (isLoading) {
    return (
      <div className="flex h-48 items-center justify-center rounded-xl border border-zinc-200 bg-white">
        <p className="text-sm text-zinc-500">Memuat daftar keluhan...</p>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="flex h-48 items-center justify-center rounded-xl border border-rose-200 bg-rose-50">
        <p className="text-sm text-rose-700">
          Gagal memuat data. Pastikan API Laravel berjalan di localhost:8000.
        </p>
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="flex h-48 items-center justify-center rounded-xl border border-dashed border-zinc-300 bg-white">
        <p className="text-sm text-zinc-500">Belum ada keluhan. Kirim yang pertama!</p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-zinc-200 text-sm">
          <thead className="bg-zinc-50">
            <tr>
              <th className="px-4 py-3 text-left font-medium text-zinc-600">
                Pelanggan
              </th>
              <th className="px-4 py-3 text-left font-medium text-zinc-600">
                Keluhan
              </th>
              <th className="px-4 py-3 text-left font-medium text-zinc-600">
                Status AI
              </th>
              <th className="px-4 py-3 text-left font-medium text-zinc-600">
                Sentimen
              </th>
              <th className="px-4 py-3 text-left font-medium text-zinc-600">
                Kategori
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {items.map((item) => (
              <tr
                key={item.id}
                className={
                  item._optimistic === "sending"
                    ? "bg-amber-50/60"
                    : "hover:bg-zinc-50/80"
                }
              >
                <td className="px-4 py-3 align-top text-zinc-700">
                  {item.customer_name?.trim() || (
                    <span className="text-zinc-400">—</span>
                  )}
                </td>
                <td className="max-w-xs px-4 py-3 align-top text-zinc-800">
                  <p className="line-clamp-3 whitespace-pre-wrap">
                    {item.feedback_text}
                  </p>
                </td>
                <td className="px-4 py-3 align-top">
                  <span
                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${statusClass(item)}`}
                  >
                    {statusLabel(item)}
                  </span>
                </td>
                <td
                  className={`px-4 py-3 align-top capitalize ${sentimentClass(item.sentiment)}`}
                >
                  {item.sentiment ?? "—"}
                </td>
                <td className="px-4 py-3 align-top text-zinc-700">
                  {item.category ?? "—"}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
