# Conventions
- Keep role/ownership checks explicit: route role middleware, policy authorization, and relation ownership checks are all used.
- Use FormRequest->validated() for mutation inputs; uploads should remain on private/local storage and downloads should authorize relation ownership.
- Database uniqueness is used for academic scopes; migrations may preflight duplicates before adding unique indexes.
- Reports/exports often materialize collections with get()/groupBy(); assess memory/timeout impact when changing report size or data volume.
- Use Serena symbol/reference tools for code navigation; do not infer architecture from one controller.
