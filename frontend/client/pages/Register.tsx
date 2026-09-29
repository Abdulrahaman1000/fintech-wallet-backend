import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { Eye, EyeOff } from "lucide-react";
import { useAuth } from "../context/AuthContext";

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: "", email: "", password: "", password_confirmation: "" });
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  function update(field: string, value: string) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      await register(form);
      navigate("/");
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      const firstError = errors ? Object.values(errors)[0]?.[0] : null;
      setError(firstError || err.response?.data?.message || "Registration failed.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="auth-screen">
      <form className="auth-card" onSubmit={handleSubmit}>
        <h1>Create your account</h1>
        <p className="heading-subtitle">Start using your wallet in seconds.</p>
        {error && <div className="inline-error">{error}</div>}
        <label>Full name<input value={form.name} onChange={(e) => update("name", e.target.value)} required /></label>
        <label>Email<input type="email" value={form.email} onChange={(e) => update("email", e.target.value)} required /></label>
        <label>
          Password
          <div className="password-field">
            <input
              type={showPassword ? "text" : "password"}
              value={form.password}
              onChange={(e) => update("password", e.target.value)}
              required
            />
            <button
              type="button"
              className="password-toggle"
              onClick={() => setShowPassword((s) => !s)}
              aria-label={showPassword ? "Hide password" : "Show password"}
            >
              {showPassword ? <EyeOff size={17} /> : <Eye size={17} />}
            </button>
          </div>
        </label>
        <label>
          Confirm password
          <div className="password-field">
            <input
              type={showConfirm ? "text" : "password"}
              value={form.password_confirmation}
              onChange={(e) => update("password_confirmation", e.target.value)}
              required
            />
            <button
              type="button"
              className="password-toggle"
              onClick={() => setShowConfirm((s) => !s)}
              aria-label={showConfirm ? "Hide password" : "Show password"}
            >
              {showConfirm ? <EyeOff size={17} /> : <Eye size={17} />}
            </button>
          </div>
        </label>
        <button className="primary-button" type="submit" disabled={loading}>
          {loading ? "Creating account..." : "Register"}
        </button>
        <p>Already have an account? <Link to="/login">Log in</Link></p>
      </form>
    </div>
  );
}