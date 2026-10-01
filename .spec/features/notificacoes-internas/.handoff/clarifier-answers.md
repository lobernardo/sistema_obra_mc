# Developer answers — notificacoes-internas (resolve Q-01..Q-06)

The developer approved the SPEC subject to these decisions. Apply them in-place, remove the resolved [NEEDS CLARIFICATION] markers, update RF/UI/CT/RNF, Scope, TO BE prose, FLEXIBLE and Open Questions accordingly, and bump the version.

## Q-01 — Pedido "Outra" (obra_id null)
All **active** `suprimentos` users receive (minus the actor). Obra side unchanged: only the requester with papel `obra` (mirrors `PedidoPolicy::view`).

## Q-02 — Registering information/occurrences (scope reduced)
- Gestão, Suprimentos and Obra (with `view`) may register relevant information about the pedido. No restriction by type for Obra.
- It is a **free-text observation/occurrence field**; the free text is **mandatory**. Choosing a category must NOT be required. Categories (falta de produto, troca, atraso, problema de entrega, outra) may exist only as an **optional** tag if technically useful/for future organization.
- Every new entry must: go into the pedido history; generate in-app notification; generate e-mail to recipients; identify author, date/time and pedido; remain traceable (append-only).
- Router guidance (verify against code, then decide): the existing `observacao` flow (`AddPedidoObservacaoAction`, event `observacao`, allowed in any status, 3 roles, max 2000) already matches almost all of this. Prefer evolving that single flow ("Observação / ocorrência") with an optional category over introducing 5 mandatory occurrence event types. If an optional category is kept, define where it is stored without breaking `pedido_events` append-only semantics and existing history rendering; otherwise drop categories. Allowed in any status (same as observação today).

## Q-03 — Atraso
No automatic delay detection, no scheduler/cron in this feature. Delay is registered manually via the free field and notifies normally. Automatic overdue alerts = possible future separate feature (keep in Out of scope).

## Q-04 — E-mail
E-mail already works in production (password reset and invites are sent today via Resend), so do NOT frame anything as "can the server send e-mail". The only concern is dispatching notification e-mails without hurting user-action response time. Use Laravel `defer()` if adequate to the current architecture. Do not create queue/worker or new infra unless necessary.
Note for the SPEC: the remaining technical point is narrower — whether the production runtime (FrankenPHP via Railpack) flushes the response before deferred callbacks run (`fastcgi_finish_request` / FrankenPHP equivalent). Keep it as a lightweight verification task inside the plan (not a product question, no marker); if it does not flush, the e-mails still send correctly but after the response is computed — then reassess. Worker (option C) stays documented only as a future evolution requiring approval. Rewrite RNF-03 so it no longer carries a marker.

## Q-05 — Bell
Approved: refresh every 60 s, paused while the tab is hidden.

## Q-06 — Suprimentos responsible
YES. A `suprimentos` user receives notifications for a pedido when associated to the pedido's obra (`obra_profile`) **OR** set as the pedido's `responsible_id`. Either condition suffices. Still: active only, and the actor never receives a notification of their own action. (Recipient set is a union/dedup — one notification per user per event.)
