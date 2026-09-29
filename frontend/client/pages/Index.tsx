import { useEffect, useState } from "react";
import { ArrowDownLeft, ArrowUpRight, ChevronRight, CircleHelp, Eye, EyeOff, Plus, WalletCards } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import { getWallets, fundWallet, type Wallet } from "../api/wallets";
import { createTransfer } from "../api/transfers";
import { getTransactions, type Transaction } from "../api/transactions";
import { useAuth } from "../context/AuthContext";

type ModalType = "fund" | "transfer" | null;

const currencyMeta: Record<string, { symbol: string; label: string; tone: string }> = {
  NGN: { symbol: "₦", label: "Nigerian naira", tone: "mint" },
  USD: { symbol: "$", label: "US dollar", tone: "blue" },
  USDT: { symbol: "₮", label: "Tether", tone: "violet" },
};

function formatAmount(minorUnits: number) {
  return (minorUnits / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function timeAgo(dateString: string) {
  const date = new Date(dateString);
  return date.toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" });
}

export default function Index() {
  const { user } = useAuth();
  const navigate = useNavigate();

  const [wallets, setWallets] = useState<Wallet[]>([]);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [loadingData, setLoadingData] = useState(true);
  const [loadError, setLoadError] = useState("");

  const [modal, setModal] = useState<ModalType>(null);
  const [showBalances, setShowBalances] = useState(true);
  const [submitted, setSubmitted] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");

  async function loadData() {
    setLoadingData(true);
    setLoadError("");
    try {
      const [walletsRes, txnsRes] = await Promise.all([getWallets(), getTransactions(1)]);
      setWallets(walletsRes.wallets);
      setTransactions(txnsRes.data.slice(0, 5));
    } catch (err) {
      setLoadError("Could not load your wallet data. Please try again.");
    } finally {
      setLoadingData(false);
    }
  }

  useEffect(() => {
    loadData();
  }, []);

  function closeModal() {
    setModal(null);
    setSubmitted(false);
    setIsSubmitting(false);
    setError("");
  }

  async function submitForm(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    const formData = new FormData(event.currentTarget);
    const currency = formData.get("currency") as string;
    const amount = Number(formData.get("amount"));

    if (!amount || amount <= 0) {
      setError("Enter a valid amount.");
      return;
    }

    setIsSubmitting(true);
    try {
      if (modal === "fund") {
        await fundWallet({
          currency,
          amount,
          idempotency_key: crypto.randomUUID(),
        });
      } else if (modal === "transfer") {
        const recipientEmail = formData.get("recipient_email") as string;
        const narration = (formData.get("narration") as string) || undefined;
        const reference = formData.get("reference") as string;
        await createTransfer({
          recipient_email: recipientEmail,
          currency,
          amount,
          reference,
          narration,
        });
      }
      setSubmitted(true);
      loadData(); // refresh balances + transactions in the background
    } catch (err: any) {
      const message = err.response?.data?.message || "Something went wrong. Please try again.";
      setError(message);
    } finally {
      setIsSubmitting(false);
    }
  }

  const firstName = user?.name?.split(" ")[0] ?? "there";

  return (
    <div className="dashboard-content">
      <div className="page-heading">
        <div>
          <p className="eyebrow">{new Date().toLocaleDateString(undefined, { weekday: "long", year: "numeric", month: "long", day: "numeric" }).toUpperCase()}</p>
          <h1>Good day, {firstName} <span className="wave">✳</span></h1>
          <p className="heading-subtitle">Here's what's happening with your money today.</p>
        </div>
        <button className="help-link" type="button"><CircleHelp size={17} /> Help center</button>
      </div>

      {loadError && <div className="inline-error">{loadError}</div>}

      <section className="balance-section" aria-label="Wallet balances">
        <div className="section-title-row">
          <div><h2>Your balances</h2><span className="section-caption">Across all your wallets</span></div>
          <button className="icon-button balance-toggle" aria-label={showBalances ? "Hide balances" : "Show balances"} onClick={() => setShowBalances(!showBalances)}>{showBalances ? <Eye size={17} /> : <EyeOff size={17} />}</button>
        </div>
        {loadingData ? (
          <div className="balance-grid">
            {[1, 2, 3].map((i) => <div key={i} className="balance-card" style={{ opacity: 0.5 }}><p>Loading...</p></div>)}
          </div>
        ) : (
          <div className="balance-grid">
            {wallets.map((wallet) => {
              const meta = currencyMeta[wallet.currency];
              return (
                <article key={wallet.currency} className={`balance-card ${meta.tone}`}>
                  <div className="balance-card-top"><div className="currency-mark">{meta.symbol}</div><span className="currency-code">{wallet.currency}</span></div>
                  <p className="wallet-label">{meta.label}</p>
                  <p className="wallet-amount">{showBalances ? `${meta.symbol}${formatAmount(wallet.balance)}` : "••••••••"}</p>
                  <div className="balance-card-bottom"><span>Available balance</span><WalletCards size={17} /></div>
                </article>
              );
            })}
          </div>
        )}
      </section>

      <section className="quick-actions" aria-label="Quick actions">
        <button className="action-card action-primary" onClick={() => setModal("fund")}><span className="action-icon"><Plus size={19} /></span><span><strong>Fund wallet</strong><small>Add money to your wallet</small></span><ChevronRight className="action-chevron" size={18} /></button>
        <button className="action-card" onClick={() => setModal("transfer")}><span className="action-icon send-icon"><ArrowUpRight size={19} /></span><span><strong>Send money</strong><small>Transfer to anyone, anywhere</small></span><ChevronRight className="action-chevron" size={18} /></button>
      </section>

      <section className="transactions-panel">
        <div className="section-title-row transactions-title"><div><h2>Recent transactions</h2><span className="section-caption">Your latest wallet activity</span></div><Link to="/transactions" className="view-all">View all <ChevronRight size={15} /></Link></div>
        {loadingData ? (
          <p style={{ padding: "20px 0" }}>Loading transactions...</p>
        ) : transactions.length === 0 ? (
          <p style={{ padding: "20px 0", color: "#888" }}>No transactions yet. Fund your wallet to get started.</p>
        ) : (
          <div className="transaction-list">
            {transactions.map((txn) => {
              const incoming = txn.type === "funding" || txn.type === "transfer_credit";
              const Icon = incoming ? ArrowDownLeft : ArrowUpRight;
              const title =
                txn.type === "funding" ? "Wallet funding" :
                txn.type === "transfer_credit" ? `Payment from ${txn.counterparty?.name ?? "user"}` :
                `Transfer to ${txn.counterparty?.name ?? "user"}`;
              return (
                <Link className="transaction-row" key={txn.id} to={`/transactions/${txn.id}`}>
                  <span className={`transaction-icon ${incoming ? "incoming" : "outgoing"}`}><Icon size={17} /></span>
                  <span className="transaction-description"><strong>{title}</strong><small>{timeAgo(txn.created_at)}</small></span>
                  <span className={`status-pill ${txn.status}`}>{txn.status}</span>
                  <span className={`transaction-amount ${incoming ? "positive" : ""}`}>{incoming ? "+" : "−"} {currencyMeta[txn.currency]?.symbol}{formatAmount(txn.amount)}<small>{txn.currency}</small></span>
                  <ChevronRight className="row-chevron" size={16} />
                </Link>
              );
            })}
          </div>
        )}
      </section>

      {modal && (
        <div className="modal-backdrop" onMouseDown={(event) => event.target === event.currentTarget && closeModal()}>
          <section className="wallet-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <button className="modal-close" onClick={closeModal} aria-label="Close">×</button>
            {submitted ? (
              <div className="success-state">
                <div className="success-check">✓</div>
                <h2>{modal === "fund" ? "Wallet funded" : "Transfer sent"}</h2>
                <p>{modal === "fund" ? "Your wallet balance has been updated." : "Your transfer is on its way."}</p>
                <button className="primary-button" onClick={closeModal}>Done</button>
              </div>
            ) : (
              <>
                <p className="eyebrow">{modal === "fund" ? "ADD MONEY" : "SEND MONEY"}</p>
                <h2 id="modal-title">{modal === "fund" ? "Fund your wallet" : "Make a transfer"}</h2>
                <p className="modal-description">{modal === "fund" ? "Choose a wallet and enter how much you'd like to add." : "Send money securely to another Euno wallet."}</p>
                {error && <div className="inline-error">{error}</div>}
                <form className="wallet-form" onSubmit={submitForm}>
                  {modal === "transfer" && <label>Recipient email<input name="recipient_email" type="email" placeholder="name@example.com" required /></label>}
                  <div className="form-row">
                    <label>Currency
                      <select name="currency" defaultValue="NGN">
                        <option value="NGN">NGN · Nigerian naira</option>
                        <option value="USD">USD · US dollar</option>
                        <option value="USDT">USDT · Tether</option>
                      </select>
                    </label>
                    <label>Amount<input name="amount" type="number" min="0.01" step="0.01" placeholder="0.00" required /></label>
                  </div>
                  {modal === "transfer" && (
                    <>
                      <label>Narration <span className="optional">Optional</span><input name="narration" placeholder="What's this for?" /></label>
                      <label>Reference<input name="reference" value={`EUN-${Date.now().toString().slice(-8)}`} readOnly /></label>
                    </>
                  )}
                  <button className="primary-button" type="submit" disabled={isSubmitting}>
                    {isSubmitting ? <><span className="button-spinner" /> Processing…</> : <>{modal === "fund" ? "Continue to fund" : "Send money"}<ArrowUpRight size={16} /></>}
                  </button>
                  <p className="secure-note">Securely protected with bank-level encryption</p>
                </form>
              </>
            )}
          </section>
        </div>
      )}
    </div>
  );
}