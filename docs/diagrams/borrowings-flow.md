# Borrowings module flow

> **Unverified note:** this diagram is built from your project plan's stated business rules (section 6) and the README's access-control table — I haven't reviewed your actual `BorrowingController` / `BorrowingService` source in this conversation. Check this against the real implementation before treating it as documentation of fact rather than design intent.

```mermaid
flowchart TD
    A(["POST /borrowings — borrow a book"]) --> B{"auth:sanctum + role: owner/admin/librarian"}
    B -- "no/invalid token" --> B1["401 Unauthenticated"]
    B -- "member role" --> B2["403 Forbidden — members cannot self-serve borrow"]
    B -- staff --> C["Begin DB transaction"]
    C --> D["Lock the book row (lockForUpdate)"]
    D --> E{"member.is_active AND available_copies > 0 AND member's active borrowings < 3?"}
    E -- no --> E1["422 Unprocessable — rule violated"]
    E -- yes --> F["Create Borrowing: borrowed_at = now, due_at = +14 days"]
    F --> G["Decrement available_copies"]
    G --> H["Commit transaction"]
    H --> I["201 Created: borrowing record"]

    J(["PATCH /borrowings/{id}/return"]) --> K{"auth:sanctum + role: owner/admin/librarian"}
    K -- "no/invalid token" --> K1["401 Unauthenticated"]
    K -- "member role" --> K2["403 Forbidden"]
    K -- staff --> L["Begin DB transaction"]
    L --> M["Lock the book row"]
    M --> N{"returned_at already set?"}
    N -- yes --> N1["409 Conflict — already returned"]
    N -- no --> O["Set returned_at = now"]
    O --> P["Increment available_copies"]
    P --> Q["Commit transaction"]
    Q --> R["200 OK: updated borrowing"]

    S(["GET /borrowings"]) --> T{"auth:sanctum"}
    T -- member --> T1["Scoped to own borrowings only"]
    T -- staff --> T2["All borrowings, filterable by status=active|returned|overdue"]
```

The row lock on both borrow and return is what prevents two simultaneous requests from handing out (or double-crediting back) the same last copy — the concurrency guarantee your plan doc calls out explicitly.
