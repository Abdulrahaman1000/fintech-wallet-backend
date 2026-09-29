import { useEffect, useState } from "react";
import { ArrowDownLeft, ArrowUpRight, ChevronLeft, ChevronRight, Search } from "lucide-react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { getTransactions, getTransaction, type Transaction } from "../api/transactions";

const currencyMeta: Record<string, { symbol: string }> = {
  NGN: { symbol: "₦" },
  USD: { symbol: "$" },
  USDT: { symbol: "₮" },
};

function formatAmount(minorUnits: number) {
  return (minorUnits / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" });
}

function typeLabel(txn: Transaction) {
  if (txn.type === "funding") return "Funding";
  if (txn.type === "transfer_credit") return "Transfer in";
  return "Transfer out";
}

function titleFor(txn: Transaction) {
  if (txn.type === "funding") return "Wallet funding";
  if (txn.type === "transfer_credit") return `Payment from ${txn.counterparty?.name ?? "user"}`;
  return `Transfer to ${txn.counterparty?.name ?? "user"}`;
}

export function TransactionsPage() {
  const navigate = useNavigate();
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    setLoading(true);
    setError("");
    getTransactions(page)
      .then((res) => {
        setTransactions(res.data);
        setLastPage(res.last_page);
      })
      .catch(() => setError("Could not load transactions."))
      .finally(() => setLoading(false));
  }, [page]);

  return (
    <div className="dashboard-content">
      <div className="page-heading">
        <div>
          <p className="eyebrow">YOUR ACTIVITY</p>
          <h1>Transaction history</h1>
          <p className="heading-subtitle">A complete record of your wallet activity.</p>
        </div>
      </div>
      <section className="transactions-panel history-panel">
        <div className="history-toolbar">
          <div className="search-field"><Search size={17} /><input placeholder="Search transactions" aria-label="Search transactions" disabled /></div>
        </div>

        {error && <div className="inline-error">{error}</div>}

        {loading ? (
          <p style={{ padding: "20px 0" }}>Loading...</p>
        ) : transactions.length === 0 ? (
          <p style={{ padding: "20px 0", color: "#888" }}>No transactions yet.</p>
        ) : (
          <div className="table-wrap">
            <table className="transaction-table">
              <thead>
                <tr><th>TRANSACTION</th><th>TYPE</th><th>DATE</th><th>REFERENCE</th><th>STATUS</th><th className="align-right">AMOUNT</th></tr>
              </thead>
              <tbody>
                {transactions.map((txn) => {
                  const incoming = txn.type === "funding" || txn.type === "transfer_credit";
                  const Icon = incoming ? ArrowDownLeft : ArrowUpRight;
                  const meta = currencyMeta[txn.currency] ?? { symbol: "" };
                  return (
                    <tr key={txn.id} onClick={() => navigate(`/transactions/${txn.id}`)}>
                      <td>
                        <span className={`transaction-icon ${incoming ? "incoming" : "outgoing"}`}><Icon size={16} /></span>
                        <span className="table-name">{titleFor(txn)}<small>{txn.currency}</small></span>
                      </td>
                      <td>{typeLabel(txn)}</td>
                      <td>{formatDate(txn.created_at)}</td>
                      <td className="reference-cell">{txn.reference}</td>
                      <td><span className={`status-pill ${txn.status}`}>{txn.status}</span></td>
                      <td className={`align-right transaction-amount ${incoming ? "positive" : ""}`}>
                        {incoming ? "+" : "−"} {meta.symbol}{formatAmount(txn.amount)}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        <div className="pagination">
          <span>Page <strong>{page}</strong> of <strong>{lastPage}</strong></span>
          <div>
            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)} aria-label="Previous page"><ChevronLeft size={16} /></button>
            <button disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)} aria-label="Next page"><ChevronRight size={16} /></button>
          </div>
        </div>
      </section>
    </div>
  );
}

export function TransactionDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [txn, setTxn] = useState<Transaction | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    setError("");
    getTransaction(id)
      .then((res) => setTxn(res.transaction))
      .catch((err) => {
        if (err.response?.status === 404) {
          setError("This transaction was not found, or you don't have access to it.");
        } else {
          setError("Could not load transaction details.");
        }
      })
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div className="dashboard-content"><p style={{ padding: "40px 0" }}>Loading...</p></div>;

  if (error || !txn) {
    return (
      <div className="dashboard-content">
        <div className="page-heading"><div><h1>Transaction not found</h1></div></div>
        <div className="inline-error">{error}</div>
        <button className="primary-button" style={{ marginTop: 20 }} onClick={() => navigate("/transactions")}>Back to transactions</button>
      </div>
    );
  }

  const incoming = txn.type === "funding" || txn.type === "transfer_credit";
  const meta = currencyMeta[txn.currency] ?? { symbol: "" };

  return (
    <div className="dashboard-content">
      <div className="page-heading">
        <div>
          <p className="eyebrow">TRANSACTION DETAIL</p>
          <h1>{titleFor(txn)}</h1>
        </div>
      </div>
      <section className="placeholder-card" style={{ textAlign: "left" }}>
        <p className={`transaction-amount ${incoming ? "positive" : ""}`} style={{ fontSize: 32, marginBottom: 20 }}>
          {incoming ? "+" : "−"} {meta.symbol}{formatAmount(txn.amount)} <small>{txn.currency}</small>
        </p>
        <dl className="detail-list">
          <div><dt>Status</dt><dd><span className={`status-pill ${txn.status}`}>{txn.status}</span></dd></div>
          <div><dt>Type</dt><dd>{typeLabel(txn)}</dd></div>
          <div><dt>Reference</dt><dd>{txn.reference}</dd></div>
          <div><dt>Date</dt><dd>{formatDate(txn.created_at)}</dd></div>
          <div><dt>Balance after</dt><dd>{meta.symbol}{formatAmount(txn.balance_after)}</dd></div>
          {txn.counterparty && <div><dt>{incoming ? "From" : "To"}</dt><dd>{txn.counterparty.name} ({txn.counterparty.email})</dd></div>}
          {txn.narration && <div><dt>Narration</dt><dd>{txn.narration}</dd></div>}
        </dl>
        <Link className="primary-button inline-button" to="/transactions" style={{ marginTop: 24, display: "inline-flex" }}>Back to transactions <ChevronRight size={16} /></Link>
      </section>
    </div>
  );
}