# Auth module flow

Covers `POST /auth/register`, `POST /auth/login`, `POST /auth/refresh`, `POST /auth/logout`.

> Verified against the current Pest suite (`AuthTest`) rather than an older copy of `AuthController` — if your controller doesn't yet wrap registration in a transaction or force the role server-side, that's a gap between this diagram and the code worth closing.

```mermaid
flowchart TD
    A(["POST /auth/register"]) --> B["Validate via RegisterRequest"]
    B -- invalid --> B1["422 Validation error"]
    B -- valid --> C["DB transaction: create user, force role = 'member'"]
    C -- role assignment throws --> C1["Rollback — no user row persists"]
    C -- success --> D["Issue Sanctum token"]
    D --> E["201 Created: token + user envelope"]

    F(["POST /auth/login"]) --> G["Look up user by email"]
    G --> I{"User exists AND Hash::check(password) passes?"}
    I -- "no (either case)" --> J["422 — identical 'invalid credentials' envelope (no account enumeration)"]
    I -- yes --> K["Issue Sanctum token"]
    K --> L["200 OK: token + user envelope"]

    M(["POST /auth/refresh"]) --> N{"auth:sanctum"}
    N -- "no/invalid token" --> N1["401 Unauthenticated"]
    N -- "valid token" --> O["Revoke current token"]
    O --> P["Issue new token"]
    P --> Q["200 OK: new token"]

    R(["POST /auth/logout"]) --> S{"auth:sanctum"}
    S -- "no/invalid token" --> S1["401 Unauthenticated"]
    S -- "valid token" --> T["Revoke current token"]
    T --> U["200 OK: logged out"]
```

Key behaviors this diagram encodes:
- **No account enumeration** — a wrong password and an unknown email return the exact same response, so an attacker can't tell which one failed.
- **Role can't be set by the caller** — every new account is forced to `member` server-side, regardless of what the request sends.
- **Registration is transactional** — if role assignment fails for any reason, the whole operation rolls back; no orphaned user row is left behind.
