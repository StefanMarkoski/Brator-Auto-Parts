# Bounded contexts

Five contexts, per the schema plan. Each follows the house Laravel DDD spec:

```
app/Domain/{Context}/
├── Actions/          single-purpose writes; ≥2 aggregates ⇒ DB::transaction
├── DTOs/             readonly transfer objects
├── Enums/
├── Events/           past-tense domain facts, raised by Actions only
├── Http/
│   ├── Requests/     FormRequests exposing toDTO() / toFilterDTO()
│   └── Resources/
├── Jobs/
├── Listeners/
│   ├── Internal/     reacting to this context's own events
│   └── External/     reacting to another context's events
├── Models/           direct Eloquent, no repositories
├── Policies/
├── Processes/        multi-step workflows inside this context
├── Queries/
│   ├── Public/       cross-context reads — returns readonly Read DTOs
│   └── Internal/     own read helpers, may return Models
├── Rules/
├── Services/         multi-Action orchestration within this context
└── ValueObjects/     required for domain primitives with invariants
```

**Subfolders are created when the first class needs them,** not up front — 75 empty
directories is noise, not structure. The layout above is the contract; this file is
where it's recorded.

| Context | Owns |
|---|---|
| `Catalog` | products, brands, categories, attributes, images, cross-references, stock |
| `Fitment` | vehicle make/model/variant tree, part↔vehicle compatibility |
| `Ordering` | basket, receipt, receipt lines, the dummy checkout |
| `CatalogImport` | import sources/runs/staging, external refs, field-override provenance |
| `Content` | posts, pages, contact submissions |

## Two rules that matter most on this project

1. **Cross-context reads go through `Queries/Public/` only.** Never reach into another
   context's Models. Cross-context *writes* are events only.
2. **Catalogue listing reads do not travel through domain objects.** They are flat,
   indexed queries selecting explicit columns (never `SELECT *`, never the description
   columns). A parts catalogue is read-heavy; this is the rule that keeps it fast.
   See the schema plan, §10.
