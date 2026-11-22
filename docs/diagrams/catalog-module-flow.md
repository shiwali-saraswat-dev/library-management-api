# Catalog module flow (Authors, Categories, Books)

These three modules share one pattern: public reads, staff-only writes, owner-only permanent deletion.

```mermaid
flowchart TD
    A(["Request to /authors, /categories, or /books"]) --> B{"Which action?"}

    B -- "GET list or GET view/{id}" --> C["No auth required — public read"]
    C --> C1["Policy: viewAny / view (always allowed)"]
    C1 --> C2["200 OK: paginated or single resource"]

    B -- "POST /store, PUT /update/{id}, DELETE /destroy/{id}" --> D{"auth:sanctum"}
    D -- "no/invalid token" --> D1["401 Unauthenticated"]
    D -- "valid token" --> E{"Policy: create / update / delete"}
    E -- "member role" --> E1["403 Forbidden"]
    E -- "owner / admin / librarian" --> F["Service → Repository → DB write"]
    F -- "destroy, record has dependents" --> F1["409 Conflict"]
    F -- "otherwise" --> F2["200 / 201 / 204"]

    B -- "PATCH /restore/{id} or DELETE /force-delete/{id}" --> G{"auth:sanctum"}
    G -- "no/invalid token" --> G1["401 Unauthenticated"]
    G -- "valid token" --> H{"Policy: restore / forceDelete"}
    H -- "restore, member" --> H1["403 Forbidden"]
    H -- "restore, staff" --> I{"Actually trashed?"}
    H -- "forceDelete, not owner" --> H2["403 Forbidden — owner only"]
    H -- "forceDelete, owner" --> J{"Actually trashed?"}
    I -- no --> I1["409 Conflict: not trashed"]
    I -- yes --> I2["200 OK: restored"]
    J -- no --> J1["409 Conflict: not trashed"]
    J -- yes --> J2["204 No Content: permanently deleted"]
```

The `trashed` query parameter on the list endpoint (`only_deleted`, `with_deleted`) requires the same permission as the dedicated `/trashed` route — a member sending it gets `403`, not a silently-filtered result.
