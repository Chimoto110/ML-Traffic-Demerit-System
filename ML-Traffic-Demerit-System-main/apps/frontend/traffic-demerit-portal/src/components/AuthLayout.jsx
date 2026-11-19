import React, { useEffect, useMemo, useState } from "react";
const compliancePhotoStrip = new URL("../assets/compliance-photo-strip.svg", import.meta.url).href;

const WEBSITE_BACKGROUND_IMAGE = "https://images.pexels.com/photos/15496495/pexels-photo-15496495.jpeg?auto=compress&cs=tinysrgb&w=1800";

export default function AuthLayout({
  icon: Icon,
  title,
  subtitle,
  footer = null,
  children,
  backgroundImage = WEBSITE_BACKGROUND_IMAGE,
  backgroundImages = [],
  backgroundChangeMs = 3000,
  showInfoPanel = true,
  overlayClassName = "bg-slate-950/32",
}) {
  const images = useMemo(() => {
    const list = Array.isArray(backgroundImages) ? backgroundImages.filter(Boolean) : [];
    if (list.length > 0) return list;
    return [backgroundImage];
  }, [backgroundImage, backgroundImages]);

  const [bgIndex, setBgIndex] = useState(0);

  useEffect(() => {
    setBgIndex(0);
  }, [images]);

  useEffect(() => {
    if (images.length <= 1) return;

    const interval = setInterval(() => {
      setBgIndex((current) => (current + 1) % images.length);
    }, Math.max(1000, Number(backgroundChangeMs) || 3000));

    return () => clearInterval(interval);
  }, [images, backgroundChangeMs]);

  const activeBackground = images[bgIndex] || backgroundImage;

  return (
    <div className="app-shell flex items-center justify-center p-4 md:p-8">
      <div
        className="pointer-events-none absolute inset-0 bg-center bg-cover"
        style={{ backgroundImage: `url(${activeBackground})` }}
      />
      <div className={`pointer-events-none absolute inset-0 ${overlayClassName}`} />
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        <div className="absolute -left-20 top-12 h-72 w-72 rounded-full bg-orange-400/25 blur-3xl" />
        <div className="absolute right-0 top-8 h-72 w-72 rounded-full bg-cyan-400/25 blur-3xl" />
      </div>

      <div className={`relative z-10 grid w-full overflow-hidden rounded-3xl border border-cyan-100/25 bg-slate-900/48 shadow-[0_30px_90px_-35px_rgba(0,0,0,0.8)] backdrop-blur ${showInfoPanel ? "max-w-5xl md:grid-cols-2" : "max-w-xl"}`}>
        {showInfoPanel && (
        <div className="hidden md:flex flex-col justify-between p-10 bg-gradient-to-br from-slate-900 via-slate-800 to-cyan-900 text-white">
          <div>
            <div className="hero-chip border-white/25 bg-white/10 text-slate-100">Traffic Safety Intelligence</div>
            <h2 className="mt-5 font-display text-4xl font-bold leading-tight">Urban Roads, Safer Decisions.</h2>
            <p className="mt-4 text-sm text-slate-200/90">Machine-assisted enforcement and compliance workflows for public road safety teams.</p>
            <div className="mt-6 overflow-hidden rounded-2xl border border-white/15">
              <img
                src={compliancePhotoStrip}
                alt="Road compliance intelligence map"
                className="h-28 w-full object-cover"
              />
            </div>
            <div className="mt-6 space-y-2 text-xs text-slate-200/90">
              <div>Real-time risk scoring</div>
              <div>Automated sanction triggers</div>
              <div>Audit-friendly review trails</div>
            </div>
          </div>
          <div className="text-xs text-slate-200/80">Traffic Demerit System</div>
        </div>
        )}

        <div className="p-6 md:p-9">
          <div className="mb-8 text-center md:text-left">
            <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary shadow-lg shadow-cyan-700/20 mb-4">
              <Icon className="w-7 h-7 text-primary-foreground" aria-hidden="true" />
            </div>
            <h1 className="font-display text-3xl font-bold tracking-tight text-orange-50">{title}</h1>
            {subtitle && <p className="text-slate-300 mt-2">{subtitle}</p>}
          </div>

          <div className="rounded-2xl border border-white/20 bg-slate-950/45 p-6 shadow-[0_15px_40px_-26px_rgba(0,0,0,0.8)] text-slate-100">
            {children}
          </div>

          {footer && (
            <p className="text-center text-sm text-slate-300 mt-6">{footer}</p>
          )}
        </div>
      </div>
    </div>
  );
}
