"use client";

import dynamic from "next/dynamic";

const Dashboard = dynamic(
  () => import("@/components/Dashboard").then((mod) => mod.Dashboard),
  {
    ssr: false,
    loading: () => (
      <div className="flex min-h-full items-center justify-center bg-zinc-100">
        <p className="text-sm text-zinc-500">Memuat dashboard...</p>
      </div>
    ),
  },
);

export function DashboardClient() {
  return <Dashboard />;
}
