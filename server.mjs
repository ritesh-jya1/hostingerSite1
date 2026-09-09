import crypto from "node:crypto";
import path from "node:path";
import { fileURLToPath } from "node:url";
import express from "express";
import cors from "cors";
import pg from "pg";

const { Pool } = pg;
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const publicDir = path.join(__dirname, "public");
const port = Number(process.env.PORT || 3000);
const databaseUrl = process.env.DATABASE_URL;
const adminPassword = process.env.ADMIN_PASSWORD;

if (!databaseUrl) {
  throw new Error("DATABASE_URL is required. Add it in Hostinger Environment Variables.");
}

if (!adminPassword) {
  throw new Error("ADMIN_PASSWORD is required. Add it in Hostinger Environment Variables.");
}

if (!Number.isInteger(port) || port <= 0) {
  throw new Error(`Invalid PORT value: ${process.env.PORT}`);
}

const pool = new Pool({ connectionString: databaseUrl });
const app = express();
const adminToken = crypto
  .createHmac("sha256", adminPassword)
  .update("knox-gable-admin")
  .digest("hex");

app.disable("x-powered-by");
app.use(cors());
app.use(express.json({ limit: "100kb" }));
app.use(express.urlencoded({ extended: true }));

function normalizeRegistration(row) {
  return {
    id: row.id,
    fullName: row.fullName,
    email: row.email,
    phone: row.phone,
    amazonOrderId: row.amazonOrderId,
    createdAt: new Date(row.createdAt).toISOString(),
  };
}

function isValidRegistration(body) {
  const fullName = typeof body?.fullName === "string" ? body.fullName.trim() : "";
  const email = typeof body?.email === "string" ? body.email.trim() : "";
  const phone = typeof body?.phone === "string" ? body.phone.trim() : "";
  const amazonOrderId =
    typeof body?.amazonOrderId === "string" ? body.amazonOrderId.trim() : "";

  if (!fullName || !email || !phone || !amazonOrderId) {
    return { valid: false, error: "All fields are required." };
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return { valid: false, error: "Please enter a valid email address." };
  }

  if (phone.replace(/\D/g, "").length < 10) {
    return { valid: false, error: "Please enter a valid phone number." };
  }

  return {
    valid: true,
    data: { fullName, email, phone, amazonOrderId },
  };
}

function isAdminTokenValid(value) {
  if (typeof value !== "string" || value.length !== adminToken.length) {
    return false;
  }

  return crypto.timingSafeEqual(
    Buffer.from(value, "utf8"),
    Buffer.from(adminToken, "utf8"),
  );
}

app.get("/api/healthz", (_req, res) => {
  res.json({ status: "ok" });
});

app.post("/api/registrations", async (req, res) => {
  const parsed = isValidRegistration(req.body);
  if (!parsed.valid) {
    res.status(400).json({ error: parsed.error });
    return;
  }

  const result = await pool.query(
    `INSERT INTO registrations (full_name, email, phone, amazon_order_id)
     VALUES ($1, $2, $3, $4)
     RETURNING id, full_name AS "fullName", email, phone,
               amazon_order_id AS "amazonOrderId", created_at AS "createdAt"`,
    [
      parsed.data.fullName,
      parsed.data.email,
      parsed.data.phone,
      parsed.data.amazonOrderId,
    ],
  );

  res.status(201).json(normalizeRegistration(result.rows[0]));
});

app.post("/api/admin/login", (req, res) => {
  if (
    typeof req.body?.password !== "string" ||
    req.body.password !== adminPassword
  ) {
    res.status(401).json({ error: "Invalid password." });
    return;
  }

  res.json({ token: adminToken });
});

app.get("/api/admin/registrations", async (req, res) => {
  const token = req.get("x-admin-token");
  if (!isAdminTokenValid(token)) {
    res.status(401).json({ error: "Unauthorized." });
    return;
  }

  const result = await pool.query(
    `SELECT id, full_name AS "fullName", email, phone,
            amazon_order_id AS "amazonOrderId", created_at AS "createdAt"
     FROM registrations
     ORDER BY created_at DESC`,
  );

  res.json(result.rows.map(normalizeRegistration));
});

app.use(express.static(publicDir, { index: "index.html" }));

app.get("/{*splat}", (req, res, next) => {
  if (req.path.startsWith("/api")) {
    next();
    return;
  }
  res.sendFile(path.join(publicDir, "index.html"));
});

app.use((error, _req, res, _next) => {
  console.error(error);
  res.status(500).json({ error: "Something went wrong. Please try again." });
});

app.listen(port, () => {
  console.log(`Knox & Gable is listening on port ${port}`);
});