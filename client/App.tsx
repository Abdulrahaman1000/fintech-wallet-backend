import "./global.css";
import { createRoot } from "react-dom/client";
import { BrowserRouter, Link, NavLink, Route, Routes, Navigate, useNavigate } from "react-router-dom";
import { ArrowLeftRight, ChevronDown, CircleHelp, Command, LayoutDashboard, LogOut, PlusCircle, ReceiptText } from "lucide-react";
import Index from "./pages/Index";
import Login from "./pages/Login";
import Register from "./pages/Register";
import { TransactionsPage, TransactionDetailPage } from "./pages/WalletPages";
import NotFound from "./pages/NotFound";
import { AuthProvider, useAuth } from "./context/AuthContext";
import { ProtectedRoute } from "./components/ProtectedRoute";

function WalletLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  async function handleLogout() {
    await logout();
    navigate("/login");
  }

  const initials = user?.name
    ?.split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase() ?? "?";

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <Link to="/" className="brand"><span className="brand-mark"><Command size={20} strokeWidth={2.5} /></span><span>euno<span className="brand-dot">.</span></span></Link>
        <div className="workspace-label">WORKSPACE</div>
        <nav className="side-nav">
          <NavLink to="/" end className={({ isActive }) => `nav-link ${isActive ? "active" : ""}`}><LayoutDashboard size={18} />Dashboard</NavLink>
          <NavLink to="/transactions" className={({ isActive }) => `nav-link ${isActive ? "active" : ""}`}><ReceiptText size={18} />Transactions</NavLink>
        </nav>
        <div className="sidebar-bottom">
          <div className="side-help"><span className="help-round"><CircleHelp size={16} /></span><span><strong>Need a hand?</strong><small>Visit our help center</small></span></div>
          <button className="profile-button"><span className="avatar">{initials}</span><span className="profile-copy"><strong>{user?.name}</strong><small>{user?.email}</small></span><ChevronDown size={15} /></button>
        </div>
      </aside>
      <main className="main-area">
        <header className="topbar">
          <button className="mobile-brand"><span className="brand-mark"><Command size={18} strokeWidth={2.5} /></span>euno<span className="brand-dot">.</span></button>
          <div className="breadcrumb">Workspace <span>/</span> <strong>Wallet</strong></div>
          <div className="topbar-right">
            <span className="secure-indicator"><i />All systems operational</span>
            <span className="avatar top-avatar">{initials}</span>
            <button className="logout-button" title="Log out" aria-label="Log out" onClick={handleLogout}><LogOut size={17} /></button>
          </div>
        </header>
        <div className="mobile-nav">
          <NavLink to="/" end>Overview</NavLink>
          <NavLink to="/transactions">Activity</NavLink>
        </div>
        <Routes>
          <Route path="/" element={<Index />} />
          <Route path="/transactions" element={<TransactionsPage />} />
          <Route path="/transactions/:id" element={<TransactionDetailPage />} />
          <Route path="*" element={<NotFound />} />
        </Routes>
        <footer className="app-footer"><span>© 2026 Euno Financial Technologies</span></footer>
      </main>
    </div>
  );
}

function AppRoutes() {
  const { user, loading } = useAuth();

  if (loading) return <div style={{ padding: 40 }}>Loading...</div>;

  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate to="/" replace /> : <Login />} />
      <Route path="/register" element={user ? <Navigate to="/" replace /> : <Register />} />
      <Route
        path="/*"
        element={
          <ProtectedRoute>
            <WalletLayout />
          </ProtectedRoute>
        }
      />
    </Routes>
  );
}

const App = () => (
  <BrowserRouter>
    <AuthProvider>
      <AppRoutes />
    </AuthProvider>
  </BrowserRouter>
);

createRoot(document.getElementById("root")!).render(<App />);