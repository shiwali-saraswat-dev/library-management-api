# Members module flow

Members are rows in `users` scoped to the `member` role (see `Member::booted()`'s global scope). Ownership checks (`own`) run through the Policy layer.

```mermaid
flowchart TD
    A(["Any /members/* request"]) --> B{"auth:sanctum"}
    B -- "no/invalid token" --> B1["401 Unauthenticated"]
    B -- "valid token" --> C{"Which action?"}

    C -- "GET /members (list)" --> D{"Policy: viewAny"}
    D -- "member role" --> D1["403 Forbidden"]
    D -- "owner/admin/librarian" --> D2["200 OK: paginated list"]

    C -- "GET /members/view/{id}" --> E{"Policy: view"}
    E -- "member, not own record" --> E1["403 Forbidden"]
    E -- "member, own record OR staff" --> E2["200 OK (ETag + Cache-Control: private, max-age=60)"]

    C -- "PUT /members/update/{id}" --> F{"Policy: update"}
    F -- "member, not own record" --> F1["403 Forbidden"]
    F -- "member, own record" --> F2["Update profile fields only — is_active, membership_number, roles stripped"]
    F -- "staff" --> F3["Update any field"]

    C -- "DELETE /members/destroy/{id}" --> G{"Policy: delete"}
    G -- member --> G1["403 Forbidden"]
    G -- staff --> H{"MemberRepository::hasActiveBorrowings"}
    H -- yes --> H1["409 Conflict — MemberHasActiveBorrowingsException"]
    H -- no --> H2["Soft delete"]

    C -- "PATCH /members/restore/{id}" --> I{"Policy: restore"}
    I -- member --> I1["403 Forbidden"]
    I -- staff --> J["MemberService::findTrashedOrFail — trashed-aware lookup, used BEFORE authorization"]
    J --> K{"Actually trashed?"}
    K -- no --> K1["409 — MemberNotTrashedException"]
    K -- yes --> K2["200 OK: restored"]

    C -- "DELETE /members/force-delete/{id}" --> L{"Policy: forceDelete — owner only"}
    L -- "not owner" --> L1["403 Forbidden"]
    L -- owner --> M["MemberService::findTrashedOrFail"]
    M --> N{"Actually trashed?"}
    N -- no --> N1["409 — MemberNotTrashedException"]
    N -- yes --> O{"hasActiveBorrowings?"}
    O -- yes --> O1["409 — MemberHasActiveBorrowingsException"]
    O -- no --> O2["204 No Content: permanently deleted"]
```

The `findTrashedOrFail` step on restore and force-delete is the fix for a real bug caught during development: the controller originally called the plain (non-trashed-aware) `findOrFail` before authorizing, which 404'd on any already-soft-deleted member — exactly the records these two actions are meant to operate on.
