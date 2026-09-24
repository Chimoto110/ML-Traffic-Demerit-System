import React from "react";
import { Button } from "@/components/ui/button";

const badgeClass = (status) => (
  status === "read"
    ? "bg-slate-100 text-slate-500"
    : "bg-amber-100 text-amber-700"
);

export default function NotificationCenter({ notifications = [], onToggleRead }) {
  if (notifications.length === 0) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">
        No notifications yet.
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {notifications.map((notification) => {
        const isRead = notification.status === "read";

        return (
          <div key={notification.id} className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div className="text-sm font-semibold text-slate-900">{notification.title}</div>
                <div className="mt-1 text-sm text-slate-600">{notification.message}</div>
                <div className="mt-2 text-xs text-slate-500">
                  {notification.created_at ? new Date(notification.created_at).toLocaleString() : ""}
                </div>
              </div>
              <div className="flex items-center gap-2">
                <span className={`rounded-full px-2 py-1 text-[11px] font-semibold ${badgeClass(notification.status)}`}>
                  {isRead ? "READ" : "UNREAD"}
                </span>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => onToggleRead(notification, isRead ? "unread" : "read")}
                >
                  Mark {isRead ? "Unread" : "Read"}
                </Button>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}