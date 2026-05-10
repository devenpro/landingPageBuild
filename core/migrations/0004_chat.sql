-- core/migrations/0004_chat.sql — Phase 13 schema for the public chatbot.
--
-- ai_chat_messages: optional persistence layer for visitor ↔ assistant
-- conversations. Writes only happen when AI_CHAT_PERSIST=1 in .env;
-- otherwise the chat is in-session-only (the client holds the
-- conversation in localStorage and sends the full transcript on each
-- turn, so the server never needs durable memory).
--
-- session_id is a random opaque token generated client-side
-- (crypto.randomUUID() in the widget) and persisted in the visitor's
-- localStorage. It is NOT bound to any login or account; it groups
-- messages from the same browser. Wiping localStorage starts a fresh
-- conversation as far as analytics is concerned.
--
-- Privacy note: when persistence is on, this table holds visitor
-- messages verbatim. Anyone with admin access (or DB access) can read
-- them. Default is OFF for that reason; admins flip it on per-site.

CREATE TABLE IF NOT EXISTS ai_chat_messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  session_id TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('user','assistant')),
  content TEXT NOT NULL,
  ip_address TEXT,
  user_agent TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_chat_session ON ai_chat_messages(session_id, created_at);
CREATE INDEX IF NOT EXISTS idx_chat_created ON ai_chat_messages(created_at);
