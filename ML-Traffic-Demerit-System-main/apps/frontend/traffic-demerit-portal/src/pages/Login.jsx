import React, { useState } from "react";
import { Link, Navigate, useNavigate, useParams } from "react-router-dom";
import { useAuth } from "@/lib/AuthContext";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Car, Shield, Building2, Mail, Lock, Loader2 } from "lucide-react";
import AuthLayout from "@/components/AuthLayout";

const LOGIN_BACKGROUND_IMAGES = [
  "https://images.pexels.com/photos/30661396/pexels-photo-30661396.jpeg?auto=compress&cs=tinysrgb&w=1800",
  "https://images.pexels.com/photos/15496523/pexels-photo-15496523.jpeg?auto=compress&cs=tinysrgb&w=1800",
  "https://images.pexels.com/photos/36964425/pexels-photo-36964425.jpeg?auto=compress&cs=tinysrgb&w=1800",
];

const PORTALS = {
  driver: {
    title: "Driver Portal",
    subtitle: "Sign in to view your demerit ledger, sanctions and payments",
    icon: Car,
  },
  officer: {
    title: "Officer Portal",
    subtitle: "Sign in to log violations and review enforcement records",
    icon: Shield,
  },
  admin: {
    title: "Admin Portal",
    subtitle: "Sign in to the national traffic administration console",
    icon: Building2,
  },
};

const PORTAL_ROLES = ["driver", "officer", "admin"];

export default function Login() {
  const { portal } = useParams();
  const navigate = useNavigate();
  const { user, loading: authLoading, login } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [fieldErrors, setFieldErrors] = useState(null);
  const [pending, setPending] = useState(false);

  const config = PORTALS[portal];

  // Unknown portal in the URL → back to the landing page.
  if (!config) {
    return <Navigate to="/" replace />;
  }

  // Already signed in → go straight to that user's portal.
  if (!authLoading && user) {
    return <Navigate to={PORTAL_ROLES.includes(user.role) ? `/${user.role}` : "/"} replace />;
  }

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setFieldErrors(null);
    setPending(true);
    try {
      const result = await login(portal, email, password);
      navigate(result?.redirect || `/${portal}`, { replace: true });
    } catch (err) {
      // Real API message, e.g. "Invalid credentials for this portal" or the
      // lockout notice. Rendered as text - never raw HTML.
      setError(err?.message || "Sign-in failed. Please try again.");
      setFieldErrors(err?.errors || null);
    } finally {
      setPending(false);
    }
  };

  return (
    <AuthLayout
      icon={config.icon}
      title={config.title}
      subtitle={config.subtitle}
      backgroundImages={LOGIN_BACKGROUND_IMAGES}
      backgroundChangeMs={3000}
      showInfoPanel={false}
      overlayClassName="bg-slate-950/18"
      footer={
        <>
          <Link to="/" className="text-primary font-medium hover:underline">
            Back to home
          </Link>
          {portal === "driver" && (
            <>
              {" · "}
              <Link to="/register" className="text-primary font-medium hover:underline">
                Create an account
              </Link>
            </>
          )}
        </>
      }
    >
      {error && (
        <div role="alert" className="mb-4 p-3 rounded-lg bg-destructive/10 border border-destructive/30 text-destructive text-sm">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="email">Email</Label>
          <div className="relative">
            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" aria-hidden="true" />
            <Input
              id="email"
              type="email"
              autoComplete="email"
              autoFocus
              placeholder="you@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="pl-10 h-12"
              required
            />
          </div>
          {fieldErrors?.email?.[0] && (
            <p className="text-xs text-destructive">{fieldErrors.email[0]}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="password">Password</Label>
          <div className="relative">
            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" aria-hidden="true" />
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              placeholder="••••••••"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="pl-10 h-12"
              required
            />
          </div>
          {fieldErrors?.password?.[0] && (
            <p className="text-xs text-destructive">{fieldErrors.password[0]}</p>
          )}
        </div>

        <Button type="submit" className="w-full h-12 font-medium" disabled={pending}>
          {pending ? (
            <>
              <Loader2 className="w-4 h-4 mr-2 animate-spin" />
              Signing in...
            </>
          ) : (
            "Sign in"
          )}
        </Button>
      </form>
    </AuthLayout>
  );
}
