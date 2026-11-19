import React, { useState } from "react";
import { Link, Navigate, useNavigate } from "react-router-dom";
import { useAuth } from "@/lib/AuthContext";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { UserPlus, Mail, Lock, Loader2 } from "lucide-react";
import AuthLayout from "@/components/AuthLayout";

const REGISTER_BACKGROUND_IMAGES = [
  "https://images.pexels.com/photos/30661396/pexels-photo-30661396.jpeg?auto=compress&cs=tinysrgb&w=1800",
  "https://images.pexels.com/photos/15496523/pexels-photo-15496523.jpeg?auto=compress&cs=tinysrgb&w=1800",
  "https://images.pexels.com/photos/36964425/pexels-photo-36964425.jpeg?auto=compress&cs=tinysrgb&w=1800",
];

/** Driver-only self-registration against POST /api/auth/register. */
export default function Register() {
  const navigate = useNavigate();
  const { user, loading: authLoading, register } = useAuth();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [error, setError] = useState("");
  const [fieldErrors, setFieldErrors] = useState(null);
  const [pending, setPending] = useState(false);

  // Already signed in → straight to the driver dashboard.
  if (!authLoading && user) {
    return <Navigate to={user.role === "driver" ? "/driver" : `/${user.role}`} replace />;
  }

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setFieldErrors(null);
    if (password !== confirmPassword) {
      setError("Passwords do not match");
      return;
    }
    setPending(true);
    try {
      await register({ name, email, password });
      navigate("/driver", { replace: true });
    } catch (err) {
      // Real API validation message, e.g. "The email has already been taken."
      setError(err?.message || "Registration failed. Please try again.");
      setFieldErrors(err?.errors || null);
    } finally {
      setPending(false);
    }
  };

  return (
    <AuthLayout
      icon={UserPlus}
      title="Create your account"
      subtitle="Driver self-registration - join the traffic demerit portal"
      backgroundImages={REGISTER_BACKGROUND_IMAGES}
      backgroundChangeMs={3000}
      footer={
        <>
          Already registered?{" "}
          <Link to="/login/driver" className="text-primary font-medium hover:underline">
            Sign in
          </Link>
          {" · "}
          <Link to="/" className="text-primary font-medium hover:underline">
            Back to home
          </Link>
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
          <Label htmlFor="name">Full name</Label>
          <div className="relative">
            <UserPlus className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" aria-hidden="true" />
            <Input
              id="name"
              type="text"
              autoComplete="name"
              autoFocus
              placeholder="Jane Wanjiku"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="pl-10 h-12"
              required
            />
          </div>
          {fieldErrors?.name?.[0] && (
            <p className="text-xs text-destructive">{fieldErrors.name[0]}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="email">Email</Label>
          <div className="relative">
            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" aria-hidden="true" />
            <Input
              id="email"
              type="email"
              autoComplete="email"
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
              autoComplete="new-password"
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

        <div className="space-y-2">
          <Label htmlFor="confirm">Confirm password</Label>
          <div className="relative">
            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" aria-hidden="true" />
            <Input
              id="confirm"
              type="password"
              autoComplete="new-password"
              placeholder="••••••••"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              className="pl-10 h-12"
              required
            />
          </div>
        </div>

        <Button type="submit" className="w-full h-12 font-medium" disabled={pending}>
          {pending ? (
            <>
              <Loader2 className="w-4 h-4 mr-2 animate-spin" />
              Creating account...
            </>
          ) : (
            "Create account"
          )}
        </Button>
      </form>
    </AuthLayout>
  );
}
