import client from "./client";

export interface Transaction {
  id: number;
  reference: string;
  type: "funding" | "transfer_debit" | "transfer_credit";
  status: "pending" | "success" | "failed";
  currency: string;
  amount: number;
  balance_after: number;
  narration?: string;
  counterparty?: { id: number; name: string; email: string };
  created_at: string;
}

export const getTransactions = (page = 1) =>
  client.get<{ data: Transaction[]; current_page: number; last_page: number }>(`/transactions?page=${page}`).then((r) => r.data);

export const getTransaction = (id: number | string) =>
  client.get<{ transaction: Transaction }>(`/transactions/${id}`).then((r) => r.data);