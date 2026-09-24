import React, { useState } from "react";
import { Link } from "react-router-dom";
import { appClient } from "@/api/appClient";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { LogIn, Mail, Lock, Loader2 } from "lucide-react";
import AuthLayout from "@/components/AuthLayout";
import GoogleIcon from "@/components/GoogleIcon";
import { safeReturnTo } from "@/lib/authReturnTo";

const LOGIN_BACKGROUND_IMAGES = [
  "https://images.pexels.com/photos/30661396/pexels-photo-30661396.jpeg?auto=compress&cs=tinysrgb&w=1800",
  "https://images.pexels.com/photos/15496523/pexels-photo-15496523.jpeg?auto=compress&cs=tinysrgb&w=1800",
  "https://images.pexels.com/photos/36964425/pexels-photo-36964425.jpeg?auto=compress&cs=tinysrgb&w=1800",
];

export default function Login() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  // Post-login destination (e.g. the MCP OAuth consent page sends users here
  // with returnTo so the grant flow can resume). Same-origin paths only.
  const returnTo = safeReturnTo();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      await appClient.auth.loginViaEmailPassword(email, password);
      window.location.href = returnTo;
    } catch (err) {
      setError(err.message || "Invalid email or password");
    } finally {
      setLoading(false);
    }
  };

  const handleGoogle = () => {
    appClient.auth.loginWithProvider("google", returnTo);
  };

  const fillDemo = (role) => {
    if (role === "admin") {
      setEmail("admin@ntsa.go.ke");
      setPassword("Admin@123");
      return;
    }

    if (role === "officer") {
      setEmail("officer@ntsa.go.ke");
      setPassword("Officer@123");
      return;
    }

    setEmail("driver@ntsa.go.ke");
    setPassword("Driver@123");
  };

  return (
    <AuthLayout
      icon={LogIn}
      title="Welcome back"
      subtitle="Log in to your account"
      backgroundImages={LOGIN_BACKGROUND_IMAGES}
      backgroundChangeMs={3000}
      showInfoPanel={false}
      overlayClassName="bg-slate-950/18"
      footer={
        <>
          Don't have an account?{" "}
          <Link
            to={"/register" + (returnTo !== "/" ? "?returnTo=" + encodeURIComponent(returnTo) : "")}
            className="text-primary font-medium hover:underline"
          >
            Create one
          </Link>
          {" · "}
          <Link to="/about" className="text-primary font-medium hover:underline">
            About us
          </Link>
        </>
      }
    >
      <Button
        variant="outline"
        className="w-full h-12 text-sm font-medium mb-6"
        onClick={handleGoogle}
      >
        <GoogleIcon className="w-5 h-5 mr-2" />
        Continue with Google
      </Button>

      <div className="relative mb-6">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-border" />
        </div>
        <div className="relative flex justify-center text-xs uppercase">
          <span className="bg-card px-3 text-muted-foreground">or</span>
        </div>
      </div>

      {error && (
        <div className="mb-4 p-3 rounded-lg bg-destructive/10 text-destructive text-sm">
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
        </div>
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <Label htmlFor="password">Password</Label>
            <Link to="/forgot-password" className="text-xs text-primary hover:underline">
              Forgot password?
            </Link>
          </div>
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
        </div>
        <Button type="submit" className="w-full h-12 font-medium" disabled={loading}>
          {loading ? (
            <>
              <Loader2 className="w-4 h-4 mr-2 animate-spin" />
              Logging in...
            </>
          ) : (
            "Log in"
          )}
        </Button>
      </form>

      <div className="mt-5 flex flex-wrap gap-2">
        <Button type="button" size="sm" variant="outline" className="border-cyan-100/20 bg-slate-900/70 text-slate-100 hover:bg-slate-800" onClick={() => fillDemo("admin")}>Admin</Button>
        <Button type="button" size="sm" variant="outline" className="border-cyan-100/20 bg-slate-900/70 text-slate-100 hover:bg-slate-800" onClick={() => fillDemo("officer")}>Officer</Button>
        <Button type="button" size="sm" variant="outline" className="border-cyan-100/20 bg-slate-900/70 text-slate-100 hover:bg-slate-800" onClick={() => fillDemo("driver")}>Driver</Button>
      </div>

    </AuthLayout>
  );
}
