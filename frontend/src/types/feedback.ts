export type FeedbackSentiment = "positive" | "neutral" | "negative";

export type FeedbackStatusAi =
  | "pending"
  | "processing"
  | "completed"
  | "failed";

export type OptimisticState = "sending" | "pending-ai";

export interface Feedback {
  id: string;
  customer_name: string | null;
  feedback_text: string;
  sentiment: FeedbackSentiment | null;
  category: string | null;
  status_ai: FeedbackStatusAi;
  created_at?: string;
  updated_at?: string;
}

export interface FeedbackListItem extends Feedback {
  _optimistic?: OptimisticState;
}

export interface CreateFeedbackPayload {
  id: string;
  customer_name?: string;
  feedback_text: string;
}

export type CreateFeedbackResult =
  | { type: "created"; feedback: Feedback }
  | { type: "queued"; id: string; message: string };
