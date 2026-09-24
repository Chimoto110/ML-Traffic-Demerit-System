import React from "react";
import { Link } from "react-router-dom";
import { Info, ShieldCheck, Scale, BarChart3 } from "lucide-react";
import AuthLayout from "@/components/AuthLayout";

const ROTATING_BACKGROUND_IMAGES = [
  "https://images.pexels.com/photos/30661396/pexels-photo-30661396.jpeg?auto=compress&cs=tinysrgb&w=1800",
  "https://images.pexels.com/photos/15496523/pexels-photo-15496523.jpeg?auto=compress&cs=tinysrgb&w=1800",
  "https://images.pexels.com/photos/36964425/pexels-photo-36964425.jpeg?auto=compress&cs=tinysrgb&w=1800",
];

export default function About() {
  return (
    <AuthLayout
      icon={Info}
      title="About This System"
      subtitle="A quick overview of how the Traffic Demerit System works"
      backgroundImages={ROTATING_BACKGROUND_IMAGES}
      backgroundChangeMs={3000}
      footer={
        <>
          Ready to continue? <Link to="/login" className="text-primary font-medium hover:underline">Log in</Link>
        </>
      }
    >
      <div className="space-y-4 text-sm text-slate-200">
        <p>
          The Traffic Demerit System helps road safety teams track violations, assign demerit points,
          and enforce sanctions consistently. It brings officers, administrators, and drivers into one
          workflow with clear records and accountability.
        </p>

        <div className="grid gap-3 sm:grid-cols-3">
          <div className="rounded-xl border border-cyan-100/20 bg-slate-900/40 p-3">
            <ShieldCheck className="mb-2 h-4 w-4 text-cyan-300" aria-hidden="true" />
            <div className="text-xs font-semibold uppercase tracking-wide text-cyan-100">Enforcement</div>
            <p className="mt-1 text-xs text-slate-300">Officers log violations and the system calculates points instantly.</p>
          </div>
          <div className="rounded-xl border border-cyan-100/20 bg-slate-900/40 p-3">
            <Scale className="mb-2 h-4 w-4 text-orange-300" aria-hidden="true" />
            <div className="text-xs font-semibold uppercase tracking-wide text-orange-100">Fair Process</div>
            <p className="mt-1 text-xs text-slate-300">Drivers can view ledgers, sanctions, and submit appeals for review.</p>
          </div>
          <div className="rounded-xl border border-cyan-100/20 bg-slate-900/40 p-3">
            <BarChart3 className="mb-2 h-4 w-4 text-emerald-300" aria-hidden="true" />
            <div className="text-xs font-semibold uppercase tracking-wide text-emerald-100">Oversight</div>
            <p className="mt-1 text-xs text-slate-300">Administrators monitor trends, compliance reports, and operational data.</p>
          </div>
        </div>

        <p className="text-xs text-slate-300">
          Goal: safer roads through transparent enforcement, better data, and faster decision-making.
        </p>
      </div>
    </AuthLayout>
  );
}
