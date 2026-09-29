import client from "./client";

export const createTransfer = (data: {
  recipient_email: string;
  currency: string;
  amount: number;
  reference: string;
  narration?: string;
}) => client.post("/transfers", data).then((r) => r.data);