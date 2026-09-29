import client from "./client";

export interface Wallet {
  id: number;
  currency: "NGN" | "USD" | "USDT";
  balance: number;
}

export const getWallets = () => client.get<{ wallets: Wallet[] }>("/wallets").then((r) => r.data);

export const fundWallet = (data: { currency: string; amount: number; idempotency_key: string }) =>
  client.post("/wallets/fund", data).then((r) => r.data);