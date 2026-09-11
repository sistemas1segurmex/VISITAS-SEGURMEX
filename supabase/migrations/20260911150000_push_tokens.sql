-- Tokens de notificaciones push (Firebase Cloud Messaging) por vendedor.
-- Un vendedor puede tener varios (celular nuevo, reinstaló la app, etc.) --
-- se manda la notificación a todos los tokens vigentes de ese usuario.
CREATE TABLE IF NOT EXISTS "push_tokens" (
    "id"             serial PRIMARY KEY,
    "usuario_id"     integer NOT NULL REFERENCES "usuarios"("id") ON DELETE CASCADE,
    "token"          text NOT NULL,
    "plataforma"     varchar(20) NOT NULL DEFAULT 'android',
    "created_at"     timestamptz NOT NULL DEFAULT NOW(),
    "actualizado_en" timestamptz NOT NULL DEFAULT NOW(),
    CONSTRAINT "uq_push_tokens_token" UNIQUE ("token")
);
CREATE INDEX IF NOT EXISTS "idx_push_tokens_usuario" ON "push_tokens" ("usuario_id");
