import type {
  CreateFeedbackPayload,
  CreateFeedbackResult,
  Feedback,
  FeedbackInsight,
} from "@/types/feedback";

const API_BASE = (() => {
  const raw = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";
  const trimmed = raw.replace(/\/$/, "");
  return trimmed.endsWith("/api") ? trimmed : `${trimmed}/api`;
})();

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public details?: unknown,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

async function parseJson<T>(response: Response): Promise<T | null> {
  const text = await response.text();
  if (!text) {
    return null;
  }

  try {
    return JSON.parse(text) as T;
  } catch {
    return null;
  }
}

async function request<T>(
  path: string,
  options: RequestInit = {},
): Promise<{ data: T; status: number }> {
  const response = await fetch(`${API_BASE}${path}`, {
    cache: "no-store",
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "Cache-Control": "no-cache",
      Pragma: "no-cache",
      ...options.headers,
    },
  });

  const data = await parseJson<T>(response);

  if (!response.ok) {
    const message =
      (data as { message?: string } | null)?.message ??
      `Request failed with status ${response.status}`;
    throw new ApiError(response.status, message, data);
  }

  return { data: data as T, status: response.status };
}

export async function fetchFeedback(): Promise<Feedback[]> {
  const { data } = await request<Feedback[]>(
    `/feedback?_=${Date.now()}`,
  );
  return data ?? [];
}

export async function fetchFeedbackInsight(): Promise<FeedbackInsight> {
  const { data } = await request<FeedbackInsight>(
    `/feedback/insight?_=${Date.now()}`,
  );

  if (!data?.insight) {
    throw new ApiError(502, "Insight response is empty.");
  }

  return data;
}

export async function createFeedback(
  payload: CreateFeedbackPayload,
): Promise<CreateFeedbackResult> {
  const response = await fetch(`${API_BASE}/feedback`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });

  const data = await parseJson<Feedback | { message: string; id: string; queued: boolean }>(
    response,
  );

  if (response.status === 201 && data) {
    return { type: "created", feedback: data as Feedback };
  }

  if (response.status === 202 && data && "queued" in data && data.queued) {
    return {
      type: "queued",
      id: data.id,
      message: data.message,
    };
  }

  const message =
    (data as { message?: string } | null)?.message ??
    `Request failed with status ${response.status}`;
  throw new ApiError(response.status, message, data);
}
